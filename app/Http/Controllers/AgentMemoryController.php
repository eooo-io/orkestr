<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\AgentMemory;
use App\Models\AgentConversation;
use App\Models\Project;
use App\Services\Execution\AgentMemoryService as LegacyMemoryService;
use App\Services\Memory\AgentMemoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentMemoryController extends Controller
{
    public function __construct(
        private LegacyMemoryService $legacyService,
        private AgentMemoryService $memoryService,
    ) {}

    /**
     * GET /api/projects/{project}/agents/{agent}/memories
     *
     * List memories (paginated).
     */
    public function index(Request $request, Project $project, Agent $agent): JsonResponse
    {
        $perPage = (int) ($request->query('per_page', 20));
        $memories = $this->memoryService->listAll($agent->id, $project->id, $perPage);

        return response()->json([
            'data' => $memories->items(),
            'meta' => [
                'current_page' => $memories->currentPage(),
                'last_page' => $memories->lastPage(),
                'per_page' => $memories->perPage(),
                'total' => $memories->total(),
            ],
        ]);
    }

    /**
     * POST /api/projects/{project}/agents/{agent}/memories
     *
     * Remember — store or upsert a memory.
     */
    public function store(Request $request, Project $project, Agent $agent): JsonResponse
    {
        $validated = $request->validate([
            'key' => 'required|string|max:255',
            'content' => 'required|string',
            'metadata' => 'nullable|array',
        ]);

        $memory = $this->memoryService->remember(
            agentId: $agent->id,
            projectId: $project->id,
            key: $validated['key'],
            content: $validated['content'],
            metadata: $validated['metadata'] ?? null,
        );

        return response()->json(['data' => $memory], 201);
    }

    /**
     * GET /api/projects/{project}/agents/{agent}/memories/recall?q={query}
     *
     * Semantic search / recall.
     */
    public function recall(Request $request, Project $project, Agent $agent): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:1',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $results = $this->memoryService->recall(
            agentId: $agent->id,
            projectId: $project->id,
            query: $request->query('q'),
            limit: (int) ($request->query('limit', 5)),
        );

        return response()->json(['data' => $results->values()]);
    }

    /**
     * PUT /api/agent-memories/{id}
     *
     * Update a memory's content.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'content' => 'required|string',
        ]);

        $memory = $this->memoryService->update($id, $validated['content']);

        return response()->json(['data' => $memory]);
    }

    /**
     * DELETE /api/agent-memories/{id}
     *
     * Forget a single memory.
     */
    public function destroy(AgentMemory $agentMemory): JsonResponse
    {
        $agentMemory->delete();

        return response()->json(null, 204);
    }

    /**
     * DELETE /api/projects/{project}/agents/{agent}/memories
     *
     * Clear all memories (or by type).
     */
    public function clear(Request $request, Project $project, Agent $agent): JsonResponse
    {
        $type = $request->query('type');
        if ($type) {
            $deleted = $this->legacyService->clearType($agent, $project, $type);
        } else {
            $deleted = AgentMemory::forAgent($agent->id, $project->id)->delete();
        }

        return response()->json(['deleted' => $deleted]);
    }

    /**
     * GET /api/projects/{project}/agents/{agent}/conversations
     */
    public function conversations(Project $project, Agent $agent): JsonResponse
    {
        $conversations = $this->legacyService->getConversations($agent, $project);

        return response()->json(['conversations' => $conversations]);
    }
}
