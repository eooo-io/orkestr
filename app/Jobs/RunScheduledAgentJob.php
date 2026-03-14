<?php

namespace App\Jobs;

use App\Models\AgentSchedule;
use App\Services\Execution\AgentExecutionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunScheduledAgentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(
        public AgentSchedule $schedule,
        public array $triggerPayload = [],
        public string $triggerSource = 'cron',
    ) {}

    public function handle(AgentExecutionService $executionService): void
    {
        // Re-fetch to check if still enabled
        $schedule = $this->schedule->fresh();

        if (! $schedule || ! $schedule->is_enabled) {
            Log::info("Skipping disabled schedule: {$this->schedule->id}");

            return;
        }

        // Merge input_template with trigger payload
        $input = array_merge(
            $schedule->input_template ?? [],
            $this->triggerPayload,
            ['_trigger_source' => $this->triggerSource],
        );

        // Build execution config
        $config = $schedule->execution_config ?? [];

        // Execute the agent
        $run = $executionService->execute(
            $schedule->project,
            $schedule->agent,
            $input,
            $config,
            $schedule->created_by,
        );

        // Link the execution run to this schedule
        $run->update(['schedule_id' => $schedule->id]);

        // Record success
        $schedule->recordSuccess();

        // For cron schedules, compute next run time
        if ($schedule->trigger_type === 'cron') {
            $schedule->update(['next_run_at' => $schedule->computeNextRun()]);
        }

        Log::info("Schedule {$schedule->id} executed successfully", [
            'run_id' => $run->id,
            'trigger_source' => $this->triggerSource,
        ]);
    }

    public function failed(?\Throwable $e): void
    {
        $this->schedule->recordFailure($e?->getMessage() ?? 'Unknown error');

        Log::error("Schedule {$this->schedule->id} failed: {$e?->getMessage()}", [
            'schedule_id' => $this->schedule->id,
            'trigger_source' => $this->triggerSource,
        ]);
    }
}
