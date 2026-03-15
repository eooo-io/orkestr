<?php

namespace App\Http\Controllers;

use App\Models\Skill;
use App\Services\SkillOwnershipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SkillOwnershipController extends Controller
{
    public function __construct(
        private SkillOwnershipService $ownershipService,
    ) {}

    /**
     * GET /api/skills/{skill}/ownership
     */
    public function show(Skill $skill): JsonResponse
    {
        return response()->json([
            'skill_id' => $skill->id,
            'owner_id' => $skill->owner_id,
            'codeowners' => $skill->codeowners,
            'owners' => $this->ownershipService->getOwners($skill),
        ]);
    }

    /**
     * PUT /api/skills/{skill}/ownership
     */
    public function update(Skill $skill, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'owner_id' => 'nullable|integer|exists:users,id',
            'codeowners' => 'nullable|array',
            'codeowners.*.email' => 'required_with:codeowners|email',
            'codeowners.*.pattern' => 'nullable|string',
            'codeowners.*.name' => 'nullable|string',
        ]);

        $skill->update([
            'owner_id' => $validated['owner_id'] ?? $skill->owner_id,
            'codeowners' => $validated['codeowners'] ?? $skill->codeowners,
        ]);

        return response()->json([
            'skill_id' => $skill->id,
            'owner_id' => $skill->owner_id,
            'codeowners' => $skill->codeowners,
            'owners' => $this->ownershipService->getOwners($skill),
        ]);
    }
}
