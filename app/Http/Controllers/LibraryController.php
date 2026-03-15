<?php

namespace App\Http\Controllers;

use App\Http\Resources\SkillResource;
use App\Models\LibrarySkill;
use App\Models\Project;
use App\Models\Tag;
use App\Services\AgentisManifestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LibraryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = LibrarySkill::query();

        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }

        if ($tags = $request->input('tags')) {
            $tagNames = is_array($tags) ? $tags : explode(',', $tags);
            foreach ($tagNames as $tag) {
                $query->whereJsonContains('tags', trim($tag));
            }
        }

        if ($q = $request->input('q')) {
            $query->where(function ($qb) use ($q) {
                $qb->where('name', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            });
        }

        return response()->json($query->orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'body' => 'nullable|string',
            'tags' => 'nullable|array',
            'frontmatter' => 'nullable|array',
        ]);

        $validated['slug'] = $validated['slug'] ?? Str::slug($validated['name']);

        // Ensure unique slug
        $baseSlug = $validated['slug'];
        $counter = 1;
        while (LibrarySkill::where('slug', $validated['slug'])->exists()) {
            $validated['slug'] = "{$baseSlug}-{$counter}";
            $counter++;
        }

        $skill = LibrarySkill::create($validated);

        return response()->json($skill, 201);
    }

    public function update(Request $request, LibrarySkill $librarySkill): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:255',
            'category' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'body' => 'nullable|string',
            'tags' => 'nullable|array',
            'frontmatter' => 'nullable|array',
        ]);

        $librarySkill->update($validated);

        return response()->json($librarySkill->fresh());
    }

    public function destroy(LibrarySkill $librarySkill): JsonResponse
    {
        $librarySkill->delete();

        return response()->json(null, 204);
    }

    public function import(Request $request, LibrarySkill $librarySkill, AgentisManifestService $manifestService): SkillResource
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
        ]);

        $project = Project::findOrFail($validated['project_id']);

        // Generate unique slug
        $slug = $librarySkill->slug;
        $baseSlug = $slug;
        $counter = 1;
        while ($project->skills()->where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        $skill = $project->skills()->create([
            'slug' => $slug,
            'name' => $librarySkill->name,
            'description' => $librarySkill->description,
            'model' => $librarySkill->frontmatter['model'] ?? null,
            'max_tokens' => $librarySkill->frontmatter['max_tokens'] ?? null,
            'tools' => $librarySkill->frontmatter['tools'] ?? [],
            'body' => $librarySkill->body,
        ]);

        // Sync tags
        if (! empty($librarySkill->tags)) {
            $tagIds = collect($librarySkill->tags)->map(function (string $name) {
                return Tag::firstOrCreate(['name' => trim($name)])->id;
            });
            $skill->tags()->sync($tagIds);
        }

        // Create v1 version
        $skill->versions()->create([
            'version_number' => 1,
            'frontmatter' => array_merge($librarySkill->frontmatter ?? [], [
                'id' => $slug,
                'name' => $librarySkill->name,
                'description' => $librarySkill->description,
            ]),
            'body' => $librarySkill->body,
            'note' => "Imported from library: {$librarySkill->name}",
            'saved_at' => now(),
        ]);

        // Write file
        $frontmatter = [
            'id' => $slug,
            'name' => $skill->name,
            'description' => $skill->description,
            'tags' => $librarySkill->tags ?? [],
            'model' => $skill->model,
            'max_tokens' => $skill->max_tokens,
            'tools' => $skill->tools ?? [],
            'created_at' => $skill->created_at->toIso8601String(),
            'updated_at' => $skill->updated_at->toIso8601String(),
        ];

        $manifestService->writeSkillFile($project->resolved_path, $frontmatter, $skill->body ?? '');

        return new SkillResource($skill->load('tags'));
    }
}
