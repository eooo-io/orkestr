<?php

namespace App\Services\Memory;

use App\Models\AgentMemory;
use App\Models\KnowledgeGraphEdge;
use App\Models\KnowledgeGraphNode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class KnowledgeGraphService
{
    public function __construct(
        protected EmbeddingService $embeddingService,
    ) {}

    /**
     * Add a node to the knowledge graph.
     */
    public function addNode(
        int $projectId,
        string $entityType,
        string $entityName,
        ?array $properties = null,
        ?int $poolId = null,
    ): KnowledgeGraphNode {
        $embedding = $this->embeddingService->embed("{$entityType}: {$entityName}");

        return KnowledgeGraphNode::create([
            'project_id' => $projectId,
            'pool_id' => $poolId,
            'entity_type' => $entityType,
            'entity_name' => $entityName,
            'properties' => $properties,
            'embedding' => $embedding,
        ]);
    }

    /**
     * Add an edge between two nodes.
     */
    public function addEdge(
        int $sourceId,
        int $targetId,
        string $relationship,
        ?array $properties = null,
        float $weight = 1.0,
    ): KnowledgeGraphEdge {
        return KnowledgeGraphEdge::create([
            'source_node_id' => $sourceId,
            'target_node_id' => $targetId,
            'relationship' => $relationship,
            'properties' => $properties,
            'weight' => $weight,
        ]);
    }

    /**
     * Query subgraph within N hops of a given entity.
     * Returns {nodes: [...], edges: [...]} for the matched subgraph.
     */
    public function query(int $projectId, ?string $entityName = null, ?string $entityType = null, int $hops = 2): array
    {
        // Find seed nodes
        $seedQuery = KnowledgeGraphNode::where('project_id', $projectId);
        if ($entityName !== null) {
            $seedQuery->where('entity_name', 'like', "%{$entityName}%");
        }
        if ($entityType !== null) {
            $seedQuery->where('entity_type', $entityType);
        }

        $seedNodes = $seedQuery->get();

        if ($seedNodes->isEmpty()) {
            return ['nodes' => [], 'edges' => []];
        }

        // Collect nodes via BFS traversal up to N hops
        $visitedNodeIds = $seedNodes->pluck('id')->toArray();
        $frontier = $visitedNodeIds;
        $allEdges = collect();

        for ($hop = 0; $hop < $hops && ! empty($frontier); $hop++) {
            // Get all edges connected to frontier nodes
            $edges = KnowledgeGraphEdge::whereIn('source_node_id', $frontier)
                ->orWhereIn('target_node_id', $frontier)
                ->get();

            $allEdges = $allEdges->merge($edges);

            // Discover new nodes
            $newFrontier = [];
            foreach ($edges as $edge) {
                if (! in_array($edge->source_node_id, $visitedNodeIds)) {
                    $visitedNodeIds[] = $edge->source_node_id;
                    $newFrontier[] = $edge->source_node_id;
                }
                if (! in_array($edge->target_node_id, $visitedNodeIds)) {
                    $visitedNodeIds[] = $edge->target_node_id;
                    $newFrontier[] = $edge->target_node_id;
                }
            }

            $frontier = $newFrontier;
        }

        $nodes = KnowledgeGraphNode::whereIn('id', $visitedNodeIds)->get();
        $edges = $allEdges->unique('id')->values();

        return [
            'nodes' => $nodes->map(fn ($n) => [
                'id' => $n->id,
                'uuid' => $n->uuid,
                'entity_type' => $n->entity_type,
                'entity_name' => $n->entity_name,
                'properties' => $n->properties,
                'pool_id' => $n->pool_id,
                'created_at' => $n->created_at,
            ])->values()->all(),
            'edges' => $edges->map(fn ($e) => [
                'id' => $e->id,
                'source_node_id' => $e->source_node_id,
                'target_node_id' => $e->target_node_id,
                'relationship' => $e->relationship,
                'properties' => $e->properties,
                'weight' => (float) $e->weight,
                'created_at' => $e->created_at,
            ])->values()->all(),
        ];
    }

    /**
     * Semantic search on node embeddings.
     */
    public function findRelated(int $projectId, string $query, int $limit = 10): Collection
    {
        if (AgentMemory::hasPgVector()) {
            return $this->findRelatedVector($projectId, $query, $limit);
        }

        return $this->findRelatedLike($projectId, $query, $limit);
    }

    /**
     * Get the full graph for a project (for visualization).
     */
    public function getGraph(int $projectId): array
    {
        $nodes = KnowledgeGraphNode::where('project_id', $projectId)->get();
        $nodeIds = $nodes->pluck('id')->all();

        $edges = KnowledgeGraphEdge::whereIn('source_node_id', $nodeIds)
            ->orWhereIn('target_node_id', $nodeIds)
            ->get();

        return [
            'nodes' => $nodes->map(fn ($n) => [
                'id' => $n->id,
                'uuid' => $n->uuid,
                'entity_type' => $n->entity_type,
                'entity_name' => $n->entity_name,
                'properties' => $n->properties,
                'pool_id' => $n->pool_id,
                'created_at' => $n->created_at,
            ])->values()->all(),
            'edges' => $edges->map(fn ($e) => [
                'id' => $e->id,
                'source_node_id' => $e->source_node_id,
                'target_node_id' => $e->target_node_id,
                'relationship' => $e->relationship,
                'properties' => $e->properties,
                'weight' => (float) $e->weight,
                'created_at' => $e->created_at,
            ])->values()->all(),
        ];
    }

    /**
     * Remove a node and cascade delete its edges.
     */
    public function removeNode(int $nodeId): void
    {
        $node = KnowledgeGraphNode::findOrFail($nodeId);
        // Edges cascade via FK, but explicitly delete for non-FK DBs
        KnowledgeGraphEdge::where('source_node_id', $nodeId)
            ->orWhere('target_node_id', $nodeId)
            ->delete();
        $node->delete();
    }

    /**
     * Vector similarity search for related nodes.
     */
    private function findRelatedVector(int $projectId, string $query, int $limit): Collection
    {
        try {
            $queryEmbedding = $this->embeddingService->embed($query);
            $vectorStr = '[' . implode(',', $queryEmbedding) . ']';
            $connection = AgentMemory::resolveConnectionName() ?? config('database.default');

            $results = DB::connection($connection)->select(
                "SELECT *, (embedding::vector <=> ?::vector) AS distance
                 FROM knowledge_graph_nodes
                 WHERE project_id = ?
                 ORDER BY distance ASC
                 LIMIT ?",
                [$vectorStr, $projectId, $limit]
            );

            return collect($results)->map(function ($row) {
                $node = new KnowledgeGraphNode;
                $node->setRawAttributes((array) $row, true);
                $node->exists = true;

                return $node;
            });
        } catch (\Throwable $e) {
            Log::warning("Knowledge graph vector search failed, falling back to LIKE: {$e->getMessage()}");

            return $this->findRelatedLike($projectId, $query, $limit);
        }
    }

    /**
     * Fallback LIKE-based search on entity name/type.
     */
    private function findRelatedLike(int $projectId, string $query, int $limit): Collection
    {
        $keywords = preg_split('/\s+/', trim($query));
        $keywords = array_filter($keywords, fn ($kw) => mb_strlen($kw) >= 2);

        $q = KnowledgeGraphNode::where('project_id', $projectId);

        if (! empty($keywords)) {
            $q->where(function ($builder) use ($keywords) {
                foreach ($keywords as $keyword) {
                    $builder->orWhere('entity_name', 'like', "%{$keyword}%")
                        ->orWhere('entity_type', 'like', "%{$keyword}%");
                }
            });
        }

        return $q->orderByDesc('updated_at')->limit($limit)->get();
    }
}
