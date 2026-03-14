<?php

namespace App\Console\Commands;

use App\Jobs\RunScheduledAgentJob;
use App\Models\AgentSchedule;
use Illuminate\Console\Command;

class ProcessAgentSchedules extends Command
{
    protected $signature = 'schedules:process';

    protected $description = 'Process due agent schedules and dispatch execution jobs';

    public function handle(): int
    {
        $dueSchedules = AgentSchedule::cron()
            ->enabled()
            ->due()
            ->get();

        if ($dueSchedules->isEmpty()) {
            $this->info('No due schedules found.');

            return self::SUCCESS;
        }

        $dispatched = 0;

        foreach ($dueSchedules as $schedule) {
            // Bump next_run_at immediately to prevent double-dispatch
            $schedule->update(['next_run_at' => $schedule->computeNextRun()]);

            RunScheduledAgentJob::dispatch($schedule, [], 'cron');
            $dispatched++;

            $this->line("Dispatched schedule: {$schedule->name} (ID: {$schedule->id})");
        }

        $this->info("Dispatched {$dispatched} schedule(s).");

        return self::SUCCESS;
    }
}
