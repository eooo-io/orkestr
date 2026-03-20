<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\Project;
use App\Models\SharedMemoryPool;
use App\Services\Memory\SharedMemoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SharedMemoryController extends Controller
{
    public function __construct(
        protected SharedMemoryService $memoryService,
    ) {}

    /**
     * List pools for a project.
     */
    public function index(Project $project): JsonResponse
    {
        $pools = $project->sharedMemoryPools()
            ->withCount(['entries', 'agents'])
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $pools]);
    }

    /**
     * Create a new shared memory pool.
     */
    public function store(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'access_policy' => 'sometimes|string|in:open,explicit,role_based',
            'retention_days' => 'nullable|integer|min:1|max:3650',
        ]);

        $pool = $project->sharedMemoryPools()->create($validated);
        $pool->loadCount(['entries', 'agents']);

        return response()->json(['data' => $pool], 201);
    }

    /**
     * Show pool detail with stats.
     */
    public function show(SharedMemoryPool $pool): JsonResponse
    {
        $pool->loadCount(['entries', 'agents']);
        $pool->load('agents:id,name,slug,icon');

        return response()->json([
            'data' => [
                ...$pool->toArray(),
                'stats' => [
                    'entry_count' => $pool->entries_count,
                    'agent_count' => $pool->agents_count,
                    'avg_confidence' => round((float) $pool->entries()->avg('confidence'), 2),
                    'oldest_entry' => $pool->entries()->min('created_at'),
                    'newest_entry' => $pool->entries()->max('created_at'),
                ],
            ],
        ]);
    }

    /**
     * Update a pool.
     */
    public function update(Request $request, SharedMemoryPool $pool): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'access_policy' => 'sometimes|string|in:open,explicit,role_based',
            'retention_days' => 'nullable|integer|min:1|max:3650',
        ]);

        $pool->update($validated);
        $pool->loadCount(['entries', 'agents']);

        return response()->json(['data' => $pool]);
    }

    /**
     * Delete a pool and all its entries.
     */
    public function destroy(SharedMemoryPool $pool): JsonResponse
    {
        $pool->delete();

        return response()->json(['message' => 'Pool deleted']);
    }

    /**
     * Add an agent to a pool with a given access mode.
     */
    public function addAgent(Request $request, SharedMemoryPool $pool): JsonResponse
    {
        $validated = $request->validate([
            'agent_id' => 'required|integer|exists:agents,id',
            'access_mode' => 'sometimes|string|in:read,write,admin',
        ]);

        $pool->agents()->syncWithoutDetaching([
            $validated['agent_id'] => [
                'access_mode' => $validated['access_mode'] ?? 'write',
            ],
        ]);

        $pool->load('agents:id,name,slug,icon');

        return response()->json(['data' => $pool->agents]);
    }

    /**
     * Remove an agent from a pool.
     */
    public function removeAgent(SharedMemoryPool $pool, Agent $agent): JsonResponse
    {
        $pool->agents()->detach($agent->id);

        $pool->load('agents:id,name,slug,icon');

        return response()->json(['data' => $pool->agents]);
    }

    /**
     * Paginated entries for a pool with optional search.
     */
    public function entries(Request $request, SharedMemoryPool $pool): JsonResponse
    {
        $query = $pool->entries()->with('contributor:id,name,slug,icon');

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('key', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            });
        }

        if ($tag = $request->query('tag')) {
            $query->where('tags', 'like', "%\"{$tag}\"%");
        }

        $entries = $query->orderByDesc('updated_at')->paginate(20);

        return response()->json($entries);
    }

    /**
     * Store a new entry in a pool.
     */
    public function storeEntry(Request $request, SharedMemoryPool $pool): JsonResponse
    {
        $validated = $request->validate([
            'key' => 'required|string|max:255',
            'content' => 'required|array',
            'agent_id' => 'nullable|integer|exists:agents,id',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:100',
            'confidence' => 'nullable|numeric|min:0|max:1',
            'metadata' => 'nullable|array',
            'expires_at' => 'nullable|date',
        ]);

        $entry = $this->memoryService->remember(
            poolId: $pool->id,
            agentId: $validated['agent_id'] ?? null,
            key: $validated['key'],
            content: $validated['content'],
            tags: $validated['tags'] ?? null,
            confidence: $validated['confidence'] ?? 0.8,
        );

        if (isset($validated['metadata'])) {
            $entry->update(['metadata' => $validated['metadata']]);
        }
        if (isset($validated['expires_at'])) {
            $entry->update(['expires_at' => $validated['expires_at']]);
        }

        $entry->load('contributor:id,name,slug,icon');

        return response()->json(['data' => $entry], 201);
    }

    /**
     * Semantic search within a pool.
     */
    public function recall(Request $request, SharedMemoryPool $pool): JsonResponse
    {
        $validated = $request->validate([
            'query' => 'required|string|max:1000',
            'limit' => 'nullable|integer|min:1|max:50',
            'agent_id' => 'nullable|integer|exists:agents,id',
        ]);

        $results = $this->memoryService->recall(
            poolId: $pool->id,
            query: $validated['query'],
            limit: $validated['limit'] ?? 10,
            agentId: $validated['agent_id'] ?? null,
        );

        return response()->json(['data' => $results->values()]);
    }

    /**
     * Get contribution stats for a pool.
     */
    public function contributors(SharedMemoryPool $pool): JsonResponse
    {
        $stats = $this->memoryService->getContributors($pool->id);

        return response()->json(['data' => $stats]);
    }
}
