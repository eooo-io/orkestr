<?php

namespace App\Services\Execution\Guards;

use App\Models\Agent;
use App\Models\ExecutionRun;
use App\Services\Execution\CostCalculator;
use Illuminate\Support\Carbon;

class BudgetGuard
{
    /**
     * Default limits (can be overridden per-run via config).
     */
    private const DEFAULT_MAX_TOKENS = 100_000;

    private const DEFAULT_MAX_COST_MICROCENTS = 5_000_000; // $5.00

    private const DEFAULT_MAX_ITERATIONS = 25;

    public function __construct(
        private CostCalculator $costCalculator,
    ) {}

    /**
     * Check if the run has exceeded its budget.
     * Returns null if OK, or a string error message if over budget.
     */
    public function check(ExecutionRun $run, array $config = []): ?string
    {
        $maxTokens = $config['max_total_tokens'] ?? self::DEFAULT_MAX_TOKENS;
        $maxCost = $config['max_cost_microcents'] ?? self::DEFAULT_MAX_COST_MICROCENTS;
        $maxIterations = $config['max_iterations'] ?? self::DEFAULT_MAX_ITERATIONS;

        if ($run->total_tokens >= $maxTokens) {
            return "Token budget exceeded: {$run->total_tokens} >= {$maxTokens} tokens";
        }

        if ($run->total_cost_microcents >= $maxCost) {
            $formattedCost = CostCalculator::formatCost($run->total_cost_microcents);
            $formattedMax = CostCalculator::formatCost($maxCost);

            return "Cost budget exceeded: {$formattedCost} >= {$formattedMax}";
        }

        $stepCount = $run->steps()->count();
        // Each iteration has ~4 steps (perceive, reason, act, observe)
        $estimatedIterations = (int) ceil($stepCount / 4);
        if ($estimatedIterations >= $maxIterations) {
            return "Iteration limit exceeded: {$estimatedIterations} >= {$maxIterations}";
        }

        return null;
    }

    /**
     * Estimate cost for a single LLM call and check if it would exceed the budget.
     */
    public function wouldExceedBudget(ExecutionRun $run, string $model, array $estimatedUsage, array $config = []): bool
    {
        $maxCost = $config['max_cost_microcents'] ?? self::DEFAULT_MAX_COST_MICROCENTS;
        $estimatedCost = $this->costCalculator->calculate($model, $estimatedUsage);

        return ($run->total_cost_microcents + $estimatedCost) >= $maxCost;
    }

    /**
     * Check per-agent per-run budget limit.
     * Returns null if OK, or a string error if budget exceeded.
     */
    public function checkAgentRunBudget(Agent $agent, ExecutionRun $run): ?string
    {
        if ($agent->budget_limit_usd === null) {
            return null;
        }

        $limitMicrocents = (int) ($agent->budget_limit_usd * 1_000_000);
        $currentCost = $run->total_cost_microcents;

        if ($currentCost >= $limitMicrocents) {
            $formatted = CostCalculator::formatCost($currentCost);
            $limitFormatted = '$' . number_format($agent->budget_limit_usd, 4);

            return "Agent per-run budget exceeded: {$formatted} >= {$limitFormatted}";
        }

        return null;
    }

    /**
     * Check per-agent daily budget limit.
     * Returns null if OK, or a string error if budget exceeded.
     */
    public function checkAgentDailyBudget(Agent $agent): ?string
    {
        if ($agent->daily_budget_limit_usd === null) {
            return null;
        }

        $limitMicrocents = (int) ($agent->daily_budget_limit_usd * 1_000_000);
        $todayStart = Carbon::today();

        $dailyCost = ExecutionRun::where('agent_id', $agent->id)
            ->where('created_at', '>=', $todayStart)
            ->sum('total_cost_microcents');

        if ($dailyCost >= $limitMicrocents) {
            $formatted = CostCalculator::formatCost($dailyCost);
            $limitFormatted = '$' . number_format($agent->daily_budget_limit_usd, 4);

            return "Agent daily budget exceeded: {$formatted} >= {$limitFormatted}";
        }

        return null;
    }

    /**
     * Get budget status for an agent.
     */
    public function getAgentBudgetStatus(Agent $agent): array
    {
        $todayStart = Carbon::today();

        $dailySpend = ExecutionRun::where('agent_id', $agent->id)
            ->where('created_at', '>=', $todayStart)
            ->sum('total_cost_microcents');

        $runBudgetLimit = $agent->budget_limit_usd !== null
            ? (int) ($agent->budget_limit_usd * 1_000_000)
            : null;

        $dailyBudgetLimit = $agent->daily_budget_limit_usd !== null
            ? (int) ($agent->daily_budget_limit_usd * 1_000_000)
            : null;

        return [
            'daily_spend_microcents' => $dailySpend,
            'daily_spend_formatted' => CostCalculator::formatCost($dailySpend),
            'run_budget_limit_usd' => $agent->budget_limit_usd,
            'daily_budget_limit_usd' => $agent->daily_budget_limit_usd,
            'run_budget_limit_microcents' => $runBudgetLimit,
            'daily_budget_limit_microcents' => $dailyBudgetLimit,
            'daily_remaining_microcents' => $dailyBudgetLimit !== null ? max(0, $dailyBudgetLimit - $dailySpend) : null,
            'daily_remaining_formatted' => $dailyBudgetLimit !== null
                ? CostCalculator::formatCost(max(0, $dailyBudgetLimit - $dailySpend))
                : null,
        ];
    }

    /**
     * Get the configured limits for display/API.
     */
    public static function getLimits(array $config = []): array
    {
        return [
            'max_total_tokens' => $config['max_total_tokens'] ?? self::DEFAULT_MAX_TOKENS,
            'max_cost_microcents' => $config['max_cost_microcents'] ?? self::DEFAULT_MAX_COST_MICROCENTS,
            'max_cost_formatted' => CostCalculator::formatCost($config['max_cost_microcents'] ?? self::DEFAULT_MAX_COST_MICROCENTS),
            'max_iterations' => $config['max_iterations'] ?? self::DEFAULT_MAX_ITERATIONS,
        ];
    }
}
