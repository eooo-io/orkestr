<?php

namespace App\Services;

use App\Models\Skill;
use App\Models\User;

class SkillOwnershipService
{
    /**
     * Get all owners (primary owner + codeowners) for a skill.
     */
    public function getOwners(Skill $skill): array
    {
        $owners = [];

        if ($skill->owner_id) {
            $owner = User::find($skill->owner_id);
            if ($owner) {
                $owners[] = [
                    'user_id' => $owner->id,
                    'name' => $owner->name,
                    'email' => $owner->email,
                    'role' => 'owner',
                ];
            }
        }

        if ($skill->codeowners) {
            foreach ($skill->codeowners as $codeowner) {
                $user = User::where('email', $codeowner['email'] ?? '')->first();
                $owners[] = [
                    'email' => $codeowner['email'] ?? null,
                    'pattern' => $codeowner['pattern'] ?? '*',
                    'user_id' => $user?->id,
                    'name' => $user?->name ?? $codeowner['name'] ?? null,
                    'role' => 'codeowner',
                ];
            }
        }

        return $owners;
    }

    /**
     * Check if a user is an owner or codeowner of a skill.
     */
    public function isOwner(Skill $skill, User $user): bool
    {
        if ($skill->owner_id === $user->id) {
            return true;
        }

        if ($skill->codeowners) {
            foreach ($skill->codeowners as $codeowner) {
                if (($codeowner['email'] ?? null) === $user->email) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Auto-assign a reviewer based on ownership. Returns user ID or null.
     */
    public function autoAssignReviewer(Skill $skill, ?User $excludeUser = null): ?int
    {
        // Prefer the primary owner
        if ($skill->owner_id && $skill->owner_id !== $excludeUser?->id) {
            return $skill->owner_id;
        }

        // Fall back to first codeowner
        if ($skill->codeowners) {
            foreach ($skill->codeowners as $codeowner) {
                $user = User::where('email', $codeowner['email'] ?? '')->first();
                if ($user && $user->id !== $excludeUser?->id) {
                    return $user->id;
                }
            }
        }

        return null;
    }
}
