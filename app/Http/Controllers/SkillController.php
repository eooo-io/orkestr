<?php

namespace App\Http\Controllers;

use App\Http\Resources\SkillResource;
use App\Models\Project;
use App\Models\Skill;
use App\Models\Tag;
use App\Services\AgentisManifestService;
use App\Services\GitService;
use App\Services\PromptLinter;
use App\Services\SkillCompositionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class SkillController extends Controller
{
    public function __construct(
        protected AgentisManifestService $manifestService,
        protected SkillCompositionService $compositionService,
        protected GitService $gitService,
    ) {}

    public function index(Project $project): AnonymousResourceCollection
    {
        $skills = $project->skills()->with('tags')->orderBy('name')->get();

        return SkillResource::collection($skills);
    }

    public function store(Request $request, Project $project): SkillResource
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'model' => 'nullable|string|max:100',
            'max_tokens' => 'nullable|integer|min:1',
            'tools' => 'nullable|array',
            'includes' => 'nullable|array',
            'includes.*' => 'string|max:100',
            'body' => 'nullable|string',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'template_variables' => 'nullable|array',
            'template_variables.*.name' => 'required|string|max:100',
            'template_variables.*.description' => 'nullable|string|max:500',
            'template_variables.*.default' => 'nullable|string|max:10000',
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
            'model' => $validated['model'] ?? null,
            'max_tokens' => $validated['max_tokens'] ?? null,
            'tools' => $validated['tools'] ?? [],
            'includes' => $validated['includes'] ?? [],
            'body' => $validated['body'] ?? '',
            'template_variables' => $validated['template_variables'] ?? null,
        ]);

        $this->syncTags($skill, $validated['tags'] ?? []);
        $this->createVersion($skill);
        $this->writeFile($project, $skill);

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
            'model' => 'nullable|string|max:100',
            'max_tokens' => 'nullable|integer|min:1',
            'tools' => 'nullable|array',
            'includes' => 'nullable|array',
            'includes.*' => 'string|max:100',
            'body' => 'nullable|string',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'template_variables' => 'nullable|array',
            'template_variables.*.name' => 'required|string|max:100',
            'template_variables.*.description' => 'nullable|string|max:500',
            'template_variables.*.default' => 'nullable|string|max:10000',
        ]);

        $skill->update(collect($validated)->except('tags')->toArray());

        if (array_key_exists('tags', $validated)) {
            $this->syncTags($skill, $validated['tags'] ?? []);
        }

        $this->createVersion($skill);
        $this->writeFile($skill->project, $skill);

        return new SkillResource($skill->load('tags'));
    }

    public function lint(Skill $skill, PromptLinter $linter): JsonResponse
    {
        $issues = $linter->lint($skill->body ?? '');

        return response()->json(['data' => $issues]);
    }

    public function destroy(Skill $skill): JsonResponse
    {
        $this->manifestService->deleteSkillFile($skill->project->resolved_path, $skill->slug);
        $skill->delete();

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
            'model' => $skill->model,
            'max_tokens' => $skill->max_tokens,
            'tools' => $skill->tools,
            'includes' => $skill->includes,
            'body' => $skill->body,
            'template_variables' => $skill->template_variables,
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

    protected function createVersion(Skill $skill): void
    {
        $nextVersion = ($skill->versions()->max('version_number') ?? 0) + 1;

        $skill->versions()->create([
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
            'saved_at' => now(),
        ]);
    }

    protected function writeFile(Project $project, Skill $skill): void
    {
        $frontmatter = [
            'id' => $skill->slug,
            'name' => $skill->name,
            'description' => $skill->description,
            'tags' => $skill->tags->pluck('name')->values()->all(),
            'model' => $skill->model,
            'max_tokens' => $skill->max_tokens,
            'tools' => $skill->tools ?? [],
            'includes' => $skill->includes ?? [],
            'template_variables' => $skill->template_variables,
            'created_at' => $skill->created_at->toIso8601String(),
            'updated_at' => $skill->updated_at->toIso8601String(),
        ];

        $this->manifestService->writeSkillFile($project->resolved_path, $frontmatter, $skill->body ?? '');

        // Auto-commit if enabled
        if ($project->git_auto_commit) {
            try {
                $relativePath = ".agentis/skills/{$skill->slug}.md";
                $this->gitService->commit(
                    $project->resolved_path,
                    $relativePath,
                    "agentis: update {$skill->name}",
                );
            } catch (\RuntimeException) {
                // Silently skip if git fails (not a git repo, etc.)
            }
        }
    }
}
