<?php

namespace App\Listeners;

use App\Models\Agent;
use App\Models\ExecutionRun;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;

class ExecutionCompletedListener
{
    /**
     * Handle execution completion — create notifications based on agent config.
     *
     * Called directly from RunScheduledAgentJob or AgentExecutionService
     * after an execution completes.
     */
    public static function handle(ExecutionRun $run): void
    {
        $agent = $run->agent;

        if (! $agent) {
            return;
        }

        $userId = $run->created_by;

        if (! $userId) {
            return;
        }

        $status = $run->status;
        $agentName = $agent->displayName();
        $durationSec = $run->total_duration_ms > 0
            ? round($run->total_duration_ms / 1000, 1)
            : null;
        $costDisplay = $run->total_cost_microcents > 0
            ? '$' . number_format($run->total_cost_microcents / 1000000, 4)
            : null;

        $shouldNotify = false;
        $type = 'execution_completed';
        $title = '';
        $body = '';

        if ($status === 'failed' && ($agent->notify_on_failure ?? true)) {
            $shouldNotify = true;
            $type = 'execution_failed';
            $title = "Execution failed: {$agentName}";
            $body = "Agent \"{$agentName}\" execution failed.";
            if ($run->error) {
                $body .= " Error: {$run->error}";
            }
            if ($durationSec !== null) {
                $body .= " Duration: {$durationSec}s.";
            }
        } elseif ($status === 'completed' && ($agent->notify_on_success ?? false)) {
            $shouldNotify = true;
            $type = 'execution_completed';
            $title = "Execution completed: {$agentName}";
            $body = "Agent \"{$agentName}\" completed execution successfully.";
            if ($durationSec !== null) {
                $body .= " Duration: {$durationSec}s.";
            }
            if ($costDisplay !== null) {
                $body .= " Cost: {$costDisplay}.";
            }
        } elseif ($status === 'budget_exceeded') {
            // Always notify on budget exceeded
            $shouldNotify = true;
            $type = 'execution_budget_exceeded';
            $title = "Budget exceeded: {$agentName}";
            $body = "Agent \"{$agentName}\" execution was stopped due to budget limits.";
            if ($costDisplay !== null) {
                $body .= " Cost at termination: {$costDisplay}.";
            }
        }

        if (! $shouldNotify) {
            return;
        }

        try {
            Notification::create([
                'user_id' => $userId,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'data' => [
                    'execution_run_id' => $run->id,
                    'execution_run_uuid' => $run->uuid,
                    'agent_id' => $agent->id,
                    'agent_name' => $agentName,
                    'project_id' => $run->project_id,
                    'status' => $status,
                    'total_duration_ms' => $run->total_duration_ms,
                    'total_cost_microcents' => $run->total_cost_microcents,
                    'total_tokens' => $run->total_tokens,
                ],
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning("Failed to create execution notification: {$e->getMessage()}");
        }
    }
}
