<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\ExecutionRun;
use App\Models\Project;
use App\Http\Resources\ExecutionRunResource;
use App\Jobs\RunScheduledAgentJob;
use App\Models\AgentSchedule;
use App\Services\Execution\AgentExecutionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class A2aExecutionController extends Controller
{
    public function __construct(
        private AgentExecutionService $executionService,
    ) {}

    /**
     * POST /api/a2a/{agentSlug}/execute
     *
     * Receive an A2A delegation request and execute the agent.
     */
    public function execute(Request $request, string $agentSlug): JsonResponse
    {
        $agent = Agent::where('slug', $agentSlug)->first();

        if (! $agent) {
            return response()->json(['error' => 'Agent not found.'], 404);
        }

        // Find the first project this agent is enabled in
        $projectAgent = $agent->projectAgents()
            ->where('is_enabled', true)
            ->first();

        if (! $projectAgent) {
            return response()->json(['error' => 'Agent is not enabled in any project.'], 422);
        }

        $project = Project::find($projectAgent->project_id);

        if (! $project) {
            return response()->json(['error' => 'Project not found.'], 404);
        }

        $validated = $request->validate([
            'task' => 'required|string|max:10000',
            'context' => 'nullable|array',
        ]);

        $mode = $request->header('X-A2A-Mode', 'async');

        $input = [
            'message' => $validated['task'],
            'context' => $validated['context'] ?? [],
            '_trigger_source' => 'a2a',
        ];

        if ($mode === 'sync') {
            return $this->executeSync($project, $agent, $input);
        }

        return $this->executeAsync($project, $agent, $input);
    }

    /**
     * Execute synchronously (up to 30s timeout) and return result.
     */
    private function executeSync(Project $project, Agent $agent, array $input): JsonResponse
    {
        try {
            $run = $this->executionService->execute(
                project: $project,
                agent: $agent,
                input: $input,
                config: ['timeout' => 30],
            );

            $run->load('steps');

            return response()->json([
                'execution_id' => $run->uuid,
                'status' => $run->status,
                'output' => $run->output,
                'total_tokens' => $run->total_tokens,
                'total_duration_ms' => $run->total_duration_ms,
            ]);
        } catch (\Throwable $e) {
            Log::error("A2A sync execution failed for agent {$agent->slug}: {$e->getMessage()}");

            return response()->json([
                'error' => 'Execution failed.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Dispatch execution asynchronously and return the execution ID.
     */
    private function executeAsync(Project $project, Agent $agent, array $input): JsonResponse
    {
        $run = ExecutionRun::create([
            'project_id' => $project->id,
            'agent_id' => $agent->id,
            'input' => $input,
            'config' => ['trigger_source' => 'a2a'],
            'status' => 'pending',
        ]);

        // Find or create a schedule-like dispatch — use the job directly
        // We create a temporary schedule concept for the A2A trigger
        dispatch(function () use ($project, $agent, $input, $run) {
            try {
                $service = app(AgentExecutionService::class);
                $result = $service->execute(
                    project: $project,
                    agent: $agent,
                    input: $input,
                    config: [],
                );

                // Copy results to the pre-created run
                $run->update([
                    'status' => $result->status,
                    'output' => $result->output,
                    'started_at' => $result->started_at,
                    'completed_at' => $result->completed_at,
                    'total_tokens' => $result->total_tokens,
                    'total_cost_microcents' => $result->total_cost_microcents,
                    'total_duration_ms' => $result->total_duration_ms,
                    'error' => $result->error,
                    'model_used' => $result->model_used,
                ]);
            } catch (\Throwable $e) {
                $run->markFailed($e->getMessage());
            }
        })->afterResponse();

        return response()->json([
            'execution_id' => $run->uuid,
            'status' => 'pending',
            'message' => 'Execution dispatched.',
        ], 202);
    }
}
