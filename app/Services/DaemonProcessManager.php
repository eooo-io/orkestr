<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\AgentProcess;
use App\Models\Project;
use App\Jobs\DaemonExecutionJob;

class DaemonProcessManager
{
    /**
     * Start a daemon process for an agent in a project.
     */
    public function start(Agent $agent, Project $project, array $options = []): AgentProcess
    {
        // Check if already running
        $existing = AgentProcess::forAgent($agent->id, $project->id)->active()->first();
        if ($existing) {
            return $existing;
        }

        $process = AgentProcess::create([
            'agent_id' => $agent->id,
            'project_id' => $project->id,
            'status' => 'starting',
            'restart_policy' => $options['restart_policy'] ?? 'on_failure',
            'max_restarts' => $options['max_restarts'] ?? 5,
            'wake_conditions' => $options['wake_conditions'] ?? null,
            'state' => $options['initial_state'] ?? [],
        ]);

        // Dispatch the long-running job
        DaemonExecutionJob::dispatch($process);

        return $process;
    }

    /**
     * Stop a daemon process gracefully.
     */
    public function stop(AgentProcess $process, string $reason = 'User requested stop'): void
    {
        if (! $process->isRunning()) {
            return;
        }

        $process->transitionTo('stopping', $reason);

        // The DaemonExecutionJob checks for 'stopping' status and exits gracefully
    }

    /**
     * Force-kill a daemon process.
     */
    public function kill(AgentProcess $process): void
    {
        $process->transitionTo('stopped', 'Force killed');
    }

    /**
     * Restart a daemon process.
     */
    public function restart(AgentProcess $process): AgentProcess
    {
        $this->stop($process, 'Restart requested');

        // Force old process to stopped so start() won't see it as active
        $process->refresh();
        if ($process->status === 'stopping') {
            $process->transitionTo('stopped', 'Restart superseded');
        }

        // Create a new process with same config
        return $this->start(
            $process->agent,
            $process->project,
            [
                'restart_policy' => $process->restart_policy,
                'max_restarts' => $process->max_restarts,
                'wake_conditions' => $process->wake_conditions,
                'initial_state' => $process->state ?? [],
            ],
        );
    }

    /**
     * Get status of all running processes.
     */
    public function fleet(): array
    {
        return AgentProcess::active()
            ->with(['agent:id,name,slug,icon', 'project:id,name'])
            ->get()
            ->map(fn (AgentProcess $p) => [
                'id' => $p->id,
                'uuid' => $p->uuid,
                'agent' => $p->agent,
                'project' => $p->project,
                'status' => $p->status,
                'healthy' => $p->isHealthy(),
                'started_at' => $p->started_at?->toIso8601String(),
                'last_heartbeat_at' => $p->last_heartbeat_at?->toIso8601String(),
                'restart_count' => $p->restart_count,
                'uptime_seconds' => $p->started_at ? $p->started_at->diffInSeconds(now()) : 0,
            ])
            ->all();
    }

    /**
     * Check health of all running processes and handle stale ones.
     */
    public function healthCheck(): array
    {
        $processes = AgentProcess::active()->get();
        $results = [];

        foreach ($processes as $process) {
            if ($process->isHealthy()) {
                $results[] = ['id' => $process->id, 'status' => 'healthy'];
                continue;
            }

            // Stale process — mark as crashed
            $process->transitionTo('crashed', 'Heartbeat timeout');

            if ($process->canRestart()) {
                $newProcess = $this->start(
                    $process->agent,
                    $process->project,
                    [
                        'restart_policy' => $process->restart_policy,
                        'max_restarts' => $process->max_restarts,
                        'wake_conditions' => $process->wake_conditions,
                        'initial_state' => $process->state ?? [],
                    ],
                );
                $results[] = ['id' => $process->id, 'status' => 'restarted', 'new_id' => $newProcess->id];
            } else {
                $results[] = ['id' => $process->id, 'status' => 'crashed', 'restarts_exhausted' => true];
            }
        }

        return $results;
    }
}
