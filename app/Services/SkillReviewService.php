<?php

namespace App\Services;

use App\Models\Skill;
use App\Models\SkillReview;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class SkillReviewService
{
    public function __construct(
        private NotificationService $notificationService,
    ) {}

    /**
     * Submit a skill for review.
     */
    public function submit(Skill $skill, User $submitter, ?int $skillVersionId = null, ?string $comments = null): SkillReview
    {
        $review = SkillReview::create([
            'skill_id' => $skill->id,
            'skill_version_id' => $skillVersionId,
            'status' => 'pending',
            'comments' => $comments,
            'submitted_by' => $submitter->id,
        ]);

        // Notify owners if set
        if ($skill->owner_id) {
            $this->notificationService->notify(
                $skill->owner_id,
                'skill_review_submitted',
                "Review requested: {$skill->name}",
                "A review has been submitted for skill '{$skill->name}'.",
                ['skill_id' => $skill->id, 'review_id' => $review->id]
            );
        }

        return $review;
    }

    /**
     * Approve a review.
     */
    public function approve(SkillReview $review, User $reviewer, ?string $comments = null): SkillReview
    {
        $review->update([
            'status' => 'approved',
            'reviewer_id' => $reviewer->id,
            'comments' => $comments ?? $review->comments,
        ]);

        $this->notificationService->notify(
            $review->submitted_by,
            'skill_review_approved',
            "Skill approved: {$review->skill->name}",
            "Your skill '{$review->skill->name}' has been approved.",
            ['skill_id' => $review->skill_id, 'review_id' => $review->id]
        );

        return $review;
    }

    /**
     * Reject a review.
     */
    public function reject(SkillReview $review, User $reviewer, ?string $comments = null): SkillReview
    {
        $review->update([
            'status' => 'rejected',
            'reviewer_id' => $reviewer->id,
            'comments' => $comments ?? $review->comments,
        ]);

        $this->notificationService->notify(
            $review->submitted_by,
            'skill_review_rejected',
            "Skill rejected: {$review->skill->name}",
            "Your skill '{$review->skill->name}' has been rejected." . ($comments ? " Reason: {$comments}" : ''),
            ['skill_id' => $review->skill_id, 'review_id' => $review->id]
        );

        return $review;
    }

    /**
     * Get pending reviews, optionally for a specific skill.
     */
    public function pendingReviews(?int $skillId = null): Collection
    {
        $query = SkillReview::pending()->with(['skill', 'submitter']);

        if ($skillId) {
            $query->where('skill_id', $skillId);
        }

        return $query->orderByDesc('created_at')->get();
    }
}
