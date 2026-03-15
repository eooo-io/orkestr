<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\Skill;
use App\Services\ContentReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContentReviewController extends Controller
{
    public function __construct(
        private ContentReviewService $reviewService,
    ) {}

    /**
     * POST /api/skills/{skill}/review — review a skill for security risks.
     */
    public function reviewSkill(Skill $skill, Request $request): JsonResponse
    {
        $model = $request->input('model', 'claude-sonnet-4-6');
        $result = $this->reviewService->reviewSkill($skill, $model);

        return response()->json($result);
    }

    /**
     * POST /api/agents/{agent}/review — review an agent for security risks.
     */
    public function reviewAgent(Agent $agent, Request $request): JsonResponse
    {
        $model = $request->input('model', 'claude-sonnet-4-6');
        $result = $this->reviewService->reviewAgent($agent, $model);

        return response()->json($result);
    }
}
