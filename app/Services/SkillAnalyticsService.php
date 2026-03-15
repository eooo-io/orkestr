<?php

namespace App\Services;

use App\Models\Skill;
use App\Models\SkillAnalytic;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SkillAnalyticsService
{
    /**
     * Record a test run result for a skill.
     */
    public function record(
        int $skillId,
        bool $passed,
        ?float $tokens = null,
        ?float $costMicrocents = null,
        ?float $latencyMs = null,
        ?int $organizationId = null,
    ): SkillAnalytic {
        $date = Carbon::today();

        $analytic = SkillAnalytic::where('skill_id', $skillId)
            ->whereDate('date', $date)
            ->first();

        if (! $analytic) {
            $analytic = SkillAnalytic::create([
                'skill_id' => $skillId,
                'date' => $date,
                'organization_id' => $organizationId,
                'test_runs' => 0,
                'pass_count' => 0,
                'fail_count' => 0,
            ]);
        }

        $analytic->test_runs++;
        if ($passed) {
            $analytic->pass_count++;
        } else {
            $analytic->fail_count++;
        }

        // Running average for tokens, cost, latency
        if ($tokens !== null) {
            $analytic->avg_tokens = $analytic->avg_tokens
                ? (($analytic->avg_tokens * ($analytic->test_runs - 1)) + $tokens) / $analytic->test_runs
                : $tokens;
        }
        if ($costMicrocents !== null) {
            $analytic->avg_cost_microcents = $analytic->avg_cost_microcents
                ? (($analytic->avg_cost_microcents * ($analytic->test_runs - 1)) + $costMicrocents) / $analytic->test_runs
                : $costMicrocents;
        }
        if ($latencyMs !== null) {
            $analytic->avg_latency_ms = $analytic->avg_latency_ms
                ? (($analytic->avg_latency_ms * ($analytic->test_runs - 1)) + $latencyMs) / $analytic->test_runs
                : $latencyMs;
        }

        $analytic->save();

        return $analytic;
    }

    /**
     * Get aggregated stats for a skill over a date range.
     */
    public function getSkillStats(int $skillId, ?string $from = null, ?string $to = null): array
    {
        $query = SkillAnalytic::where('skill_id', $skillId);

        if ($from) {
            $query->where('date', '>=', $from);
        }
        if ($to) {
            $query->where('date', '<=', $to);
        }

        $data = $query->get();

        return [
            'total_runs' => $data->sum('test_runs'),
            'total_pass' => $data->sum('pass_count'),
            'total_fail' => $data->sum('fail_count'),
            'pass_rate' => $data->sum('test_runs') > 0
                ? round($data->sum('pass_count') / $data->sum('test_runs') * 100, 1)
                : 0,
            'avg_tokens' => round($data->avg('avg_tokens') ?? 0, 1),
            'avg_cost_microcents' => round($data->avg('avg_cost_microcents') ?? 0, 1),
            'avg_latency_ms' => round($data->avg('avg_latency_ms') ?? 0, 1),
            'days' => $data->count(),
        ];
    }

    /**
     * Get top skills by test run count.
     */
    public function getTopSkills(int $limit = 10, ?int $organizationId = null): Collection
    {
        $query = SkillAnalytic::query()
            ->selectRaw('skill_id, SUM(test_runs) as total_runs, SUM(pass_count) as total_pass, SUM(fail_count) as total_fail')
            ->groupBy('skill_id')
            ->orderByDesc('total_runs')
            ->limit($limit);

        if ($organizationId) {
            $query->where('organization_id', $organizationId);
        }

        return $query->get()->map(function ($row) {
            $skill = Skill::find($row->skill_id);

            return [
                'skill_id' => $row->skill_id,
                'skill_name' => $skill?->name,
                'total_runs' => (int) $row->total_runs,
                'total_pass' => (int) $row->total_pass,
                'total_fail' => (int) $row->total_fail,
                'pass_rate' => $row->total_runs > 0
                    ? round($row->total_pass / $row->total_runs * 100, 1)
                    : 0,
            ];
        });
    }

    /**
     * Get daily trends over last N days.
     */
    public function getTrends(int $days = 30, ?int $organizationId = null): Collection
    {
        $from = Carbon::today()->subDays($days)->toDateString();

        $query = SkillAnalytic::query()
            ->selectRaw('date, SUM(test_runs) as total_runs, SUM(pass_count) as total_pass, SUM(fail_count) as total_fail')
            ->where('date', '>=', $from)
            ->groupBy('date')
            ->orderBy('date');

        if ($organizationId) {
            $query->where('organization_id', $organizationId);
        }

        return $query->get();
    }
}
