<?php

namespace App\Services\Observability;

use App\Models\ExecutionRun;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CostForecaster
{
    /**
     * Generate cost forecast data for an organization.
     *
     * @return array{daily_costs: array, forecast_7d: float, forecast_30d: float, trend: string, avg_daily: float}
     */
    public function forecast(int $organizationId): array
    {
        $from = now()->subDays(30)->startOfDay();
        $to = now()->endOfDay();

        // Query daily costs for the last 30 days via projects belonging to the org
        $dailyCosts = ExecutionRun::query()
            ->join('projects', 'execution_runs.project_id', '=', 'projects.id')
            ->where('projects.organization_id', $organizationId)
            ->whereBetween('execution_runs.created_at', [$from, $to])
            ->select(
                DB::raw('DATE(execution_runs.created_at) as date'),
                DB::raw('SUM(execution_runs.total_cost_microcents) / 100 as cost'),
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date,
                'cost' => round((float) $row->cost, 2),
            ])
            ->values()
            ->all();

        // Fill gaps with zero-cost days
        $dailyCosts = $this->fillGaps($dailyCosts, $from, $to);

        $costs = array_column($dailyCosts, 'cost');
        $avgDaily = count($costs) > 0 ? array_sum($costs) / count($costs) : 0;

        // Linear regression for trend projection
        $regression = $this->linearRegression($costs);
        $slope = $regression['slope'];
        $intercept = $regression['intercept'];

        $n = count($costs);

        // Project forward
        $forecast7d = 0;
        $forecast30d = 0;

        for ($i = 0; $i < 30; $i++) {
            $projected = max(0, $intercept + $slope * ($n + $i));
            $forecast30d += $projected;

            if ($i < 7) {
                $forecast7d += $projected;
            }
        }

        // Determine trend based on slope relative to average
        $trend = 'stable';
        if ($avgDaily > 0) {
            $slopePercent = ($slope / $avgDaily) * 100;
            if ($slopePercent > 10) {
                $trend = 'increasing';
            } elseif ($slopePercent < -10) {
                $trend = 'decreasing';
            }
        }

        return [
            'daily_costs' => $dailyCosts,
            'forecast_7d' => round($forecast7d, 2),
            'forecast_30d' => round($forecast30d, 2),
            'trend' => $trend,
            'avg_daily' => round($avgDaily, 2),
        ];
    }

    /**
     * Fill date gaps with zero-cost entries.
     */
    private function fillGaps(array $dailyCosts, Carbon $from, Carbon $to): array
    {
        $costMap = [];
        foreach ($dailyCosts as $entry) {
            $costMap[$entry['date']] = $entry['cost'];
        }

        $filled = [];
        $current = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();

        while ($current->lte($end)) {
            $dateStr = $current->toDateString();
            $filled[] = [
                'date' => $dateStr,
                'cost' => $costMap[$dateStr] ?? 0,
            ];
            $current->addDay();
        }

        return $filled;
    }

    /**
     * Simple linear regression: y = intercept + slope * x.
     *
     * @return array{slope: float, intercept: float}
     */
    private function linearRegression(array $values): array
    {
        $n = count($values);

        if ($n < 2) {
            return ['slope' => 0, 'intercept' => $values[0] ?? 0];
        }

        $sumX = 0;
        $sumY = 0;
        $sumXY = 0;
        $sumX2 = 0;

        for ($i = 0; $i < $n; $i++) {
            $sumX += $i;
            $sumY += $values[$i];
            $sumXY += $i * $values[$i];
            $sumX2 += $i * $i;
        }

        $denominator = ($n * $sumX2) - ($sumX * $sumX);

        if ($denominator == 0) {
            return ['slope' => 0, 'intercept' => $sumY / $n];
        }

        $slope = (($n * $sumXY) - ($sumX * $sumY)) / $denominator;
        $intercept = ($sumY - ($slope * $sumX)) / $n;

        return ['slope' => $slope, 'intercept' => $intercept];
    }
}
