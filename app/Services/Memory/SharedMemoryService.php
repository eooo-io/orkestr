<?php

namespace App\Services\Memory;

use App\Models\AgentMemory;
use App\Models\SharedMemoryEntry;
use App\Models\SharedMemoryPool;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SharedMemoryService
{
    public function __construct(
        protected EmbeddingService $embeddingService,
    ) {}

    /**
     * Store an entry in a shared memory pool.
     */
    public function remember(
        int $poolId,
        ?int $agentId,
        string $key,
        array $content,
        ?array $tags = null,
        float $confidence = 0.8,
    ): SharedMemoryEntry {
        $contentStr = is_string($content['value'] ?? null) ? $content['value'] : json_encode($content);
        $embedding = $this->embeddingService->embed($contentStr);

        // Upsert by pool + key
        $existing = SharedMemoryEntry::where('pool_id', $poolId)
            ->where('key', $key)
            ->first();

        if ($existing) {
            $existing->update([
                'content' => $content,
                'embedding' => $embedding,
                'contributed_by_agent_id' => $agentId ?? $existing->contributed_by_agent_id,
                'tags' => $tags ?? $existing->tags,
                'confidence' => $confidence,
            ]);

            return $existing->fresh();
        }

        return SharedMemoryEntry::create([
            'pool_id' => $poolId,
            'contributed_by_agent_id' => $agentId,
            'key' => $key,
            'content' => $content,
            'embedding' => $embedding,
            'tags' => $tags,
            'confidence' => $confidence,
        ]);
    }

    /**
     * Semantic recall from a shared memory pool.
     * Uses pgvector cosine similarity with LIKE fallback.
     */
    public function recall(int $poolId, string $query, int $limit = 10, ?int $agentId = null): Collection
    {
        if (AgentMemory::hasPgVector()) {
            return $this->recallVector($poolId, $query, $limit, $agentId);
        }

        return $this->recallLike($poolId, $query, $limit, $agentId);
    }

    /**
     * Delete an entry by key from a pool.
     */
    public function forget(int $poolId, string $key): bool
    {
        return SharedMemoryEntry::where('pool_id', $poolId)
            ->where('key', $key)
            ->delete() > 0;
    }

    /**
     * Merge duplicate entries in a pool.
     * Finds entries with the same key, keeps the most recent, deletes older ones.
     */
    public function merge(int $poolId): int
    {
        $duplicates = SharedMemoryEntry::where('pool_id', $poolId)
            ->select('key', DB::raw('COUNT(*) as cnt'))
            ->groupBy('key')
            ->having('cnt', '>', 1)
            ->pluck('key');

        $merged = 0;

        foreach ($duplicates as $key) {
            $entries = SharedMemoryEntry::where('pool_id', $poolId)
                ->where('key', $key)
                ->orderByDesc('updated_at')
                ->get();

            // Keep the first (most recent), delete the rest
            $keep = $entries->shift();
            $toDelete = $entries;

            // Merge tags from all entries
            $allTags = collect([$keep->tags ?? []])
                ->merge($toDelete->pluck('tags')->filter())
                ->flatten()
                ->unique()
                ->values()
                ->all();

            // Pick the highest confidence
            $maxConfidence = max(
                (float) $keep->confidence,
                ...$toDelete->pluck('confidence')->map(fn ($c) => (float) $c)->all()
            );

            $keep->update([
                'tags' => $allTags ?: null,
                'confidence' => $maxConfidence,
            ]);

            SharedMemoryEntry::whereIn('id', $toDelete->pluck('id'))->delete();
            $merged += $toDelete->count();
        }

        return $merged;
    }

    /**
     * Get contributor stats for a pool.
     */
    public function getContributors(int $poolId): array
    {
        $stats = SharedMemoryEntry::where('pool_id', $poolId)
            ->whereNotNull('contributed_by_agent_id')
            ->select(
                'contributed_by_agent_id',
                DB::raw('COUNT(*) as entry_count'),
                DB::raw('MAX(created_at) as last_contributed_at')
            )
            ->groupBy('contributed_by_agent_id')
            ->get();

        return $stats->map(function ($row) {
            $agent = \App\Models\Agent::select('id', 'name', 'slug', 'icon')->find($row->contributed_by_agent_id);

            return [
                'agent' => $agent,
                'entry_count' => (int) $row->entry_count,
                'last_contributed_at' => $row->last_contributed_at,
            ];
        })->all();
    }

    /**
     * Prune entries that have passed their expiration date.
     */
    public function pruneExpired(): int
    {
        return SharedMemoryEntry::whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->delete();
    }

    /**
     * Vector similarity search using pgvector cosine distance.
     */
    private function recallVector(int $poolId, string $query, int $limit, ?int $agentId): Collection
    {
        try {
            $queryEmbedding = $this->embeddingService->embed($query);
            $vectorStr = '[' . implode(',', $queryEmbedding) . ']';
            $connection = AgentMemory::resolveConnectionName() ?? config('database.default');

            $sql = "SELECT *, (embedding::vector <=> ?::vector) AS distance
                    FROM shared_memory_entries
                    WHERE pool_id = ?
                      AND (expires_at IS NULL OR expires_at > NOW())";
            $bindings = [$vectorStr, $poolId];

            if ($agentId !== null) {
                // Check if agent has access to this pool
                $pool = SharedMemoryPool::find($poolId);
                if ($pool && $pool->access_policy !== 'open') {
                    $hasAccess = DB::table('shared_memory_pool_agent')
                        ->where('shared_memory_pool_id', $poolId)
                        ->where('agent_id', $agentId)
                        ->exists();
                    if (! $hasAccess) {
                        return collect();
                    }
                }
            }

            $sql .= ' ORDER BY distance ASC LIMIT ?';
            $bindings[] = $limit;

            $results = DB::connection($connection)->select($sql, $bindings);

            return collect($results)->map(function ($row) {
                $entry = new SharedMemoryEntry;
                $entry->setRawAttributes((array) $row, true);
                $entry->exists = true;

                return $entry;
            });
        } catch (\Throwable $e) {
            Log::warning("Shared memory vector recall failed, falling back to LIKE: {$e->getMessage()}");

            return $this->recallLike($poolId, $query, $limit, $agentId);
        }
    }

    /**
     * Fallback LIKE-based search on content JSON.
     */
    private function recallLike(int $poolId, string $query, int $limit, ?int $agentId): Collection
    {
        $keywords = preg_split('/\s+/', trim($query));
        $keywords = array_filter($keywords, fn ($kw) => mb_strlen($kw) >= 2);

        $q = SharedMemoryEntry::where('pool_id', $poolId)->active();

        if ($agentId !== null) {
            $pool = SharedMemoryPool::find($poolId);
            if ($pool && $pool->access_policy !== 'open') {
                $hasAccess = DB::table('shared_memory_pool_agent')
                    ->where('shared_memory_pool_id', $poolId)
                    ->where('agent_id', $agentId)
                    ->exists();
                if (! $hasAccess) {
                    return collect();
                }
            }
        }

        if (! empty($keywords)) {
            $q->where(function ($builder) use ($keywords) {
                foreach ($keywords as $keyword) {
                    $builder->orWhere('content', 'like', "%{$keyword}%")
                        ->orWhere('key', 'like', "%{$keyword}%");
                }
            });
        }

        return $q->orderByDesc('confidence')->orderByDesc('updated_at')->limit($limit)->get();
    }
}
