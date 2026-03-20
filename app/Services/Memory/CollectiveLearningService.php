<?php

namespace App\Services\Memory;

use App\Models\SharedMemoryPool;
use Illuminate\Support\Str;

class CollectiveLearningService
{
    private const POOL_SLUG = '_collective_learning';

    public function __construct(
        protected SharedMemoryService $sharedMemory,
    ) {}

    /**
     * Record the outcome of an agent task for collective learning.
     */
    public function recordOutcome(
        int $agentId,
        int $projectId,
        string $taskType,
        string $strategy,
        bool $success,
        array $metadata = [],
    ): void {
        $pool = $this->getOrCreatePool($projectId);

        $key = "outcome:" . Str::slug($taskType) . ":" . Str::slug($strategy) . ":" . now()->timestamp;

        $content = [
            'task_type' => $taskType,
            'strategy' => $strategy,
            'success' => $success,
            'recorded_at' => now()->toIso8601String(),
            ...$metadata,
        ];

        $this->sharedMemory->remember(
            poolId: $pool->id,
            agentId: $agentId,
            key: $key,
            content: $content,
            tags: ['collective_learning', $taskType, $success ? 'success' : 'failure'],
            confidence: $success ? 0.90 : 0.50,
        );
    }

    /**
     * Recommend strategies for a given task type based on collective learning.
     * Returns ranked strategies by success rate with agent attribution.
     */
    public function recommend(int $projectId, string $taskType): array
    {
        $pool = SharedMemoryPool::where('project_id', $projectId)
            ->where('slug', self::POOL_SLUG)
            ->first();

        if (! $pool) {
            return [];
        }

        // Get all entries for this task type
        $entries = $pool->entries()
            ->active()
            ->where('content', 'like', "%\"task_type\":\"{$taskType}\"%")
            ->get();

        if ($entries->isEmpty()) {
            // Try LIKE fallback on task type string
            $entries = $pool->entries()
                ->active()
                ->where('content', 'like', "%{$taskType}%")
                ->get();
        }

        // Aggregate by strategy
        $strategies = [];
        foreach ($entries as $entry) {
            $content = $entry->content;
            if (! is_array($content) || ($content['task_type'] ?? '') !== $taskType) {
                continue;
            }

            $strategy = $content['strategy'] ?? 'unknown';
            if (! isset($strategies[$strategy])) {
                $strategies[$strategy] = [
                    'strategy' => $strategy,
                    'total' => 0,
                    'successes' => 0,
                    'failures' => 0,
                    'agents' => [],
                    'last_used' => null,
                ];
            }

            $strategies[$strategy]['total']++;
            if ($content['success'] ?? false) {
                $strategies[$strategy]['successes']++;
            } else {
                $strategies[$strategy]['failures']++;
            }

            // Track contributing agents
            if ($entry->contributed_by_agent_id) {
                $strategies[$strategy]['agents'][$entry->contributed_by_agent_id] =
                    ($strategies[$strategy]['agents'][$entry->contributed_by_agent_id] ?? 0) + 1;
            }

            $recordedAt = $content['recorded_at'] ?? $entry->created_at;
            if (! $strategies[$strategy]['last_used'] || $recordedAt > $strategies[$strategy]['last_used']) {
                $strategies[$strategy]['last_used'] = $recordedAt;
            }
        }

        // Calculate success rates and sort
        $ranked = collect($strategies)->map(function ($s) {
            $s['success_rate'] = $s['total'] > 0 ? round($s['successes'] / $s['total'], 2) : 0;
            $s['agents'] = collect($s['agents'])->map(fn ($count, $id) => [
                'agent_id' => (int) $id,
                'uses' => $count,
            ])->values()->all();

            return $s;
        })->sortByDesc('success_rate')->values()->all();

        return $ranked;
    }

    /**
     * Get or create the collective learning pool for a project.
     */
    private function getOrCreatePool(int $projectId): SharedMemoryPool
    {
        return SharedMemoryPool::firstOrCreate(
            [
                'project_id' => $projectId,
                'slug' => self::POOL_SLUG,
            ],
            [
                'name' => 'Collective Learning',
                'description' => 'Automatically managed pool for recording agent task outcomes and strategy effectiveness.',
                'access_policy' => 'open',
            ]
        );
    }
}
