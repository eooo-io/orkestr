<?php

namespace App\Http\Controllers;

use App\Models\Skill;
use App\Models\SkillReview;
use App\Services\SkillReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SkillReviewController extends Controller
{
    public function __construct(
        private SkillReviewService $reviewService,
    ) {}

    /**
     * GET /api/skills/{skill}/reviews — list reviews for a skill.
     */
    public function index(Skill $skill): JsonResponse
    {
        $reviews = $skill->reviews()
            ->with(['reviewer:id,name,email', 'submitter:id,name,email'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json($reviews);
    }

    /**
     * POST /api/skills/{skill}/reviews — submit a skill for review.
     */
    public function store(Skill $skill, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'skill_version_id' => 'nullable|integer',
            'comments' => 'nullable|string|max:5000',
        ]);

        $review = $this->reviewService->submit(
            $skill,
            $request->user(),
            $validated['skill_version_id'] ?? null,
            $validated['comments'] ?? null,
        );

        return response()->json($review, 201);
    }

    /**
     * POST /api/skill-reviews/{review}/approve
     */
    public function approve(SkillReview $review, Request $request): JsonResponse
    {
        $comments = $request->input('comments');

        $review = $this->reviewService->approve($review, $request->user(), $comments);

        return response()->json($review);
    }

    /**
     * POST /api/skill-reviews/{review}/reject
     */
    public function reject(SkillReview $review, Request $request): JsonResponse
    {
        $comments = $request->input('comments');

        $review = $this->reviewService->reject($review, $request->user(), $comments);

        return response()->json($review);
    }
}
