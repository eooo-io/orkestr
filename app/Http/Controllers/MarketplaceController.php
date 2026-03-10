<?php

namespace App\Http\Controllers;

use App\Models\LibrarySkill;
use App\Models\MarketplaceSkill;
use App\Models\Project;
use App\Models\Skill;
use App\Models\Tag;
use App\Services\AgentisManifestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MarketplaceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = MarketplaceSkill::query();

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

        $sort = $request->input('sort', 'newest');
        $query = match ($sort) {
            'popular' => $query->orderByDesc('downloads'),
            'top-rated' => $query->orderByRaw('(upvotes - downvotes) DESC'),
            default => $query->orderByDesc('created_at'),
        };

        $paginated = $query->paginate(20);

        return response()->json($paginated);
    }

    public function show(MarketplaceSkill $marketplaceSkill): JsonResponse
    {
        return response()->json(['data' => $marketplaceSkill]);
    }

    public function publish(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_type' => 'required|in:library,skill',
            'source_id' => 'required|integer',
            'author' => 'nullable|string|max:255',
        ]);

        if ($validated['source_type'] === 'library') {
            $source = LibrarySkill::findOrFail($validated['source_id']);
            $marketplaceSkill = MarketplaceSkill::create([
                'name' => $source->name,
                'slug' => $this->uniqueSlug($source->slug ?? Str::slug($source->name)),
                'description' => $source->description,
                'category' => $source->category,
                'tags' => $source->tags ?? [],
                'frontmatter' => $source->frontmatter ?? [],
                'body' => $source->body ?? '',
                'author' => $validated['author'] ?? null,
                'source' => 'library',
            ]);
        } else {
            $source = Skill::findOrFail($validated['source_id']);
            $marketplaceSkill = MarketplaceSkill::create([
                'name' => $source->name,
                'slug' => $this->uniqueSlug($source->slug),
                'description' => $source->description,
                'category' => null,
                'tags' => $source->tags ? $source->tags->pluck('name')->toArray() : [],
                'frontmatter' => [
                    'model' => $source->model,
                    'max_tokens' => $source->max_tokens,
                    'tools' => $source->tools ?? [],
                ],
                'body' => $source->body ?? '',
                'author' => $validated['author'] ?? null,
                'source' => 'project',
            ]);
        }

        return response()->json(['data' => $marketplaceSkill], 201);
    }

    public function install(Request $request, MarketplaceSkill $marketplaceSkill, AgentisManifestService $manifestService): JsonResponse
    {
        $validated = $request->validate([
            'target' => 'required|in:library,project',
            'project_id' => 'required_if:target,project|nullable|exists:projects,id',
        ]);

        if ($validated['target'] === 'library') {
            LibrarySkill::create([
                'name' => $marketplaceSkill->name,
                'slug' => $marketplaceSkill->slug,
                'description' => $marketplaceSkill->description,
                'category' => $marketplaceSkill->category,
                'tags' => $marketplaceSkill->tags ?? [],
                'frontmatter' => $marketplaceSkill->frontmatter ?? [],
                'body' => $marketplaceSkill->body,
                'source' => 'marketplace',
            ]);
        } else {
            $project = Project::findOrFail($validated['project_id']);

            $slug = $marketplaceSkill->slug;
            $baseSlug = $slug;
            $counter = 1;
            while ($project->skills()->where('slug', $slug)->exists()) {
                $slug = "{$baseSlug}-{$counter}";
                $counter++;
            }

            $skill = $project->skills()->create([
                'slug' => $slug,
                'name' => $marketplaceSkill->name,
                'description' => $marketplaceSkill->description,
                'model' => $marketplaceSkill->frontmatter['model'] ?? null,
                'max_tokens' => $marketplaceSkill->frontmatter['max_tokens'] ?? null,
                'tools' => $marketplaceSkill->frontmatter['tools'] ?? [],
                'body' => $marketplaceSkill->body,
            ]);

            // Sync tags
            if (! empty($marketplaceSkill->tags)) {
                $tagIds = collect($marketplaceSkill->tags)->map(function (string $name) {
                    return Tag::firstOrCreate(['name' => trim($name)])->id;
                });
                $skill->tags()->sync($tagIds);
            }

            // Create v1 version
            $skill->versions()->create([
                'version_number' => 1,
                'frontmatter' => array_merge($marketplaceSkill->frontmatter ?? [], [
                    'id' => $slug,
                    'name' => $marketplaceSkill->name,
                    'description' => $marketplaceSkill->description,
                ]),
                'body' => $marketplaceSkill->body,
                'note' => "Installed from marketplace: {$marketplaceSkill->name}",
                'saved_at' => now(),
            ]);

            // Write file
            $frontmatter = [
                'id' => $slug,
                'name' => $skill->name,
                'description' => $skill->description,
                'tags' => $marketplaceSkill->tags ?? [],
                'model' => $skill->model,
                'max_tokens' => $skill->max_tokens,
                'tools' => $skill->tools ?? [],
                'created_at' => $skill->created_at->toIso8601String(),
                'updated_at' => $skill->updated_at->toIso8601String(),
            ];

            $manifestService->writeSkillFile($project->resolved_path, $frontmatter, $skill->body ?? '');
        }

        $marketplaceSkill->increment('downloads');

        return response()->json(['data' => $marketplaceSkill->fresh(), 'message' => 'Skill installed successfully.']);
    }

    public function vote(Request $request, MarketplaceSkill $marketplaceSkill): JsonResponse
    {
        $validated = $request->validate([
            'vote' => 'required|in:up,down',
        ]);

        if ($validated['vote'] === 'up') {
            $marketplaceSkill->increment('upvotes');
        } else {
            $marketplaceSkill->increment('downvotes');
        }

        return response()->json(['data' => $marketplaceSkill->fresh()]);
    }

    private function uniqueSlug(string $slug): string
    {
        $baseSlug = $slug;
        $counter = 1;
        while (MarketplaceSkill::where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
