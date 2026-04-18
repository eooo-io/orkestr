<?php

namespace App\Http\Controllers;

use App\Http\Resources\SkillResource;
use App\Models\Project;
use App\Models\Skill;
use App\Models\Tag;
use App\Services\ManifestService;
use App\Services\GitService;
use App\Services\PromptLinter;
use App\Services\SkillCompositionService;
use App\Services\SkillEvalGateService;
use App\Services\SkillStalenessService;
use App\Services\WebhookDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class SkillController extends Controller
{
    public function __construct(
        protected ManifestService $manifestService,
        protected SkillCompositionService $compositionService,
        protected GitService $gitService,
        protected WebhookDispatcher $webhookDispatcher,
    ) {}

    public function index(Project $project): AnonymousResourceCollection
    {
        $skills = $project->skills()->with('tags')->orderBy('name')->get();

        return SkillResource::collection($skills);
    }

    /**
     * Lightweight skill index for agent runtime — name + summary only (~100 tokens each).
     */
    public function skillIndex(Project $project): JsonResponse
    {
        $skills = $project->skills()
            ->select('id', 'slug', 'name', 'description', 'summary', 'model')
            ->orderBy('name')
            ->get()
            ->map(fn ($s) => [
                'slug' => $s->slug,
                'name' => $s->name,
                'summary' => $s->summary ?? $s->description ?? '',
                'model' => $s->model,
            ]);

        return response()->json([
            'data' => $skills,
            'count' => $skills->count(),
            'token_estimate' => (int) ceil(mb_strlen($skills->toJson()) / 4),
        ]);
    }

    public function store(Request $request, Project $project): SkillResource
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'summary' => 'nullable|string|max:500',
            'model' => 'nullable|string|max:100',
            'max_tokens' => 'nullable|integer|min:1',
            'tools' => 'nullable|array',
            'includes' => 'nullable|array',
            'includes.*' => 'string|max:100',
            'body' => 'nullable|string',
            'conditions' => 'nullable|array',
            'conditions.file_patterns' => 'nullable|array',
            'conditions.file_patterns.*' => 'string|max:200',
            'conditions.path_prefixes' => 'nullable|array',
            'conditions.path_prefixes.*' => 'string|max:500',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'template_variables' => 'nullable|array',
            'template_variables.*.name' => 'required|string|max:100',
            'template_variables.*.description' => 'nullable|string|max:500',
            'template_variables.*.default' => 'nullable|string|max:10000',
            'category_id' => 'nullable|integer|exists:skill_categories,id',
            'skill_type' => 'nullable|string|in:capability_uplift,encoded_preference,hybrid',
        ]);

        $slug = Str::slug($validated['name']);
        $baseSlug = $slug;
        $counter = 1;
        while ($project->skills()->where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        $skill = $project->skills()->create([
            'slug' => $slug,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'summary' => $validated['summary'] ?? null,
            'model' => $validated['model'] ?? null,
            'tuned_for_model' => $validated['model'] ?? null,
            'max_tokens' => $validated['max_tokens'] ?? null,
            'tools' => $validated['tools'] ?? [],
            'includes' => $validated['includes'] ?? [],
            'body' => $validated['body'] ?? '',
            'conditions' => $validated['conditions'] ?? null,
            'template_variables' => $validated['template_variables'] ?? null,
            'category_id' => $validated['category_id'] ?? null,
            'skill_type' => $validated['skill_type'] ?? null,
        ]);

        $this->syncTags($skill, $validated['tags'] ?? []);
        $this->createVersion($skill);
        $this->writeFile($project, $skill);
        $this->dispatchWebhook('skill.created', $project, $skill);

        return new SkillResource($skill->load('tags'));
    }

    public function show(Skill $skill): SkillResource
    {
        return new SkillResource($skill->load('tags', 'project'));
    }

    public function update(Request $request, Skill $skill): SkillResource
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'summary' => 'nullable|string|max:500',
            'model' => 'nullable|string|max:100',
            'max_tokens' => 'nullable|integer|min:1',
            'tools' => 'nullable|array',
            'includes' => 'nullable|array',
            'includes.*' => 'string|max:100',
            'body' => 'nullable|string',
            'conditions' => 'nullable|array',
            'conditions.file_patterns' => 'nullable|array',
            'conditions.file_patterns.*' => 'string|max:200',
            'conditions.path_prefixes' => 'nullable|array',
            'conditions.path_prefixes.*' => 'string|max:500',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'template_variables' => 'nullable|array',
            'template_variables.*.name' => 'required|string|max:100',
            'template_variables.*.description' => 'nullable|string|max:500',
            'template_variables.*.default' => 'nullable|string|max:10000',
            'category_id' => 'nullable|integer|exists:skill_categories,id',
            'skill_type' => 'nullable|string|in:capability_uplift,encoded_preference,hybrid',
        ]);

        $skill->update(collect($validated)->except('tags')->toArray());

        if ($skill->tuned_for_model === null && ! empty($skill->model)) {
            $skill->update(['tuned_for_model' => $skill->model]);
        }

        if (array_key_exists('tags', $validated)) {
            $this->syncTags($skill, $validated['tags'] ?? []);
        }

        $version = $this->createVersion($skill);
        $this->writeFile($skill->project, $skill);
        $this->dispatchWebhook('skill.updated', $skill->project, $skill);

        $gateDecision = app(SkillEvalGateService::class)->evaluateSkillSave($skill, $version);

        return (new SkillResource($skill->load('tags')))
            ->additional(['gate_decision' => $gateDecision]);
    }

    public function lint(Skill $skill, PromptLinter $linter): JsonResponse
    {
        $issues = $linter->lintSkill($skill);

        return response()->json(['data' => $issues]);
    }

    public function staleness(Skill $skill, Request $request, SkillStalenessService $staleness): JsonResponse
    {
        return response()->json([
            'data' => $staleness->statusFor($skill, $request->query('current_model')),
        ]);
    }

    public function updateStaleness(Skill $skill, Request $request, SkillStalenessService $staleness): JsonResponse
    {
        $validated = $request->validate([
            'tuned_for_model' => 'nullable|string|max:100',
        ]);

        $skill->update(['tuned_for_model' => $validated['tuned_for_model'] ?? null]);

        return response()->json([
            'data' => $staleness->statusFor($skill->fresh()),
        ]);
    }

    public function destroy(Skill $skill): JsonResponse
    {
        $project = $skill->project;
        $skillData = ['id' => $skill->id, 'slug' => $skill->slug, 'name' => $skill->name];
        if ($project->resolved_path) {
            $this->manifestService->deleteSkillFile($project->resolved_path, $skill->slug);
        }
        $skill->delete();

        $this->webhookDispatcher->dispatch('skill.deleted', $project, $skillData);

        return response()->json(['message' => 'Skill deleted']);
    }

    public function duplicate(Request $request, Skill $skill): SkillResource
    {
        $targetProjectId = $request->input('target_project_id', $skill->project_id);
        $targetProject = Project::findOrFail($targetProjectId);

        $newName = $skill->name . ' (Copy)';
        $slug = Str::slug($newName);
        $baseSlug = $slug;
        $counter = 1;
        while ($targetProject->skills()->where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        $newSkill = $targetProject->skills()->create([
            'slug' => $slug,
            'name' => $newName,
            'description' => $skill->description,
            'summary' => $skill->summary,
            'model' => $skill->model,
            'max_tokens' => $skill->max_tokens,
            'tools' => $skill->tools,
            'includes' => $skill->includes,
            'body' => $skill->body,
            'template_variables' => $skill->template_variables,
            'category_id' => $skill->category_id,
            'skill_type' => $skill->skill_type,
        ]);

        $newSkill->tags()->sync($skill->tags->pluck('id'));
        $this->createVersion($newSkill);
        $this->writeFile($targetProject, $newSkill);

        return new SkillResource($newSkill->load('tags'));
    }

    protected function syncTags(Skill $skill, array $tagNames): void
    {
        $tagIds = collect($tagNames)->map(function (string $name) {
            return Tag::firstOrCreate(['name' => trim($name)])->id;
        });

        $skill->tags()->sync($tagIds);
    }

    protected function createVersion(Skill $skill): \App\Models\SkillVersion
    {
        $nextVersion = ($skill->versions()->max('version_number') ?? 0) + 1;

        return $skill->versions()->create([
            'version_number' => $nextVersion,
            'frontmatter' => [
                'id' => $skill->slug,
                'name' => $skill->name,
                'description' => $skill->description,
                'model' => $skill->model,
                'max_tokens' => $skill->max_tokens,
                'tools' => $skill->tools,
                'tags' => $skill->tags->pluck('name')->values()->all(),
                'template_variables' => $skill->template_variables,
            ],
            'body' => $skill->body,
            'tuned_for_model' => $skill->tuned_for_model,
            'saved_at' => now(),
        ]);
    }

    protected function dispatchWebhook(string $event, Project $project, Skill|array $skill): void
    {
        $payload = is_array($skill) ? $skill : [
            'id' => $skill->id,
            'slug' => $skill->slug,
            'name' => $skill->name,
            'project_id' => $project->id,
        ];

        $this->webhookDispatcher->dispatch($event, $project, $payload);
    }

    protected function writeFile(Project $project, Skill $skill): void
    {
        if (! $project->resolved_path) {
            return;
        }

        $frontmatter = [
            'id' => $skill->slug,
            'name' => $skill->name,
            'description' => $skill->description,
            'summary' => $skill->summary,
            'tags' => $skill->tags->pluck('name')->values()->all(),
            'model' => $skill->model,
            'max_tokens' => $skill->max_tokens,
            'tools' => $skill->tools ?? [],
            'includes' => $skill->includes ?? [],
            'conditions' => $skill->conditions,
            'template_variables' => $skill->template_variables,
            'created_at' => $skill->created_at->toIso8601String(),
            'updated_at' => $skill->updated_at->toIso8601String(),
        ];

        $this->manifestService->writeSkillFile($project->resolved_path, $frontmatter, $skill->body ?? '');

        // Auto-commit if enabled
        if ($project->git_auto_commit) {
            try {
                // Folder skills use the directory path, flat skills use the .md file
                $folderPath = $this->manifestService->getSkillFolderPath($project->resolved_path, $skill->slug);
                $relativePath = $folderPath
                    ? ".orkestr/skills/{$skill->slug}/"
                    : ".orkestr/skills/{$skill->slug}.md";

                $this->gitService->commit(
                    $project->resolved_path,
                    $relativePath,
                    "orkestr: update {$skill->name}",
                );
            } catch (\RuntimeException) {
                // Silently skip if git fails (not a git repo, etc.)
            }
        }
    }
}
