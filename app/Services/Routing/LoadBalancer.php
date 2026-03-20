<?php

namespace App\Services\Routing;

use App\Models\AgentHealthCheck;
use App\Models\AgentProcess;
use App\Models\AgentResourceQuota;
use App\Models\ExecutionRun;

class LoadBalancer
{
    /**
     * Default max concurrent tasks if no quota is configured.
     */
    private const DEFAULT_CAPACITY = 3;

    /**
     * Get the current load for an agent: count of active executions + active processes.
     */
    public function getCurrentLoad(int $agentId): int
    {
        $activeRuns = ExecutionRun::where('agent_id', $agentId)
            ->where('status', 'running')
            ->count();

        $activeProcesses = AgentProcess::where('agent_id', $agentId)
            ->whereIn('status', ['running', 'idle'])
            ->count();

        return $activeRuns + $activeProcesses;
    }

    /**
     * Get the capacity for an agent from its resource quota.
     */
    public function getCapacity(int $agentId): int
    {
        $quota = AgentResourceQuota::where('agent_id', $agentId)->first();

        return $quota?->max_concurrent_executions ?? self::DEFAULT_CAPACITY;
    }

    /**
     * Check whether an agent is available (under capacity and healthy).
     */
    public function isAvailable(int $agentId): bool
    {
        $load = $this->getCurrentLoad($agentId);
        $capacity = $this->getCapacity($agentId);

        if ($load >= $capacity) {
            return false;
        }

        // Check for recent health issues
        $latestCheck = AgentHealthCheck::where('agent_id', $agentId)
            ->orderByDesc('checked_at')
            ->first();

        if ($latestCheck && $latestCheck->status === 'failed') {
            // If the latest health check failed within the last 5 minutes, consider unavailable
            if ($latestCheck->checked_at && $latestCheck->checked_at->diffInMinutes(now()) < 5) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the load factor for an agent: 0.0 (idle) to 1.0 (full capacity).
     */
    public function getLoadFactor(int $agentId): float
    {
        $capacity = $this->getCapacity($agentId);

        if ($capacity <= 0) {
            return 1.0;
        }

        $load = $this->getCurrentLoad($agentId);

        return min(1.0, $load / $capacity);
    }
}
