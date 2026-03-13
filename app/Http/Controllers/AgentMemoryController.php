<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\AgentMemory;
use App\Models\Project;
use App\Services\Execution\AgentMemoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentMemoryController extends Controller
{
    public function __construct(
        private AgentMemoryService $memoryService,
    ) {}

    /**
     * GET /api/projects/{project}/agents/{agent}/memories
     */
    public function index(Request $request, Project $project, Agent $agent): JsonResponse
    {
        $memories = $this->memoryService->retrieve(
            agent: $agent,
            project: $project,
            type: $request->query('type'),
            limit: (int) ($request->query('limit', 50)),
        );

        return response()->json(['memories' => $memories]);
    }

    /**
     * POST /api/projects/{project}/agents/{agent}/memories
     */
    public function store(Request $request, Project $project, Agent $agent): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string|in:working,long_term',
            'key' => 'nullable|string|max:255',
            'content' => 'required',
            'metadata' => 'nullable|array',
        ]);

        $memory = $this->memoryService->store(
            agent: $agent,
            project: $project,
            type: $validated['type'],
            content: $validated['content'],
            key: $validated['key'] ?? null,
            metadata: $validated['metadata'] ?? [],
        );

        return response()->json(['memory' => $memory], 201);
    }

    /**
     * DELETE /api/memories/{agentMemory}
     */
    public function destroy(AgentMemory $agentMemory): JsonResponse
    {
        $agentMemory->delete();

        return response()->json(null, 204);
    }

    /**
     * DELETE /api/projects/{project}/agents/{agent}/memories
     *
     * Clear all memories of a given type.
     */
    public function clear(Request $request, Project $project, Agent $agent): JsonResponse
    {
        $type = $request->query('type');
        if (! $type) {
            return response()->json(['error' => 'type parameter required'], 422);
        }

        $deleted = $this->memoryService->clearType($agent, $project, $type);

        return response()->json(['deleted' => $deleted]);
    }

    /**
     * GET /api/projects/{project}/agents/{agent}/conversations
     */
    public function conversations(Project $project, Agent $agent): JsonResponse
    {
        $conversations = $this->memoryService->getConversations($agent, $project);

        return response()->json(['conversations' => $conversations]);
    }
}
