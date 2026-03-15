<?php

namespace App\Http\Controllers;

use App\Models\Skill;
use App\Services\SkillAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SkillAnalyticsController extends Controller
{
    public function __construct(
        private SkillAnalyticsService $analyticsService,
    ) {}

    /**
     * GET /api/skills/{skill}/analytics
     */
    public function show(Skill $skill, Request $request): JsonResponse
    {
        $stats = $this->analyticsService->getSkillStats(
            $skill->id,
            $request->query('from'),
            $request->query('to'),
        );

        return response()->json($stats);
    }

    /**
     * GET /api/analytics/top-skills
     */
    public function topSkills(Request $request): JsonResponse
    {
        $limit = $request->integer('limit', 10);
        $orgId = $request->query('organization_id');

        $topSkills = $this->analyticsService->getTopSkills($limit, $orgId ? (int) $orgId : null);

        return response()->json($topSkills);
    }

    /**
     * GET /api/analytics/trends
     */
    public function trends(Request $request): JsonResponse
    {
        $days = $request->integer('days', 30);
        $orgId = $request->query('organization_id');

        $trends = $this->analyticsService->getTrends($days, $orgId ? (int) $orgId : null);

        return response()->json($trends);
    }
}
