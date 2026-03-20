<?php

namespace App\Jobs;

use App\Models\Agent;
use App\Models\AgentProcess;
use App\Models\Project;
use App\Services\EventBus\EventBusService;
use App\Services\Execution\AgentExecutionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class DaemonExecutionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Max time a daemon can run before forced termination (seconds).
     */
    public int $timeout = 86400; // 24 hours

    /**
     * Don't retry — the process manager handles restarts.
     */
    public int $tries = 1;

    public function __construct(
        protected AgentProcess $process,
    ) {
        $this->onQueue('daemon');
    }

    public function handle(): void
    {
        $process = $this->process->fresh();

        if (! $process || $process->status === 'stopping' || $process->status === 'stopped') {
            return;
        }

        $process->transitionTo('running');
        $process->update(['pid' => getmypid()]);

        Log::info("Daemon started: agent={$process->agent_id} project={$process->project_id} pid=" . getmypid());

        try {
            $this->runLoop($process);
        } catch (\Throwable $e) {
            Log::error("Daemon crashed: {$e->getMessage()}", [
                'process_id' => $process->id,
                'agent_id' => $process->agent_id,
            ]);
            $process->transitionTo('crashed', $e->getMessage());

            return;
        }

        // If we exit the loop normally, we're stopping
        $process->fresh();
        if ($process->status !== 'crashed') {
            $process->transitionTo('stopped', $process->stop_reason ?? 'Loop completed');
        }
    }

    /**
     * The main daemon loop: idle → wake → execute → idle.
     */
    protected function runLoop(AgentProcess $process): void
    {
        $idleSleepSeconds = 5;
        $maxIdleCycles = 720; // 1 hour at 5s intervals before checking for work
        $idleCycles = 0;

        while (true) {
            // Check if we should stop
            $process->refresh();
            if ($process->status === 'stopping') {
                break;
            }

            // Heartbeat
            $process->heartbeat();

            // Check for wake conditions
            $event = $this->checkWakeConditions($process);

            if ($event) {
                // Transition to running and execute
                $process->transitionTo('running');
                $idleCycles = 0;

                $this->executeIteration($process, $event);

                // Back to idle
                $process->transitionTo('idle');
            } else {
                // Stay idle
                if ($process->status !== 'idle') {
                    $process->transitionTo('idle');
                }

                $idleCycles++;

                // Periodic heartbeat even when idle
                if ($idleCycles >= $maxIdleCycles) {
                    $idleCycles = 0;
                    // Could do periodic maintenance here
                }

                sleep($idleSleepSeconds);
            }
        }
    }

    /**
     * Check if any wake conditions are met.
     *
     * @return array|null Event data if woken, null if still idle
     */
    protected function checkWakeConditions(AgentProcess $process): ?array
    {
        $conditions = $process->wake_conditions ?? [];

        // Check event bus subscriptions
        if (! empty($conditions['event_topics'])) {
            foreach ($conditions['event_topics'] as $topicSlug) {
                $events = app(EventBusService::class)->consume(
                    $topicSlug,
                    "daemon:{$process->id}",
                    "worker",
                    1,
                );

                if (! empty($events)) {
                    // Acknowledge
                    $ids = array_column($events, 'id');
                    app(EventBusService::class)->acknowledge($topicSlug, "daemon:{$process->id}", $ids);

                    return [
                        'trigger' => 'event',
                        'topic' => $topicSlug,
                        'event' => $events[0],
                    ];
                }
            }
        }

        // Check Redis dispatch queue for this agent
        try {
            $message = Redis::lpop("daemon:wake:{$process->agent_id}:{$process->project_id}");
            if ($message) {
                return json_decode($message, true) ?? ['trigger' => 'manual'];
            }
        } catch (\Throwable) {
            // Redis unavailable
        }

        return null;
    }

    /**
     * Execute a single iteration of the daemon agent.
     */
    protected function executeIteration(AgentProcess $process, array $event): void
    {
        $executionCount = ($process->state['execution_count'] ?? 0) + 1;

        $process->heartbeat([
            'last_trigger' => $event['trigger'] ?? 'unknown',
            'last_execution_at' => now()->toIso8601String(),
            'execution_count' => $executionCount,
        ]);

        $agent = Agent::find($process->agent_id);
        $project = Project::find($process->project_id);

        if (! $agent || ! $project) {
            Log::warning("Daemon iteration skipped: agent or project not found", [
                'process_id' => $process->id,
                'agent_id' => $process->agent_id,
                'project_id' => $process->project_id,
            ]);

            return;
        }

        $input = [
            'trigger' => $event['trigger'] ?? 'unknown',
            'event_data' => $event['event'] ?? [],
            'topic' => $event['topic'] ?? null,
            'daemon_iteration' => $executionCount,
        ];

        try {
            $executionService = app(AgentExecutionService::class);
            $run = $executionService->execute($project, $agent, $input, [
                'source' => 'daemon',
                'process_id' => $process->id,
            ]);

            $process->heartbeat([
                'last_run_id' => $run->id,
                'last_run_status' => $run->status,
                'total_tokens' => ($process->state['total_tokens'] ?? 0) + ($run->total_tokens ?? 0),
                'total_cost' => ($process->state['total_cost'] ?? 0) + ($run->total_cost ?? 0),
            ]);

            Log::info("Daemon iteration completed: agent={$agent->slug} run={$run->id} status={$run->status}");
        } catch (\Throwable $e) {
            Log::error("Daemon iteration failed: {$e->getMessage()}", [
                'process_id' => $process->id,
                'agent_id' => $process->agent_id,
                'iteration' => $executionCount,
            ]);

            $process->heartbeat([
                'last_error' => $e->getMessage(),
                'error_count' => ($process->state['error_count'] ?? 0) + 1,
            ]);
        }
    }
}
