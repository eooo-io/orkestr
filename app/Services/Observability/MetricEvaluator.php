<?php

namespace App\Services\Observability;

use App\Models\CustomMetric;
use App\Models\ExecutionRun;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MetricEvaluator
{
    /**
     * Evaluate a custom metric and return time-series data points.
     *
     * @return array<int, array{timestamp: string, value: float}>
     */
    public function evaluate(CustomMetric $metric, ?string $from = null, ?string $to = null): array
    {
        $toDate = $to ? Carbon::parse($to) : now();
        $fromDate = $from ? Carbon::parse($from) : $toDate->copy()->subDays(7);

        $config = $metric->query_config ?? [];
        $groupBy = $this->resolveGrouping($fromDate, $toDate);

        return match ($metric->query_type) {
            'count_runs' => $this->countRuns($fromDate, $toDate, $groupBy, $config),
            'sum_tokens' => $this->sumTokens($fromDate, $toDate, $groupBy, $config),
            'avg_cost' => $this->avgCost($fromDate, $toDate, $groupBy, $config),
            'avg_duration' => $this->avgDuration($fromDate, $toDate, $groupBy, $config),
            'error_rate' => $this->errorRate($fromDate, $toDate, $groupBy, $config),
            'custom' => [],
            default => [],
        };
    }

    /**
     * Evaluate a metric for a specific window and return a single aggregate value.
     */
    public function evaluateScalar(CustomMetric $metric, int $windowMinutes): float
    {
        $to = now();
        $from = $to->copy()->subMinutes($windowMinutes);
        $config = $metric->query_config ?? [];

        $query = $this->baseQuery($from, $to, $config);

        return match ($metric->query_type) {
            'count_runs' => (float) $query->count(),
            'sum_tokens' => (float) $query->sum('total_tokens'),
            'avg_cost' => (float) ($query->avg('total_cost_microcents') ?? 0) / 100,
            'avg_duration' => (float) ($query->avg('total_duration_ms') ?? 0),
            'error_rate' => $this->computeErrorRate($from, $to, $config),
            default => 0.0,
        };
    }

    /**
     * Decide whether to group by hour or day based on the time range.
     */
    private function resolveGrouping(Carbon $from, Carbon $to): string
    {
        $diffDays = $from->diffInDays($to);

        return $diffDays <= 2 ? 'hour' : 'day';
    }

    /**
     * Build a base query with common filters applied.
     */
    private function baseQuery(Carbon $from, Carbon $to, array $config): \Illuminate\Database\Eloquent\Builder
    {
        $query = ExecutionRun::query()
            ->whereBetween('created_at', [$from, $to]);

        if (! empty($config['project_id'])) {
            $query->where('project_id', $config['project_id']);
        }

        if (! empty($config['agent_id'])) {
            $query->where('agent_id', $config['agent_id']);
        }

        if (! empty($config['model'])) {
            $query->where('model_used', $config['model']);
        }

        return $query;
    }

    /**
     * Date grouping expression for SQL.
     */
    private function dateGroupExpr(string $groupBy): string
    {
        if ($groupBy === 'hour') {
            return "DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00')";
        }

        return 'DATE(created_at)';
    }

    /**
     * Format results from grouped queries into time-series points.
     *
     * @return array<int, array{timestamp: string, value: float}>
     */
    private function formatTimeSeries($results): array
    {
        return collect($results)->map(fn ($row) => [
            'timestamp' => $row->period,
            'value' => round((float) $row->value, 4),
        ])->values()->all();
    }

    private function countRuns(Carbon $from, Carbon $to, string $groupBy, array $config): array
    {
        $expr = $this->dateGroupExpr($groupBy);

        $results = $this->baseQuery($from, $to, $config)
            ->select(DB::raw("{$expr} as period"), DB::raw('COUNT(*) as value'))
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        return $this->formatTimeSeries($results);
    }

    private function sumTokens(Carbon $from, Carbon $to, string $groupBy, array $config): array
    {
        $expr = $this->dateGroupExpr($groupBy);

        $results = $this->baseQuery($from, $to, $config)
            ->select(DB::raw("{$expr} as period"), DB::raw('SUM(total_tokens) as value'))
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        return $this->formatTimeSeries($results);
    }

    private function avgCost(Carbon $from, Carbon $to, string $groupBy, array $config): array
    {
        $expr = $this->dateGroupExpr($groupBy);

        $results = $this->baseQuery($from, $to, $config)
            ->select(DB::raw("{$expr} as period"), DB::raw('AVG(total_cost_microcents) / 100 as value'))
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        return $this->formatTimeSeries($results);
    }

    private function avgDuration(Carbon $from, Carbon $to, string $groupBy, array $config): array
    {
        $expr = $this->dateGroupExpr($groupBy);

        $results = $this->baseQuery($from, $to, $config)
            ->select(DB::raw("{$expr} as period"), DB::raw('AVG(total_duration_ms) as value'))
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        return $this->formatTimeSeries($results);
    }

    private function errorRate(Carbon $from, Carbon $to, string $groupBy, array $config): array
    {
        $expr = $this->dateGroupExpr($groupBy);

        $results = $this->baseQuery($from, $to, $config)
            ->select(
                DB::raw("{$expr} as period"),
                DB::raw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(*), 0) as value"),
            )
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        return $this->formatTimeSeries($results);
    }

    private function computeErrorRate(Carbon $from, Carbon $to, array $config): float
    {
        $query = $this->baseQuery($from, $to, $config);
        $total = $query->count();

        if ($total === 0) {
            return 0.0;
        }

        $failed = $this->baseQuery($from, $to, $config)->where('status', 'failed')->count();

        return round(($failed / $total) * 100, 4);
    }
}
