<?php

namespace App\Http\Controllers;

use App\Http\Resources\ExecutionRunResource;
use App\Jobs\RunAgentJob;
use App\Models\Agent;
use App\Models\ExecutionRun;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExecutionStreamController extends Controller
{
    /**
     * POST /api/projects/{project}/agents/{agent}/run
     *
     * Dispatch an async agent execution and return the execution ID
     * for the client to connect via SSE.
     */
    public function run(Request $request, Project $project, Agent $agent): JsonResponse
    {
        $validated = $request->validate([
            'input' => 'nullable|array',
            'input.message' => 'nullable|string',
            'input.goal' => 'nullable|string',
            'config' => 'nullable|array',
            'config.max_tokens' => 'nullable|integer|min:1|max:32000',
            'trigger_type' => 'nullable|string|in:manual,schedule,webhook,a2a',
        ]);

        $executionId = (string) Str::uuid();
        $triggerType = $validated['trigger_type'] ?? 'manual';

        // Create the execution run record immediately so the client can track it
        $run = ExecutionRun::create([
            'uuid' => $executionId,
            'project_id' => $project->id,
            'agent_id' => $agent->id,
            'input' => $validated['input'] ?? [],
            'config' => array_merge($validated['config'] ?? [], ['trigger_type' => $triggerType]),
            'created_by' => $request->user()?->id,
        ]);

        // Dispatch the job to the queue
        RunAgentJob::dispatch(
            projectId: $project->id,
            agentId: $agent->id,
            input: $validated['input'] ?? [],
            triggerType: $triggerType,
            executionId: $executionId,
            createdBy: $request->user()?->id,
            config: $validated['config'] ?? [],
        );

        return response()->json([
            'execution_id' => $executionId,
            'run_id' => $run->id,
            'stream_url' => "/api/executions/{$executionId}/stream",
            'status' => 'queued',
        ], 202);
    }

    /**
     * GET /api/executions/{executionId}/stream
     *
     * SSE endpoint that subscribes to the Redis channel for an execution
     * and forwards events to the browser.
     */
    public function stream(string $executionId): StreamedResponse
    {
        $run = ExecutionRun::where('uuid', $executionId)->first();

        if (! $run) {
            return new StreamedResponse(function () {
                echo "event: error\n";
                echo "data: " . json_encode(['error' => 'Execution not found']) . "\n\n";
                ob_flush();
                flush();
            }, 404, $this->sseHeaders());
        }

        // If execution is already finished, send the final status and close
        if ($run->isFinished()) {
            return new StreamedResponse(function () use ($run) {
                $this->sendSseEvent('status', [
                    'status' => $run->status,
                    'output' => $run->output,
                ]);
                $this->sendSseEvent('done', ['message' => 'Execution already finished']);
            }, 200, $this->sseHeaders());
        }

        return new StreamedResponse(function () use ($executionId, $run) {
            // Disable output buffering for SSE
            if (ob_get_level()) {
                ob_end_clean();
            }

            // Send initial connection event
            $this->sendSseEvent('connected', [
                'execution_id' => $executionId,
                'status' => $run->status,
            ]);

            $channel = "execution:{$executionId}";
            $timeout = 600; // 10 minutes max
            $startTime = time();
            $lastActivity = time();

            // Poll approach: check Redis pub/sub messages and DB status
            // This avoids blocking the PHP process on a Redis subscription
            while ((time() - $startTime) < $timeout) {
                if (connection_aborted()) {
                    break;
                }

                // Check for execution completion via DB (polling fallback)
                $freshRun = ExecutionRun::where('uuid', $executionId)->first();

                if (! $freshRun) {
                    $this->sendSseEvent('error', ['message' => 'Execution not found']);
                    break;
                }

                // Try to get messages from a Redis list (published events)
                try {
                    $listKey = "execution_events:{$executionId}";
                    while ($message = Redis::lpop($listKey)) {
                        $event = json_decode($message, true);
                        if ($event) {
                            $this->sendSseEvent($event['type'] ?? 'message', $event['data'] ?? []);
                            $lastActivity = time();
                        }
                    }
                } catch (\Throwable $e) {
                    // Redis not available — fall through to DB polling
                }

                // Check if execution is finished
                if ($freshRun->isFinished()) {
                    $this->sendSseEvent('status', [
                        'status' => $freshRun->status,
                        'output' => $freshRun->output,
                        'error' => $freshRun->error,
                        'total_tokens' => $freshRun->total_tokens,
                        'total_cost_microcents' => $freshRun->total_cost_microcents,
                        'total_duration_ms' => $freshRun->total_duration_ms,
                    ]);
                    $this->sendSseEvent('done', ['message' => 'Execution finished']);
                    break;
                }

                // Check if awaiting approval
                if ($freshRun->isAwaitingApproval()) {
                    $this->sendSseEvent('status', ['status' => 'awaiting_approval']);
                }

                // Send heartbeat every 15 seconds to keep connection alive
                if ((time() - $lastActivity) > 15) {
                    echo ": heartbeat\n\n";
                    if (ob_get_level()) {
                        ob_flush();
                    }
                    flush();
                    $lastActivity = time();
                }

                // Brief sleep to avoid tight loop
                usleep(500_000); // 500ms
            }
        }, 200, $this->sseHeaders());
    }

    /**
     * POST /api/executions/{executionId}/cancel
     *
     * Cancel a running execution.
     */
    public function cancel(string $executionId): JsonResponse
    {
        $run = ExecutionRun::where('uuid', $executionId)->first();

        if (! $run) {
            return response()->json(['error' => 'Execution not found'], 404);
        }

        if ($run->isFinished()) {
            return response()->json(['error' => 'Execution is already finished'], 422);
        }

        $run->markCancelled();

        // Publish cancellation event for SSE listeners
        try {
            $listKey = "execution_events:{$executionId}";
            Redis::rpush($listKey, json_encode([
                'type' => 'status',
                'data' => ['status' => 'cancelled'],
            ]));
            Redis::expire($listKey, 600);
        } catch (\Throwable) {
            // Redis not available — the DB status will be picked up by polling
        }

        return response()->json(['status' => 'cancelled', 'execution_id' => $executionId]);
    }

    private function sseHeaders(): array
    {
        return [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ];
    }

    private function sendSseEvent(string $event, array $data): void
    {
        echo "event: {$event}\n";
        echo 'data: ' . json_encode($data) . "\n\n";
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
    }
}
