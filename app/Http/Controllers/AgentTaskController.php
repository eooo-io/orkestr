<?php

namespace App\Http\Controllers;

use App\Models\AgentTask;
use App\Models\Project;
use App\Services\OrchestratorRoutingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentTaskController extends Controller
{
    /**
     * GET /api/projects/{project}/tasks
     * List tasks with optional filters.
     */
    public function index(Project $project, Request $request): JsonResponse
    {
        $query = $project->tasks()
            ->with(['agent:id,name,slug,icon,persona', 'childTasks'])
            ->orderByRaw("CASE priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 WHEN 'low' THEN 4 ELSE 5 END")
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('agent_id')) {
            $query->where('agent_id', $request->input('agent_id'));
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->input('priority'));
        }

        $tasks = $query->get();

        return response()->json(['data' => $tasks]);
    }

    /**
     * POST /api/projects/{project}/tasks
     * Create a new task.
     */
    public function store(Project $project, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'nullable|string|in:low,medium,high,critical',
            'agent_id' => 'nullable|integer|exists:agents,id',
            'parent_task_id' => 'nullable|integer|exists:agent_tasks,id',
            'input_data' => 'nullable|array',
        ]);

        $task = $project->tasks()->create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'priority' => $validated['priority'] ?? 'medium',
            'agent_id' => $validated['agent_id'] ?? null,
            'parent_task_id' => $validated['parent_task_id'] ?? null,
            'input_data' => $validated['input_data'] ?? null,
            'assigned_by_user_id' => $request->user()?->id,
            'status' => isset($validated['agent_id']) ? 'assigned' : 'pending',
        ]);

        $task->load('agent:id,name,slug,icon,persona');

        return response()->json(['data' => $task], 201);
    }

    /**
     * PUT /api/tasks/{task}
     * Update a task.
     */
    public function update(AgentTask $task, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'nullable|string|in:low,medium,high,critical',
            'status' => 'nullable|string|in:pending,assigned,running,completed,failed,cancelled',
            'input_data' => 'nullable|array',
            'output_data' => 'nullable|array',
        ]);

        $task->update($validated);
        $task->load('agent:id,name,slug,icon,persona');

        return response()->json(['data' => $task]);
    }

    /**
     * POST /api/tasks/{task}/assign
     * Assign a task to an agent.
     */
    public function assign(AgentTask $task, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'agent_id' => 'required|integer|exists:agents,id',
        ]);

        $task->update([
            'agent_id' => $validated['agent_id'],
            'status' => 'assigned',
        ]);

        $task->load('agent:id,name,slug,icon,persona');

        return response()->json(['data' => $task]);
    }

    /**
     * POST /api/tasks/{task}/run
     * Dispatch execution immediately.
     */
    public function run(AgentTask $task): JsonResponse
    {
        // If no agent is assigned, try orchestrator routing
        if (! $task->agent_id) {
            $router = app(OrchestratorRoutingService::class);
            $router->decompose($task);

            $task->refresh();
            $task->load(['agent:id,name,slug,icon,persona', 'childTasks']);

            return response()->json([
                'data' => $task,
                'message' => 'Task decomposed into subtasks by orchestrator.',
            ]);
        }

        // Mark as running
        $task->update([
            'status' => 'running',
            'started_at' => now(),
        ]);

        $task->load('agent:id,name,slug,icon,persona');

        return response()->json([
            'data' => $task,
            'message' => 'Task dispatched for execution.',
        ]);
    }

    /**
     * DELETE /api/tasks/{task}
     * Cancel or delete a task.
     */
    public function destroy(AgentTask $task): JsonResponse
    {
        if ($task->isRunning()) {
            $task->update(['status' => 'cancelled', 'completed_at' => now()]);

            return response()->json(['message' => 'Task cancelled.']);
        }

        $task->delete();

        return response()->json(['message' => 'Task deleted.']);
    }
}
