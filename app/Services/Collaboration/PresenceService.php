<?php

namespace App\Services\Collaboration;

use App\Models\PresenceSession;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Redis;

class PresenceService
{
    /**
     * Predefined avatar colors for presence indicators.
     */
    protected const COLORS = [
        '#FF5733', '#33FF57', '#3357FF', '#FF33A1', '#FFD133',
        '#33FFF5', '#A133FF', '#FF8C33', '#33FF8C', '#8C33FF',
    ];

    /**
     * Heartbeat: upsert presence for a user on a resource.
     * Stores in both DB (durable) and Redis (fast lookup with TTL).
     */
    public function heartbeat(
        int $userId,
        string $resourceType,
        int $resourceId,
        ?array $cursor = null,
        ?array $selection = null,
    ): PresenceSession {
        $color = self::COLORS[$userId % count(self::COLORS)];

        $session = PresenceSession::updateOrCreate(
            [
                'user_id' => $userId,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
            ],
            [
                'cursor_position' => $cursor,
                'selection' => $selection,
                'color' => $color,
                'last_seen_at' => now(),
                'organization_id' => auth()->user()?->current_organization_id,
            ],
        );

        // Store in Redis with 15s TTL for fast presence lookups
        $redisKey = "presence:{$resourceType}:{$resourceId}:{$userId}";
        $redisData = json_encode([
            'user_id' => $userId,
            'user_name' => $session->user?->name ?? 'Unknown',
            'cursor_position' => $cursor,
            'selection' => $selection,
            'color' => $color,
            'last_seen_at' => now()->toIso8601String(),
        ]);

        try {
            Redis::setex($redisKey, 15, $redisData);
        } catch (\Throwable) {
            // Redis unavailable — DB is still authoritative
        }

        return $session;
    }

    /**
     * Get all non-stale presence sessions for a resource.
     * Queries Redis first for speed, falls back to DB.
     */
    public function getPresence(string $resourceType, int $resourceId): Collection
    {
        // Try Redis first for fast lookups
        try {
            $pattern = "presence:{$resourceType}:{$resourceId}:*";
            $keys = Redis::keys($pattern);

            if (! empty($keys)) {
                $results = collect();
                foreach ($keys as $key) {
                    $data = Redis::get($key);
                    if ($data) {
                        $decoded = json_decode($data, true);
                        if ($decoded) {
                            $results->push($decoded);
                        }
                    }
                }

                if ($results->isNotEmpty()) {
                    return $results;
                }
            }
        } catch (\Throwable) {
            // Redis unavailable — fall through to DB
        }

        // Fallback: query DB for non-stale sessions
        $cutoff = now()->subSeconds(15);

        return PresenceSession::where('resource_type', $resourceType)
            ->where('resource_id', $resourceId)
            ->where('last_seen_at', '>=', $cutoff)
            ->with('user:id,name,email')
            ->get()
            ->map(fn (PresenceSession $s) => [
                'user_id' => $s->user_id,
                'user_name' => $s->user?->name ?? 'Unknown',
                'cursor_position' => $s->cursor_position,
                'selection' => $s->selection,
                'color' => $s->color,
                'last_seen_at' => $s->last_seen_at->toIso8601String(),
            ]);
    }

    /**
     * Remove a user's presence from a resource.
     */
    public function leave(int $userId, string $resourceType, int $resourceId): void
    {
        PresenceSession::where('user_id', $userId)
            ->where('resource_type', $resourceType)
            ->where('resource_id', $resourceId)
            ->delete();

        try {
            Redis::del("presence:{$resourceType}:{$resourceId}:{$userId}");
        } catch (\Throwable) {
            // Redis unavailable — DB delete is sufficient
        }
    }

    /**
     * Delete stale sessions older than 30 seconds.
     * Returns the number of sessions deleted.
     */
    public function cleanup(): int
    {
        $cutoff = now()->subSeconds(30);

        return PresenceSession::where('last_seen_at', '<', $cutoff)->delete();
    }
}
