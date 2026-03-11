<?php

namespace App\Services\Billing;

use App\Models\Organization;
use App\Models\Plan;

class UsageTracker
{
    /**
     * Record LLM token usage from the test runner.
     */
    public function recordTokenUsage(
        Organization $org,
        int $inputTokens,
        int $outputTokens,
        string $model,
        ?int $userId = null,
    ): void {
        $totalTokens = $inputTokens + $outputTokens;

        $org->recordUsage('llm_tokens', $totalTokens, $model, $userId, [
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
        ]);
    }

    /**
     * Record a sync operation.
     */
    public function recordSync(Organization $org, int $skillCount, ?int $userId = null): void
    {
        $org->recordUsage('sync_operations', $skillCount, null, $userId);
    }

    /**
     * Record an API call.
     */
    public function recordApiCall(Organization $org, string $endpoint, ?int $userId = null): void
    {
        $org->recordUsage('api_calls', 1, null, $userId, [
            'endpoint' => $endpoint,
        ]);
    }

    /**
     * Check if organization has exceeded token budget.
     */
    public function hasExceededTokenBudget(Organization $org): bool
    {
        $plan = Plan::findBySlug($org->plan);

        if (! $plan || $plan->included_tokens_monthly === 0) {
            return false; // unlimited or free plan with no metering
        }

        return $org->currentMonthTokenUsage() >= $plan->included_tokens_monthly;
    }

    /**
     * Get usage summary for the current billing period.
     */
    public function summary(Organization $org): array
    {
        $startOfMonth = now()->startOfMonth();

        $records = $org->usageRecords()
            ->where('recorded_at', '>=', $startOfMonth)
            ->selectRaw('type, SUM(quantity) as total, COUNT(*) as count')
            ->groupBy('type')
            ->get()
            ->keyBy('type');

        $plan = Plan::findBySlug($org->plan);

        return [
            'period_start' => $startOfMonth->toIso8601String(),
            'period_end' => now()->endOfMonth()->toIso8601String(),
            'llm_tokens' => [
                'used' => (int) ($records['llm_tokens']?->total ?? 0),
                'included' => $plan?->included_tokens_monthly ?? 0,
                'requests' => (int) ($records['llm_tokens']?->count ?? 0),
            ],
            'sync_operations' => [
                'count' => (int) ($records['sync_operations']?->total ?? 0),
            ],
            'api_calls' => [
                'count' => (int) ($records['api_calls']?->total ?? 0),
            ],
        ];
    }

    /**
     * Get daily token usage for charting.
     */
    public function dailyTokenUsage(Organization $org, int $days = 30): array
    {
        return $org->usageRecords()
            ->where('type', 'llm_tokens')
            ->where('recorded_at', '>=', now()->subDays($days))
            ->selectRaw('DATE(recorded_at) as date, SUM(quantity) as total')
            ->groupByRaw('DATE(recorded_at)')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date,
                'tokens' => (int) $row->total,
            ])
            ->toArray();
    }
}
