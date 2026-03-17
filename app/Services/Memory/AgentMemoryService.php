<?php

namespace App\Services\Memory;

use App\Models\AgentMemory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AgentMemoryService
{
    public function __construct(
        protected EmbeddingService $embeddingService,
    ) {}

    /**
     * Store or update a memory (upsert by key for same agent+project).
     */
    public function remember(int $agentId, int $projectId, string $key, string $content, ?array $metadata = null): AgentMemory
    {
        $embedding = $this->embeddingService->embed($content);

        $existing = AgentMemory::forAgent($agentId, $projectId)
            ->where('key', $key)
            ->first();

        if ($existing) {
            $existing->update([
                'content' => ['value' => $content],
                'embedding' => $embedding,
                'metadata' => $metadata ?? $existing->metadata,
            ]);

            return $existing->fresh();
        }

        return AgentMemory::create([
            'agent_id' => $agentId,
            'project_id' => $projectId,
            'type' => 'long_term',
            'key' => $key,
            'content' => ['value' => $content],
            'embedding' => $embedding,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Semantic recall: find memories relevant to a query.
     *
     * Uses pgvector cosine distance if available, LIKE fallback otherwise.
     */
    public function recall(int $agentId, int $projectId, string $query, int $limit = 5): Collection
    {
        // Try vector similarity search if pgvector is available
        if (AgentMemory::hasPgVector()) {
            return $this->recallVector($agentId, $projectId, $query, $limit);
        }

        // Fallback: LIKE search on content
        return $this->recallLike($agentId, $projectId, $query, $limit);
    }

    /**
     * Update a memory's content and re-embed.
     */
    public function update(int $memoryId, string $content): AgentMemory
    {
        $memory = AgentMemory::findOrFail($memoryId);
        $embedding = $this->embeddingService->embed($content);

        $memory->update([
            'content' => ['value' => $content],
            'embedding' => $embedding,
        ]);

        return $memory->fresh();
    }

    /**
     * Delete a memory by key for a specific agent+project.
     */
    public function forget(int $agentId, int $projectId, string $key): bool
    {
        return AgentMemory::forAgent($agentId, $projectId)
            ->where('key', $key)
            ->delete() > 0;
    }

    /**
     * List all memories for an agent+project (paginated).
     */
    public function listAll(int $agentId, int $projectId, int $perPage = 20): LengthAwarePaginator
    {
        return AgentMemory::forAgent($agentId, $projectId)
            ->active()
            ->orderByDesc('updated_at')
            ->paginate($perPage);
    }

    /**
     * Vector similarity search using pgvector's cosine distance operator.
     */
    private function recallVector(int $agentId, int $projectId, string $query, int $limit): Collection
    {
        try {
            $queryEmbedding = $this->embeddingService->embed($query);
            $vectorStr = '[' . implode(',', $queryEmbedding) . ']';
            $connection = AgentMemory::resolveConnectionName() ?? config('database.default');

            $results = DB::connection($connection)
                ->select(
                    "SELECT *, (embedding::vector <=> ?::vector) AS distance
                     FROM agent_memories
                     WHERE agent_id = ? AND project_id = ?
                       AND (expires_at IS NULL OR expires_at > NOW())
                     ORDER BY distance ASC
                     LIMIT ?",
                    [$vectorStr, $agentId, $projectId, $limit]
                );

            // Hydrate into models
            return collect($results)->map(function ($row) {
                $memory = new AgentMemory;
                $memory->setRawAttributes((array) $row, true);
                $memory->exists = true;

                return $memory;
            });
        } catch (\Throwable $e) {
            Log::warning("Vector recall failed, falling back to LIKE: {$e->getMessage()}");

            return $this->recallLike($agentId, $projectId, $query, $limit);
        }
    }

    /**
     * Fallback LIKE-based search on content JSON.
     */
    private function recallLike(int $agentId, int $projectId, string $query, int $limit): Collection
    {
        // Split query into keywords for broader matching
        $keywords = preg_split('/\s+/', trim($query));
        $keywords = array_filter($keywords, fn ($kw) => mb_strlen($kw) >= 2);

        $q = AgentMemory::forAgent($agentId, $projectId)->active();

        if (! empty($keywords)) {
            $q->where(function ($builder) use ($keywords) {
                foreach ($keywords as $keyword) {
                    $builder->orWhere('content', 'like', "%{$keyword}%")
                        ->orWhere('key', 'like', "%{$keyword}%");
                }
            });
        }

        return $q->orderByDesc('updated_at')->limit($limit)->get();
    }
}
