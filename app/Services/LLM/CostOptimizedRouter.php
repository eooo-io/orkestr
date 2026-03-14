<?php

namespace App\Services\LLM;

use App\Services\Execution\CostCalculator;

class CostOptimizedRouter
{
    /**
     * Select the best model based on routing strategy.
     *
     * @param  string  $primaryModel  The configured primary model
     * @param  array  $candidates  Additional candidate models (fallback chain)
     * @param  string  $strategy  'default', 'cost_optimized', 'performance'
     * @return array Reordered model list (first = preferred)
     */
    public function selectModels(string $primaryModel, array $candidates, string $strategy): array
    {
        $allModels = array_values(array_unique(array_merge([$primaryModel], $candidates)));

        return match ($strategy) {
            'cost_optimized' => CostCalculator::rankByCost($allModels),
            'performance' => $this->rankByPerformance($allModels),
            default => $allModels, // 'default' keeps original order
        };
    }

    private function rankByPerformance(array $models): array
    {
        // Reverse cost order — most expensive (most capable) first
        return array_reverse(CostCalculator::rankByCost($models));
    }
}
