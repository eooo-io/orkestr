<?php

namespace App\Services\Execution;

use App\Models\Agent;
use App\Models\ExecutionRun;
use Illuminate\Support\Facades\Cache;

/**
 * Real-time budget enforcement for agent executions.
 *
 * Checks per-execution and daily budget limits, returning structured
 * results indicating whether the agent is allowed to continue.
 */
class BudgetEnforcer
{
    public function __construct(
        private CostCalculator $costCalculator,
    ) {}

    /**
     * Check if the agent is within budget for the given execution.
     *
     * @return array{allowed: bool, reason: string|null, remaining_usd: float}
     */
    public function check(Agent $agent, int $tokensUsed, float $costUsd): array
    {
        // Check per-execution budget limit
        if ($agent->budget_limit_usd !== null) {
            $limit = (float) $agent->budget_limit_usd;

            if ($costUsd >= $limit) {
                return [
                    'allowed' => false,
                    'reason' => 'budget_exceeded',
                    'remaining_usd' => max(0, $limit - $costUsd),
                ];
            }
        }

        // Check daily budget limit
        $dailyResult = $this->checkDailyBudget($agent, $costUsd);
        if (! $dailyResult['allowed']) {
            return $dailyResult;
        }

        // Calculate remaining budget (use the more restrictive limit)
        $remaining = $this->calculateRemaining($agent, $costUsd);

        return [
            'allowed' => true,
            'reason' => null,
            'remaining_usd' => $remaining,
        ];
    }

    /**
     * Check daily budget using cache-based tracking.
     *
     * @return array{allowed: bool, reason: string|null, remaining_usd: float}
     */
    public function checkDailyBudget(Agent $agent, float $additionalCostUsd = 0): array
    {
        if ($agent->daily_budget_limit_usd === null) {
            return [
                'allowed' => true,
                'reason' => null,
                'remaining_usd' => PHP_FLOAT_MAX,
            ];
        }

        $dailyLimit = (float) $agent->daily_budget_limit_usd;
        $cacheKey = $this->dailyCacheKey($agent->id);

        // Get current daily spend from cache (or compute from DB as fallback)
        $dailySpend = $this->getDailySpend($agent->id);
        $totalWithAdditional = $dailySpend + $additionalCostUsd;

        if ($totalWithAdditional >= $dailyLimit) {
            return [
                'allowed' => false,
                'reason' => 'daily_budget_exceeded',
                'remaining_usd' => max(0, $dailyLimit - $dailySpend),
            ];
        }

        return [
            'allowed' => true,
            'reason' => null,
            'remaining_usd' => max(0, $dailyLimit - $totalWithAdditional),
        ];
    }

    /**
     * Record cost for an agent execution, updating the daily cache.
     */
    public function recordCost(int $agentId, float $costUsd): void
    {
        $cacheKey = $this->dailyCacheKey($agentId);

        $current = Cache::get($cacheKey, 0.0);
        Cache::put($cacheKey, $current + $costUsd, now()->endOfDay());
    }

    /**
     * Get the current daily spend for an agent.
     */
    public function getDailySpend(int $agentId): float
    {
        $cacheKey = $this->dailyCacheKey($agentId);

        // Try cache first
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return (float) $cached;
        }

        // Fall back to DB calculation
        $dailyCostMicrocents = ExecutionRun::where('agent_id', $agentId)
            ->where('created_at', '>=', now()->startOfDay())
            ->sum('total_cost_microcents');

        $dailyCostUsd = $dailyCostMicrocents / 1_000_000;

        // Cache for the rest of the day
        Cache::put($cacheKey, $dailyCostUsd, now()->endOfDay());

        return $dailyCostUsd;
    }

    /**
     * Check a specific execution run against its agent's budget.
     *
     * @return array{allowed: bool, reason: string|null, remaining_usd: float}
     */
    public function checkExecution(ExecutionRun $run): array
    {
        $agent = $run->agent;
        if (! $agent) {
            return ['allowed' => true, 'reason' => null, 'remaining_usd' => PHP_FLOAT_MAX];
        }

        $costUsd = $run->total_cost_microcents / 1_000_000;
        $tokensUsed = $run->total_tokens;

        return $this->check($agent, $tokensUsed, $costUsd);
    }

    /**
     * Get full budget status for display.
     */
    public function getStatus(Agent $agent): array
    {
        $dailySpend = $this->getDailySpend($agent->id);

        return [
            'run_budget_limit_usd' => $agent->budget_limit_usd ? (float) $agent->budget_limit_usd : null,
            'daily_budget_limit_usd' => $agent->daily_budget_limit_usd ? (float) $agent->daily_budget_limit_usd : null,
            'daily_spend_usd' => $dailySpend,
            'daily_remaining_usd' => $agent->daily_budget_limit_usd
                ? max(0, (float) $agent->daily_budget_limit_usd - $dailySpend)
                : null,
        ];
    }

    private function calculateRemaining(Agent $agent, float $currentCostUsd): float
    {
        $remaining = PHP_FLOAT_MAX;

        // Per-execution remaining
        if ($agent->budget_limit_usd !== null) {
            $remaining = min($remaining, (float) $agent->budget_limit_usd - $currentCostUsd);
        }

        // Daily remaining
        if ($agent->daily_budget_limit_usd !== null) {
            $dailySpend = $this->getDailySpend($agent->id);
            $dailyRemaining = (float) $agent->daily_budget_limit_usd - $dailySpend;
            $remaining = min($remaining, $dailyRemaining);
        }

        return max(0, $remaining);
    }

    private function dailyCacheKey(int $agentId): string
    {
        $date = now()->format('Y-m-d');

        return "agent:{$agentId}:daily_cost:{$date}";
    }
}
