<?php

namespace App\Http\Controllers;

use App\Models\Skill;
use App\Services\SkillInheritanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SkillInheritanceController extends Controller
{
    public function __construct(
        private SkillInheritanceService $inheritanceService,
    ) {}

    /**
     * GET /api/skills/{skill}/resolve — resolve full inherited config.
     */
    public function resolve(Skill $skill): JsonResponse
    {
        $resolved = $this->inheritanceService->resolve($skill);

        return response()->json($resolved);
    }

    /**
     * GET /api/skills/{skill}/children — get skills extending this one.
     */
    public function children(Skill $skill): JsonResponse
    {
        $children = $this->inheritanceService->getChildren($skill);

        return response()->json($children);
    }

    /**
     * PUT /api/skills/{skill}/inheritance — set parent skill.
     */
    public function update(Skill $skill, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'extends_skill_id' => 'nullable|integer|exists:skills,id',
            'override_sections' => 'nullable|array',
        ]);

        // Prevent self-inheritance
        if (isset($validated['extends_skill_id']) && $validated['extends_skill_id'] === $skill->id) {
            return response()->json(['error' => 'A skill cannot extend itself.'], 422);
        }

        $skill->update($validated);

        return response()->json([
            'skill_id' => $skill->id,
            'extends_skill_id' => $skill->extends_skill_id,
            'override_sections' => $skill->override_sections,
        ]);
    }
}
