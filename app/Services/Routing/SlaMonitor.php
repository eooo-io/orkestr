<?php

namespace App\Services\Routing;

use App\Models\AgentTask;
use App\Models\RoutingDecision;
use App\Models\RoutingRule;

class SlaMonitor
{
    public function __construct(
        protected SmartTaskRouter $router,
    ) {}

    /**
     * Check SLA compliance for a task against a routing rule's SLA config.
     *
     * @return array{met: bool, wait_seconds: int, max_wait: int, breach_risk: float}
     */
    public function checkSla(AgentTask $task, ?RoutingRule $rule): array
    {
        $slaConfig = $rule?->sla_config ?? [];
        $maxWait = $slaConfig['max_wait_seconds'] ?? 300; // Default 5 minutes

        $waitSeconds = $task->created_at
            ? (int) now()->diffInSeconds($task->created_at)
            : 0;

        $met = $waitSeconds <= $maxWait;
        $breachRisk = $maxWait > 0 ? min(1.0, $waitSeconds / $maxWait) : 0.0;

        // Also check cost SLA if configured
        if (! empty($slaConfig['max_cost']) && $task->execution) {
            $currentCost = $task->execution->total_cost_microcents ?? 0;
            if ($currentCost > $slaConfig['max_cost']) {
                $met = false;
                $breachRisk = 1.0;
            }
        }

        return [
            'met' => $met,
            'wait_seconds' => $waitSeconds,
            'max_wait' => $maxWait,
            'breach_risk' => round($breachRisk, 3),
        ];
    }

    /**
     * Escalate a task: bump priority and optionally re-route.
     */
    public function escalate(AgentTask $task): void
    {
        $priorityMap = [
            'low' => 'medium',
            'medium' => 'high',
            'high' => 'critical',
            'critical' => 'critical',
        ];

        $newPriority = $priorityMap[$task->priority] ?? 'high';

        $task->update(['priority' => $newPriority]);

        // Re-route if the task is still pending
        if ($task->isPending() && $task->project_id) {
            $newAgent = $this->router->route($task, $task->project_id);

            if ($newAgent) {
                $task->update([
                    'agent_id' => $newAgent->id,
                    'status' => 'assigned',
                ]);
            }
        }
    }

    /**
     * Get aggregate SLA summary for a project.
     *
     * @return array{total_tasks: int, met: int, breached: int, avg_wait_seconds: float}
     */
    public function getSlaSummary(int $projectId): array
    {
        $decisions = RoutingDecision::whereHas('task', function ($q) use ($projectId) {
            $q->where('project_id', $projectId);
        })->get();

        $total = $decisions->count();
        $met = $decisions->where('sla_met', true)->count();
        $breached = $total - $met;

        // Calculate average wait from tasks
        $tasks = AgentTask::where('project_id', $projectId)
            ->whereNotNull('started_at')
            ->whereNotNull('created_at')
            ->get();

        $totalWait = 0;
        $waitCount = 0;

        foreach ($tasks as $task) {
            $wait = $task->created_at->diffInSeconds($task->started_at);
            $totalWait += $wait;
            $waitCount++;
        }

        $avgWait = $waitCount > 0 ? round($totalWait / $waitCount, 1) : 0;

        return [
            'total_tasks' => $total,
            'met' => $met,
            'breached' => $breached,
            'avg_wait_seconds' => $avgWait,
        ];
    }
}
