<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\AgentProcess;
use App\Models\Project;
use App\Services\DaemonProcessManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentProcessController extends Controller
{
    public function __construct(
        protected DaemonProcessManager $processManager,
    ) {}

    /**
     * List all running daemon processes (fleet overview).
     */
    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->processManager->fleet()]);
    }

    /**
     * Get process status for a specific agent in a project.
     */
    public function status(Project $project, Agent $agent): JsonResponse
    {
        $process = AgentProcess::forAgent($agent->id, $project->id)
            ->latest()
            ->first();

        if (! $process) {
            return response()->json(['data' => null, 'running' => false]);
        }

        return response()->json([
            'data' => [
                'id' => $process->id,
                'uuid' => $process->uuid,
                'status' => $process->status,
                'healthy' => $process->isHealthy(),
                'started_at' => $process->started_at?->toIso8601String(),
                'last_heartbeat_at' => $process->last_heartbeat_at?->toIso8601String(),
                'stopped_at' => $process->stopped_at?->toIso8601String(),
                'restart_count' => $process->restart_count,
                'restart_policy' => $process->restart_policy,
                'state' => $process->state,
                'stop_reason' => $process->stop_reason,
                'uptime_seconds' => $process->started_at && $process->isRunning()
                    ? $process->started_at->diffInSeconds(now())
                    : 0,
            ],
            'running' => $process->isRunning(),
        ]);
    }

    /**
     * Start a daemon process.
     */
    public function start(Request $request, Project $project, Agent $agent): JsonResponse
    {
        $validated = $request->validate([
            'restart_policy' => 'sometimes|string|in:always,on_failure,never',
            'max_restarts' => 'sometimes|integer|min:0|max:100',
            'wake_conditions' => 'nullable|array',
            'wake_conditions.event_topics' => 'nullable|array',
            'wake_conditions.event_topics.*' => 'string',
        ]);

        $process = $this->processManager->start($agent, $project, $validated);

        return response()->json([
            'data' => [
                'id' => $process->id,
                'uuid' => $process->uuid,
                'status' => $process->status,
            ],
            'message' => 'Daemon process starting',
        ], 201);
    }

    /**
     * Stop a daemon process.
     */
    public function stop(Project $project, Agent $agent): JsonResponse
    {
        $process = AgentProcess::forAgent($agent->id, $project->id)->active()->first();

        if (! $process) {
            return response()->json(['message' => 'No running process found'], 404);
        }

        $this->processManager->stop($process);

        return response()->json(['message' => 'Stop signal sent']);
    }

    /**
     * Restart a daemon process.
     */
    public function restart(Project $project, Agent $agent): JsonResponse
    {
        $process = AgentProcess::forAgent($agent->id, $project->id)->active()->first();

        if (! $process) {
            return response()->json(['message' => 'No running process found'], 404);
        }

        $newProcess = $this->processManager->restart($process);

        return response()->json([
            'data' => [
                'id' => $newProcess->id,
                'uuid' => $newProcess->uuid,
                'status' => $newProcess->status,
            ],
            'message' => 'Daemon process restarting',
        ]);
    }

    /**
     * Run health check on all daemon processes.
     */
    public function healthCheck(): JsonResponse
    {
        $results = $this->processManager->healthCheck();

        return response()->json(['data' => $results]);
    }

    /**
     * Get process history for an agent.
     */
    public function history(Project $project, Agent $agent): JsonResponse
    {
        $processes = AgentProcess::forAgent($agent->id, $project->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn (AgentProcess $p) => [
                'id' => $p->id,
                'status' => $p->status,
                'started_at' => $p->started_at?->toIso8601String(),
                'stopped_at' => $p->stopped_at?->toIso8601String(),
                'stop_reason' => $p->stop_reason,
                'restart_count' => $p->restart_count,
                'uptime_seconds' => $p->started_at && $p->stopped_at
                    ? $p->started_at->diffInSeconds($p->stopped_at)
                    : ($p->started_at && $p->isRunning() ? $p->started_at->diffInSeconds(now()) : 0),
            ]);

        return response()->json(['data' => $processes]);
    }
}
