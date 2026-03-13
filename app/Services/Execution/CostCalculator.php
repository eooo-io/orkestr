<?php

namespace App\Services\Execution;

class CostCalculator
{
    /**
     * Model pricing in microcents per token (1 microcent = 1/10000 of a cent).
     * Prices per 1M tokens converted to per-token microcents.
     */
    private const MODEL_PRICING = [
        // Anthropic
        'claude-opus-4-6' => ['input' => 150, 'output' => 750],            // $15/$75 per 1M
        'claude-sonnet-4-6' => ['input' => 30, 'output' => 150],           // $3/$15 per 1M
        'claude-haiku-4-5-20251001' => ['input' => 8, 'output' => 40],     // $0.80/$4 per 1M
        // OpenAI
        'gpt-5.4' => ['input' => 50, 'output' => 150],                     // $5/$15 per 1M
        'gpt-5.4-thinking' => ['input' => 50, 'output' => 150],            // $5/$15 per 1M
        'gpt-5-mini' => ['input' => 3, 'output' => 12],                    // $0.30/$1.20 per 1M
        'gpt-5.3-instant' => ['input' => 10, 'output' => 40],              // $1/$4 per 1M
        'o3' => ['input' => 100, 'output' => 400],                         // $10/$40 per 1M
        // Google Gemini
        'gemini-3.1-pro' => ['input' => 12, 'output' => 50],               // $1.25/$5 per 1M
        'gemini-3-flash' => ['input' => 2, 'output' => 8],                 // $0.15/$0.60 per 1M (est.)
        'gemini-3.1-flash-lite' => ['input' => 3, 'output' => 15],         // $0.25/$1.50 per 1M
    ];

    /**
     * Calculate cost in microcents for a given token usage and model.
     */
    public function calculate(string $model, array $tokenUsage): int
    {
        $pricing = self::MODEL_PRICING[$model] ?? ['input' => 30, 'output' => 150]; // default to Sonnet pricing

        $inputTokens = $tokenUsage['input_tokens'] ?? 0;
        $outputTokens = $tokenUsage['output_tokens'] ?? 0;

        $inputCost = (int) ceil($inputTokens * $pricing['input'] / 10000);
        $outputCost = (int) ceil($outputTokens * $pricing['output'] / 10000);

        return $inputCost + $outputCost;
    }

    /**
     * Format microcents as a human-readable dollar amount.
     */
    public static function formatCost(int $microcents): string
    {
        $dollars = $microcents / 1000000;

        if ($dollars < 0.01) {
            return '< $0.01';
        }

        return '$' . number_format($dollars, 4);
    }

    /**
     * Get aggregate cost stats for a set of execution runs.
     */
    public function aggregateStats(iterable $runs): array
    {
        $totalTokens = 0;
        $totalCost = 0;
        $totalDuration = 0;
        $count = 0;
        $byModel = [];

        foreach ($runs as $run) {
            $totalTokens += $run->total_tokens;
            $totalCost += $run->total_cost_microcents;
            $totalDuration += $run->total_duration_ms;
            $count++;

            $model = $run->agent?->model ?? 'unknown';
            if (! isset($byModel[$model])) {
                $byModel[$model] = ['tokens' => 0, 'cost' => 0, 'runs' => 0];
            }
            $byModel[$model]['tokens'] += $run->total_tokens;
            $byModel[$model]['cost'] += $run->total_cost_microcents;
            $byModel[$model]['runs']++;
        }

        return [
            'total_runs' => $count,
            'total_tokens' => $totalTokens,
            'total_cost_microcents' => $totalCost,
            'total_cost_formatted' => self::formatCost($totalCost),
            'total_duration_ms' => $totalDuration,
            'by_model' => $byModel,
        ];
    }
}
