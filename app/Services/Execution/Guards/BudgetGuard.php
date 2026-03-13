<?php

namespace App\Services\Execution\Guards;

use App\Models\ExecutionRun;
use App\Services\Execution\CostCalculator;

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
