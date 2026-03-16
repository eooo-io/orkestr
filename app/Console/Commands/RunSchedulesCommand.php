<?php

namespace App\Console\Commands;

use App\Jobs\RunScheduledAgentJob;
use App\Models\AgentSchedule;
use App\Models\Notification;
use Illuminate\Console\Command;

class RunSchedulesCommand extends Command
{
    protected $signature = 'orkestr:run-schedules';

    protected $description = 'Process all due agent schedules and dispatch execution jobs';

    public function handle(): int
    {
        $dueSchedules = AgentSchedule::cron()
            ->enabled()
            ->due()
            ->with(['agent', 'project'])
            ->get();

        if ($dueSchedules->isEmpty()) {
            $this->info('No due schedules found.');

            return self::SUCCESS;
        }

        $dispatched = 0;
        $failed = 0;

        foreach ($dueSchedules as $schedule) {
            try {
                // Bump next_run_at immediately to prevent double-dispatch
                $nextRun = $schedule->computeNextRun();
                $schedule->update([
                    'next_run_at' => $nextRun,
                    'last_run_at' => now(),
                ]);

                RunScheduledAgentJob::dispatch($schedule, [], 'cron');
                $schedule->increment('run_count');
                $dispatched++;

                $this->line("Dispatched schedule: {$schedule->name} (ID: {$schedule->id})");
            } catch (\Throwable $e) {
                $failed++;
                $schedule->increment('failure_count');
                $schedule->update([
                    'last_error' => $e->getMessage(),
                    'last_run_at' => now(),
                ]);
                $schedule->increment('run_count');

                // Auto-disable after 5 consecutive failures
                $schedule->refresh();
                if ($schedule->failure_count > 5) {
                    $schedule->update(['is_enabled' => false]);

                    $this->warn("Auto-disabled schedule: {$schedule->name} (ID: {$schedule->id}) after {$schedule->failure_count} failures");

                    // Notify the schedule creator
                    if ($schedule->created_by) {
                        Notification::create([
                            'user_id' => $schedule->created_by,
                            'organization_id' => null,
                            'type' => 'schedule_auto_disabled',
                            'title' => "Schedule auto-disabled: {$schedule->name}",
                            'body' => "Schedule \"{$schedule->name}\" for agent \"{$schedule->agent->displayName()}\" was auto-disabled after {$schedule->failure_count} consecutive failures. Last error: {$e->getMessage()}",
                            'data' => [
                                'schedule_id' => $schedule->id,
                                'agent_id' => $schedule->agent_id,
                                'project_id' => $schedule->project_id,
                                'failure_count' => $schedule->failure_count,
                            ],
                            'created_at' => now(),
                        ]);
                    }
                }

                $this->error("Failed to dispatch schedule: {$schedule->name} (ID: {$schedule->id}): {$e->getMessage()}");
            }
        }

        $this->info("Dispatched {$dispatched} schedule(s), {$failed} failed.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
