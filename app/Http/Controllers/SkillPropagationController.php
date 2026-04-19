<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\SkillPropagation;
use App\Services\SkillPropagationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SkillPropagationController extends Controller
{
    public function __construct(
        protected SkillPropagationService $service,
    ) {}

    public function index(Organization $organization): JsonResponse
    {
        $propagations = SkillPropagation::query()
            ->with([
                'sourceSkill:id,slug,name,project_id',
                'sourceSkill.project:id,name,organization_id',
                'targetProject:id,name,organization_id',
                'targetAgent:id,name,slug',
            ])
            ->whereHas('targetProject', fn ($q) => $q->where('organization_id', $organization->id))
            ->pending()
            ->orderByDesc('suggestion_score')
            ->limit(100)
            ->get();

        return response()->json(['data' => $propagations]);
    }

    public function accept(Request $request, SkillPropagation $propagation): JsonResponse
    {
        if ($propagation->status !== SkillPropagation::STATUS_SUGGESTED) {
            return response()->json(['error' => 'Already resolved.'], 422);
        }

        $validated = $request->validate([
            'body_override' => 'nullable|string',
        ]);

        $skill = $this->service->accept($propagation, $validated['body_override'] ?? null);
        $propagation->update(['resolved_by' => Auth::id()]);

        return response()->json([
            'data' => [
                'propagation' => $propagation->fresh(),
                'new_skill_id' => $skill->id,
                'new_skill_slug' => $skill->slug,
            ],
        ]);
    }

    public function dismiss(SkillPropagation $propagation): JsonResponse
    {
        if ($propagation->status !== SkillPropagation::STATUS_SUGGESTED) {
            return response()->json(['error' => 'Already resolved.'], 422);
        }

        $this->service->dismiss($propagation);
        $propagation->update(['resolved_by' => Auth::id()]);

        return response()->json(['data' => $propagation->fresh()]);
    }

    /**
     * Lineage for a skill that was created via propagation.
     */
    public function lineage(int $skillId): JsonResponse
    {
        $propagation = SkillPropagation::where('modified_skill_id', $skillId)
            ->with(['sourceSkill.project'])
            ->first();

        if (! $propagation) {
            return response()->json(['data' => null]);
        }

        return response()->json([
            'data' => [
                'source_skill_id' => $propagation->source_skill_id,
                'source_skill_slug' => $propagation->sourceSkill?->slug,
                'source_skill_name' => $propagation->sourceSkill?->name,
                'source_project_id' => $propagation->sourceSkill?->project_id,
                'source_project_name' => $propagation->sourceSkill?->project?->name,
                'status' => $propagation->status,
                'resolved_at' => $propagation->resolved_at?->toIso8601String(),
            ],
        ]);
    }
}
