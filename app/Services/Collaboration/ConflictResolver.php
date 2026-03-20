<?php

namespace App\Services\Collaboration;

use App\Models\PresenceSession;
use App\Models\Skill;
use App\Models\SkillVersion;

class ConflictResolver
{
    /**
     * Check if another user is currently editing the given skill.
     *
     * Returns null if no conflict, or conflict info if someone else is editing.
     */
    public function checkConflict(int $skillId, int $userId): ?array
    {
        $cutoff = now()->subSeconds(15);

        $otherEditor = PresenceSession::where('resource_type', 'skill')
            ->where('resource_id', $skillId)
            ->where('user_id', '!=', $userId)
            ->where('last_seen_at', '>=', $cutoff)
            ->with('user:id,name')
            ->first();

        if (! $otherEditor) {
            return null;
        }

        return [
            'user_id' => $otherEditor->user_id,
            'user_name' => $otherEditor->user?->name ?? 'Unknown',
            'last_seen_at' => $otherEditor->last_seen_at->toIso8601String(),
        ];
    }

    /**
     * Resolve a conflict for a given skill using the specified strategy.
     *
     * Compares the current skill body with the latest version snapshot.
     *
     * Strategies:
     * - last_write_wins: the current body wins (no action needed)
     * - revert_to_version: restore from the latest version snapshot
     */
    public function resolveConflict(int $skillId, string $strategy = 'last_write_wins'): array
    {
        $skill = Skill::findOrFail($skillId);

        $latestVersion = SkillVersion::where('skill_id', $skillId)
            ->latest('version')
            ->first();

        $currentBody = $skill->body ?? '';
        $versionBody = $latestVersion?->body ?? '';
        $hasChanges = $currentBody !== $versionBody;

        if ($strategy === 'revert_to_version' && $latestVersion) {
            $skill->update([
                'body' => $latestVersion->body,
            ]);

            // Restore frontmatter fields if available
            if ($latestVersion->frontmatter) {
                $frontmatter = $latestVersion->frontmatter;
                $skill->update(array_filter([
                    'name' => $frontmatter['name'] ?? null,
                    'description' => $frontmatter['description'] ?? null,
                    'model' => $frontmatter['model'] ?? null,
                    'max_tokens' => $frontmatter['max_tokens'] ?? null,
                    'tags_list' => $frontmatter['tags'] ?? null,
                ]));
            }

            return [
                'strategy' => 'revert_to_version',
                'reverted_to_version' => $latestVersion->version,
                'had_changes' => $hasChanges,
                'skill_id' => $skillId,
            ];
        }

        return [
            'strategy' => 'last_write_wins',
            'had_changes' => $hasChanges,
            'current_body_length' => strlen($currentBody),
            'version_body_length' => strlen($versionBody),
            'latest_version' => $latestVersion?->version,
            'skill_id' => $skillId,
        ];
    }
}
