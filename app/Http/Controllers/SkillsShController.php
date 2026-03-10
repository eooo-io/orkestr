<?php

namespace App\Http\Controllers;

use App\Http\Resources\SkillResource;
use App\Models\LibrarySkill;
use App\Models\Project;
use App\Models\Tag;
use App\Services\AgentisManifestService;
use App\Services\SkillsShService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SkillsShController extends Controller
{
    public function __construct(
        protected SkillsShService $skillsSh,
        protected AgentisManifestService $manifestService,
    ) {}

    /**
     * Discover skills in a GitHub repository (fast — single API call, no content fetch).
     */
    public function discover(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'repo' => ['required', 'string', 'regex:#^[\w.\-]+/[\w.\-]+$#'],
        ]);

        try {
            $skills = $this->skillsSh->discoverSkills($validated['repo']);

            return response()->json([
                'data' => $skills,
                'repo' => $validated['repo'],
                'count' => count($skills),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Fetch details for a batch of discovered skills (descriptions, content).
     */
    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'repo' => ['required', 'string', 'regex:#^[\w.\-]+/[\w.\-]+$#'],
            'paths' => 'required|array|max:30',
            'paths.*' => 'string',
        ]);

        try {
            $skills = $this->skillsSh->fetchSkillBatch($validated['repo'], $validated['paths']);

            return response()->json(['data' => $skills]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Import a skill from a GitHub repository into the library or a project.
     */
    public function import(Request $request): JsonResponse|SkillResource
    {
        $validated = $request->validate([
            'repo' => ['required', 'string', 'regex:#^[\w.\-]+/[\w.\-]+$#'],
            'path' => 'required|string',
            'target' => 'required|in:library,project',
            'project_id' => 'required_if:target,project|nullable|exists:projects,id',
        ]);

        try {
            $skill = $this->skillsSh->fetchSkill($validated['repo'], $validated['path']);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if ($validated['target'] === 'library') {
            return $this->importToLibrary($skill, $validated['repo']);
        }

        $project = Project::findOrFail($validated['project_id']);

        return $this->importToProject($skill, $validated['repo'], $project);
    }

    protected function importToLibrary(array $skill, string $repo): JsonResponse
    {
        $slug = Str::slug($skill['name']);

        // Don't duplicate
        if (LibrarySkill::where('slug', $slug)->exists()) {
            return response()->json(['message' => "Skill '{$skill['name']}' already exists in library."], 409);
        }

        $tags = $skill['frontmatter']['tags'] ?? [];
        if (is_string($tags)) {
            $tags = array_map('trim', explode(',', $tags));
        }

        $librarySkill = LibrarySkill::create([
            'name' => $skill['name'],
            'slug' => $slug,
            'description' => $skill['description'],
            'category' => $this->deriveCategory($skill['path']),
            'tags' => $tags,
            'frontmatter' => $skill['frontmatter'],
            'body' => $skill['body'],
            'source' => "skills.sh:{$repo}",
            'created_at' => now(),
        ]);

        return response()->json(['data' => $librarySkill, 'message' => 'Imported to library']);
    }

    protected function importToProject(array $skill, string $repo, Project $project): SkillResource
    {
        $slug = Str::slug($skill['name']);
        $baseSlug = $slug;
        $counter = 1;
        while ($project->skills()->where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        $tags = $skill['frontmatter']['tags'] ?? [];
        if (is_string($tags)) {
            $tags = array_map('trim', explode(',', $tags));
        }

        $projectSkill = $project->skills()->create([
            'slug' => $slug,
            'name' => $skill['name'],
            'description' => $skill['description'],
            'model' => $skill['frontmatter']['model'] ?? null,
            'max_tokens' => $skill['frontmatter']['max_tokens'] ?? null,
            'tools' => $skill['frontmatter']['tools'] ?? [],
            'body' => $skill['body'],
        ]);

        // Sync tags
        if (! empty($tags)) {
            $tagIds = collect($tags)->map(fn (string $name) => Tag::firstOrCreate(['name' => trim($name)])->id);
            $projectSkill->tags()->sync($tagIds);
        }

        // Create v1 version
        $projectSkill->versions()->create([
            'version_number' => 1,
            'frontmatter' => array_merge($skill['frontmatter'], [
                'id' => $slug,
                'name' => $skill['name'],
                'description' => $skill['description'],
            ]),
            'body' => $skill['body'],
            'note' => "Imported from skills.sh: {$repo}",
            'saved_at' => now(),
        ]);

        // Write file
        $frontmatter = [
            'id' => $slug,
            'name' => $projectSkill->name,
            'description' => $projectSkill->description,
            'tags' => $tags,
            'model' => $projectSkill->model,
            'max_tokens' => $projectSkill->max_tokens,
            'tools' => $projectSkill->tools ?? [],
            'created_at' => $projectSkill->created_at->toIso8601String(),
            'updated_at' => $projectSkill->updated_at->toIso8601String(),
        ];

        $this->manifestService->writeSkillFile($project->resolved_path, $frontmatter, $projectSkill->body ?? '');

        return new SkillResource($projectSkill->load('tags'));
    }

    protected function deriveCategory(string $path): ?string
    {
        if (str_contains($path, '.curated/')) {
            return 'Curated';
        }
        if (str_contains($path, '.experimental/')) {
            return 'Experimental';
        }
        if (str_contains($path, '.system/')) {
            return 'System';
        }

        return 'Community';
    }
}
