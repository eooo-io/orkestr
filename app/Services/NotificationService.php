<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Skill;

class NotificationService
{
    /**
     * Send a notification to a user.
     */
    public function notify(
        int $userId,
        string $type,
        string $title,
        ?string $body = null,
        ?array $data = null,
        ?int $organizationId = null,
    ): Notification {
        return Notification::create([
            'user_id' => $userId,
            'organization_id' => $organizationId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'created_at' => now(),
        ]);
    }

    /**
     * Notify all owners of a skill.
     */
    public function notifyOwners(Skill $skill, string $type, string $title, ?string $body = null, ?array $data = null): array
    {
        $notified = [];

        if ($skill->owner_id) {
            $notified[] = $this->notify($skill->owner_id, $type, $title, $body, $data);
        }

        if ($skill->codeowners) {
            foreach ($skill->codeowners as $codeowner) {
                $user = \App\Models\User::where('email', $codeowner['email'] ?? '')->first();
                if ($user && $user->id !== $skill->owner_id) {
                    $notified[] = $this->notify($user->id, $type, $title, $body, $data);
                }
            }
        }

        return $notified;
    }

    /**
     * Mark a single notification as read.
     */
    public function markRead(int $notificationId): bool
    {
        return Notification::where('id', $notificationId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]) > 0;
    }

    /**
     * Mark all notifications for a user as read.
     */
    public function markAllRead(int $userId): int
    {
        return Notification::where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * Get unread notification count for a user.
     */
    public function unreadCount(int $userId): int
    {
        return Notification::where('user_id', $userId)
            ->whereNull('read_at')
            ->count();
    }
}
