<?php

namespace App\Http\Controllers;

use App\Models\Skill;
use App\Models\Tag;
use App\Services\AgentisManifestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BulkSkillController extends Controller
{
    public function __construct(
        protected AgentisManifestService $manifestService,
    ) {}

    public function bulkTag(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'skill_ids' => 'required|array|min:1',
            'skill_ids.*' => 'integer|exists:skills,id',
            'add_tags' => 'nullable|array',
            'add_tags.*' => 'string|max:50',
            'remove_tags' => 'nullable|array',
            'remove_tags.*' => 'string|max:50',
        ]);

        $skills = Skill::whereIn('id', $validated['skill_ids'])->get();
        $addTags = $validated['add_tags'] ?? [];
        $removeTags = $validated['remove_tags'] ?? [];

        // Resolve tag IDs to add
        $addTagIds = collect($addTags)->map(function (string $name) {
            return Tag::firstOrCreate(['name' => trim($name)])->id;
        });

        // Resolve tag IDs to remove
        $removeTagIds = [];
        if (! empty($removeTags)) {
            $removeTagIds = Tag::whereIn('name', $removeTags)->pluck('id')->all();
        }

        foreach ($skills as $skill) {
            if ($addTagIds->isNotEmpty()) {
                $skill->tags()->syncWithoutDetaching($addTagIds->all());
            }
            if (! empty($removeTagIds)) {
                $skill->tags()->detach($removeTagIds);
            }
        }

        return response()->json([
            'message' => "Updated tags on {$skills->count()} skill(s)",
            'count' => $skills->count(),
        ]);
    }

    public function bulkAssign(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'skill_ids' => 'required|array|min:1',
            'skill_ids.*' => 'integer|exists:skills,id',
            'agent_id' => 'required|integer|exists:agents,id',
            'project_id' => 'required|integer|exists:projects,id',
        ]);

        $skills = Skill::whereIn('id', $validated['skill_ids'])->get();

        foreach ($skills as $skill) {
            $skill->agents()->syncWithoutDetaching([
                $validated['agent_id'] => ['project_id' => $validated['project_id']],
            ]);
        }

        return response()->json([
            'message' => "Assigned {$skills->count()} skill(s) to agent",
            'count' => $skills->count(),
        ]);
    }

    public function bulkDelete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'skill_ids' => 'required|array|min:1',
            'skill_ids.*' => 'integer|exists:skills,id',
        ]);

        $skills = Skill::with('project')->whereIn('id', $validated['skill_ids'])->get();
        $count = $skills->count();

        foreach ($skills as $skill) {
            $this->manifestService->deleteSkillFile(
                $skill->project->resolved_path,
                $skill->slug,
            );
            $skill->delete();
        }

        return response()->json([
            'message' => "Deleted {$count} skill(s)",
            'count' => $count,
        ]);
    }

    public function bulkMove(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'skill_ids' => 'required|array|min:1',
            'skill_ids.*' => 'integer|exists:skills,id',
            'target_project_id' => 'required|integer|exists:projects,id',
        ]);

        $skills = Skill::with('project', 'tags')->whereIn('id', $validated['skill_ids'])->get();
        $targetProject = \App\Models\Project::findOrFail($validated['target_project_id']);
        $count = 0;

        foreach ($skills as $skill) {
            $oldProject = $skill->project;

            // Skip if already in target project
            if ($skill->project_id === $targetProject->id) {
                continue;
            }

            // Remove file from old project
            $this->manifestService->deleteSkillFile(
                $oldProject->resolved_path,
                $skill->slug,
            );

            // Ensure slug is unique in target project
            $slug = $skill->slug;
            $baseSlug = $slug;
            $counter = 1;
            while ($targetProject->skills()->where('slug', $slug)->exists()) {
                $slug = "{$baseSlug}-{$counter}";
                $counter++;
            }

            // Update skill
            $skill->update([
                'project_id' => $targetProject->id,
                'slug' => $slug,
            ]);

            // Write file to new project
            $frontmatter = [
                'id' => $skill->slug,
                'name' => $skill->name,
                'description' => $skill->description,
                'tags' => $skill->tags->pluck('name')->values()->all(),
                'model' => $skill->model,
                'max_tokens' => $skill->max_tokens,
                'tools' => $skill->tools ?? [],
                'includes' => $skill->includes ?? [],
                'created_at' => $skill->created_at->toIso8601String(),
                'updated_at' => $skill->updated_at->toIso8601String(),
            ];

            $this->manifestService->writeSkillFile(
                $targetProject->resolved_path,
                $frontmatter,
                $skill->body ?? '',
            );

            $count++;
        }

        return response()->json([
            'message' => "Moved {$count} skill(s) to {$targetProject->name}",
            'count' => $count,
        ]);
    }
}
