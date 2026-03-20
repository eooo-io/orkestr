<?php

namespace App\Http\Controllers;

use App\Models\KnowledgeGraphNode;
use App\Models\Project;
use App\Services\Memory\KnowledgeGraphService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KnowledgeGraphController extends Controller
{
    public function __construct(
        protected KnowledgeGraphService $graphService,
    ) {}

    /**
     * Get the full knowledge graph for a project.
     */
    public function show(Project $project): JsonResponse
    {
        $graph = $this->graphService->getGraph($project->id);

        return response()->json(['data' => $graph]);
    }

    /**
     * Add a node to the graph.
     */
    public function storeNode(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'entity_type' => 'required|string|max:100',
            'entity_name' => 'required|string|max:255',
            'properties' => 'nullable|array',
            'pool_id' => 'nullable|integer|exists:shared_memory_pools,id',
        ]);

        $node = $this->graphService->addNode(
            projectId: $project->id,
            entityType: $validated['entity_type'],
            entityName: $validated['entity_name'],
            properties: $validated['properties'] ?? null,
            poolId: $validated['pool_id'] ?? null,
        );

        return response()->json(['data' => $node], 201);
    }

    /**
     * Add an edge between two nodes.
     */
    public function storeEdge(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'source_node_id' => 'required|integer|exists:knowledge_graph_nodes,id',
            'target_node_id' => 'required|integer|exists:knowledge_graph_nodes,id',
            'relationship' => 'required|string|max:255',
            'properties' => 'nullable|array',
            'weight' => 'nullable|numeric|min:0|max:1',
        ]);

        $edge = $this->graphService->addEdge(
            sourceId: $validated['source_node_id'],
            targetId: $validated['target_node_id'],
            relationship: $validated['relationship'],
            properties: $validated['properties'] ?? null,
            weight: $validated['weight'] ?? 1.0,
        );

        return response()->json(['data' => $edge], 201);
    }

    /**
     * Query subgraph by entity name/type within N hops.
     */
    public function query(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'entity_name' => 'nullable|string|max:255',
            'entity_type' => 'nullable|string|max:100',
            'hops' => 'nullable|integer|min:1|max:10',
        ]);

        $result = $this->graphService->query(
            projectId: $project->id,
            entityName: $validated['entity_name'] ?? null,
            entityType: $validated['entity_type'] ?? null,
            hops: $validated['hops'] ?? 2,
        );

        return response()->json(['data' => $result]);
    }

    /**
     * Delete a node and its edges.
     */
    public function destroyNode(KnowledgeGraphNode $node): JsonResponse
    {
        $this->graphService->removeNode($node->id);

        return response()->json(['message' => 'Node deleted']);
    }
}
