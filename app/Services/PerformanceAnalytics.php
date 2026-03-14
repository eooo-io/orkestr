<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PerformanceAnalytics
{
    /**
     * Get the date range start based on a period string.
     */
    public function periodStart(string $period): Carbon
    {
        return match ($period) {
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            '90d' => now()->subDays(90),
            default => now()->subDays(7),
        };
    }

    /**
     * Org-wide performance overview.
     */
    public function overview(string $period = '7d', ?int $agentId = null, ?int $projectId = null): array
    {
        $since = $this->periodStart($period);

        $query = DB::table('execution_runs')
            ->where('created_at', '>=', $since);

        if ($agentId) {
            $query->where('agent_id', $agentId);
        }
        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        $stats = $query->selectRaw('
            COUNT(*) as total_runs,
            SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as successful_runs,
            SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as failed_runs,
            COALESCE(SUM(total_cost_microcents), 0) as total_cost_microcents,
            COALESCE(AVG(total_cost_microcents), 0) as avg_cost_microcents,
            COALESCE(SUM(total_tokens), 0) as total_tokens,
            COALESCE(AVG(total_tokens), 0) as avg_tokens_per_run,
            COALESCE(AVG(total_duration_ms), 0) as avg_duration_ms
        ', ['completed', 'failed'])->first();

        $totalRuns = (int) $stats->total_runs;
        $successfulRuns = (int) $stats->successful_runs;
        $failedRuns = (int) $stats->failed_runs;

        // p95 duration: approximate via ORDER BY + OFFSET
        $p95Duration = 0;
        if ($totalRuns > 0) {
            $p95Index = (int) ceil($totalRuns * 0.95) - 1;
            $p95Query = DB::table('execution_runs')
                ->where('created_at', '>=', $since)
                ->whereNotNull('total_duration_ms');

            if ($agentId) {
                $p95Query->where('agent_id', $agentId);
            }
            if ($projectId) {
                $p95Query->where('project_id', $projectId);
            }

            $p95Row = $p95Query->orderBy('total_duration_ms')
                ->offset($p95Index)
                ->limit(1)
                ->value('total_duration_ms');

            $p95Duration = (int) ($p95Row ?? 0);
        }

        // Active agents (agents with runs in last 7 days)
        $activeAgentsQuery = DB::table('execution_runs')
            ->where('created_at', '>=', now()->subDays(7));
        if ($projectId) {
            $activeAgentsQuery->where('project_id', $projectId);
        }
        $activeAgents = $activeAgentsQuery
            ->distinct('agent_id')
            ->count('agent_id');

        return [
            'total_runs' => $totalRuns,
            'successful_runs' => $successfulRuns,
            'failed_runs' => $failedRuns,
            'success_rate' => $totalRuns > 0 ? round($successfulRuns / $totalRuns * 100, 1) : 0,
            'total_cost_usd' => round((int) $stats->total_cost_microcents / 1_000_000, 6),
            'avg_cost_per_run_usd' => round((float) $stats->avg_cost_microcents / 1_000_000, 6),
            'total_tokens' => (int) $stats->total_tokens,
            'avg_tokens_per_run' => (int) round((float) $stats->avg_tokens_per_run),
            'avg_duration_ms' => (int) round((float) $stats->avg_duration_ms),
            'p95_duration_ms' => $p95Duration,
            'active_agents' => $activeAgents,
        ];
    }

    /**
     * Per-agent performance comparison.
     */
    public function agentPerformance(
        string $period = '7d',
        ?int $projectId = null,
        string $sortBy = 'run_count',
        string $sortDir = 'desc',
    ): array {
        $since = $this->periodStart($period);

        $query = DB::table('execution_runs')
            ->join('agents', 'execution_runs.agent_id', '=', 'agents.id')
            ->where('execution_runs.created_at', '>=', $since);

        if ($projectId) {
            $query->where('execution_runs.project_id', $projectId);
        }

        $allowedSort = [
            'run_count', 'success_rate', 'avg_cost_usd',
            'avg_duration_ms', 'total_cost_usd', 'last_run_at',
        ];
        $sortColumn = in_array($sortBy, $allowedSort) ? $sortBy : 'run_count';

        $agents = $query->groupBy('execution_runs.agent_id', 'agents.name')
            ->selectRaw('
                execution_runs.agent_id,
                agents.name as agent_name,
                COUNT(*) as run_count,
                ROUND(SUM(CASE WHEN execution_runs.status = ? THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 1) as success_rate,
                ROUND(AVG(execution_runs.total_cost_microcents) / 1000000, 6) as avg_cost_usd,
                ROUND(AVG(execution_runs.total_duration_ms), 0) as avg_duration_ms,
                ROUND(SUM(execution_runs.total_cost_microcents) / 1000000, 6) as total_cost_usd,
                MAX(execution_runs.created_at) as last_run_at
            ', ['completed'])
            ->orderBy($sortColumn, $sortDir)
            ->get();

        return $agents->map(fn ($row) => [
            'agent_id' => $row->agent_id,
            'agent_name' => $row->agent_name,
            'run_count' => (int) $row->run_count,
            'success_rate' => (float) $row->success_rate,
            'avg_cost_usd' => (float) $row->avg_cost_usd,
            'avg_duration_ms' => (int) $row->avg_duration_ms,
            'total_cost_usd' => (float) $row->total_cost_usd,
            'last_run_at' => $row->last_run_at,
        ])->toArray();
    }

    /**
     * Time-series data for trend charts.
     */
    public function trends(
        string $period = '7d',
        ?int $agentId = null,
        ?int $projectId = null,
    ): array {
        $since = $this->periodStart($period);

        $query = DB::table('execution_runs')
            ->where('created_at', '>=', $since);

        if ($agentId) {
            $query->where('agent_id', $agentId);
        }
        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        $rows = $query->selectRaw('
                DATE(created_at) as date,
                COUNT(*) as run_count,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as success_count,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as failure_count,
                ROUND(SUM(total_cost_microcents) / 1000000, 6) as total_cost_usd,
                SUM(total_tokens) as total_tokens
            ', ['completed', 'failed'])
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get();

        return $rows->map(fn ($row) => [
            'date' => $row->date,
            'run_count' => (int) $row->run_count,
            'success_count' => (int) $row->success_count,
            'failure_count' => (int) $row->failure_count,
            'total_cost_usd' => (float) $row->total_cost_usd,
            'total_tokens' => (int) $row->total_tokens,
        ])->toArray();
    }

    /**
     * Model usage breakdown from execution steps.
     */
    public function modelUsage(string $period = '7d', ?int $projectId = null): array
    {
        $since = $this->periodStart($period);

        $query = DB::table('execution_steps')
            ->join('execution_runs', 'execution_steps.execution_run_id', '=', 'execution_runs.id')
            ->where('execution_runs.created_at', '>=', $since)
            ->whereNotNull('execution_steps.model_used');

        if ($projectId) {
            $query->where('execution_runs.project_id', $projectId);
        }

        $rows = $query->groupBy('execution_steps.model_used')
            ->selectRaw('
                execution_steps.model_used as model_name,
                COUNT(*) as step_count,
                COUNT(DISTINCT execution_runs.id) as run_count,
                COALESCE(SUM(execution_runs.total_tokens), 0) as total_tokens,
                ROUND(SUM(execution_runs.total_cost_microcents) / 1000000, 6) as total_cost_usd,
                ROUND(AVG(execution_steps.duration_ms), 0) as avg_latency_ms
            ')
            ->orderByDesc('run_count')
            ->get();

        return $rows->map(fn ($row) => [
            'model_name' => $row->model_name,
            'run_count' => (int) $row->run_count,
            'total_tokens' => (int) $row->total_tokens,
            'total_cost_usd' => (float) $row->total_cost_usd,
            'avg_latency_ms' => (int) $row->avg_latency_ms,
        ])->toArray();
    }

    /**
     * Cost breakdown grouped by agent, model, or project.
     */
    public function costBreakdown(string $groupBy = 'agent', string $period = '7d'): array
    {
        $since = $this->periodStart($period);

        $query = DB::table('execution_runs')
            ->where('execution_runs.created_at', '>=', $since);

        $rows = match ($groupBy) {
            'agent' => $query->join('agents', 'execution_runs.agent_id', '=', 'agents.id')
                ->groupBy('execution_runs.agent_id', 'agents.name')
                ->selectRaw('
                    execution_runs.agent_id as group_id,
                    agents.name as group_name,
                    COUNT(*) as run_count,
                    ROUND(SUM(execution_runs.total_cost_microcents) / 1000000, 6) as total_cost_usd,
                    COALESCE(SUM(execution_runs.total_tokens), 0) as total_tokens
                ')
                ->orderByDesc('total_cost_usd')
                ->get(),

            'model' => $query->groupBy('execution_runs.model_used')
                ->selectRaw('
                    execution_runs.model_used as group_id,
                    execution_runs.model_used as group_name,
                    COUNT(*) as run_count,
                    ROUND(SUM(execution_runs.total_cost_microcents) / 1000000, 6) as total_cost_usd,
                    COALESCE(SUM(execution_runs.total_tokens), 0) as total_tokens
                ')
                ->orderByDesc('total_cost_usd')
                ->get(),

            'project' => $query->join('projects', 'execution_runs.project_id', '=', 'projects.id')
                ->groupBy('execution_runs.project_id', 'projects.name')
                ->selectRaw('
                    execution_runs.project_id as group_id,
                    projects.name as group_name,
                    COUNT(*) as run_count,
                    ROUND(SUM(execution_runs.total_cost_microcents) / 1000000, 6) as total_cost_usd,
                    COALESCE(SUM(execution_runs.total_tokens), 0) as total_tokens
                ')
                ->orderByDesc('total_cost_usd')
                ->get(),

            default => collect(),
        };

        return $rows->map(fn ($row) => [
            'group_id' => $row->group_id,
            'group_name' => $row->group_name,
            'run_count' => (int) $row->run_count,
            'total_cost_usd' => (float) $row->total_cost_usd,
            'total_tokens' => (int) $row->total_tokens,
        ])->toArray();
    }

    /**
     * Agents-first overview data.
     */
    public function agentsOverview(): array
    {
        $totalAgents = DB::table('agents')->count();

        $activeAgents = DB::table('execution_runs')
            ->where('created_at', '>=', now()->subDays(7))
            ->distinct('agent_id')
            ->count('agent_id');

        $todayStats = DB::table('execution_runs')
            ->where('created_at', '>=', now()->startOfDay())
            ->selectRaw('
                COUNT(*) as total_runs_today,
                COALESCE(ROUND(SUM(total_cost_microcents) / 1000000, 6), 0) as total_cost_today
            ')
            ->first();

        $recentRuns = DB::table('execution_runs')
            ->join('agents', 'execution_runs.agent_id', '=', 'agents.id')
            ->orderByDesc('execution_runs.created_at')
            ->limit(5)
            ->select([
                'execution_runs.id',
                'execution_runs.uuid',
                'agents.name as agent_name',
                'execution_runs.status',
                'execution_runs.total_duration_ms',
                'execution_runs.total_cost_microcents',
                'execution_runs.created_at',
            ])
            ->get();

        $topAgents = DB::table('execution_runs')
            ->join('agents', 'execution_runs.agent_id', '=', 'agents.id')
            ->where('execution_runs.created_at', '>=', now()->subDays(7))
            ->groupBy('execution_runs.agent_id', 'agents.name')
            ->selectRaw('
                execution_runs.agent_id,
                agents.name as agent_name,
                COUNT(*) as run_count
            ')
            ->orderByDesc('run_count')
            ->limit(3)
            ->get();

        return [
            'total_agents' => $totalAgents,
            'active_agents' => $activeAgents,
            'total_runs_today' => (int) $todayStats->total_runs_today,
            'total_cost_today' => (float) $todayStats->total_cost_today,
            'recent_runs' => $recentRuns->map(fn ($r) => [
                'id' => $r->id,
                'uuid' => $r->uuid,
                'agent_name' => $r->agent_name,
                'status' => $r->status,
                'duration_ms' => (int) $r->total_duration_ms,
                'cost_usd' => round((int) $r->total_cost_microcents / 1_000_000, 6),
                'created_at' => $r->created_at,
            ])->toArray(),
            'top_agents' => $topAgents->map(fn ($a) => [
                'agent_id' => $a->agent_id,
                'agent_name' => $a->agent_name,
                'run_count' => (int) $a->run_count,
            ])->toArray(),
        ];
    }

    /**
     * Agent team overview for a specific project.
     */
    public function agentTeamOverview(int $projectId): array
    {
        // Get agents assigned to this project
        $agents = DB::table('project_agent')
            ->join('agents', 'project_agent.agent_id', '=', 'agents.id')
            ->where('project_agent.project_id', $projectId)
            ->select([
                'agents.id',
                'agents.name',
                'agents.slug',
                'agents.icon',
                'agents.model',
                'agents.autonomy_level',
                'project_agent.is_enabled',
            ])
            ->get();

        $agentIds = $agents->pluck('id')->toArray();

        // Get performance stats per agent
        $perfStats = DB::table('execution_runs')
            ->where('project_id', $projectId)
            ->whereIn('agent_id', $agentIds)
            ->groupBy('agent_id')
            ->selectRaw('
                agent_id,
                COUNT(*) as run_count,
                ROUND(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 1) as success_rate,
                ROUND(AVG(total_cost_microcents) / 1000000, 6) as avg_cost_usd,
                MAX(created_at) as last_run_at
            ', ['completed'])
            ->get()
            ->keyBy('agent_id');

        // Get next scheduled run per agent
        $schedules = DB::table('agent_schedules')
            ->where('project_id', $projectId)
            ->whereIn('agent_id', $agentIds)
            ->where('is_enabled', true)
            ->whereNotNull('next_run_at')
            ->groupBy('agent_id')
            ->selectRaw('agent_id, MIN(next_run_at) as next_run_at')
            ->get()
            ->keyBy('agent_id');

        // Get workflow connections
        $workflowConnections = DB::table('workflow_steps')
            ->join('workflows', 'workflow_steps.workflow_id', '=', 'workflows.id')
            ->where('workflows.project_id', $projectId)
            ->whereIn('workflow_steps.agent_id', $agentIds)
            ->select(['workflow_steps.agent_id', 'workflows.name as workflow_name', 'workflows.id as workflow_id'])
            ->get()
            ->groupBy('agent_id');

        return $agents->map(function ($agent) use ($perfStats, $schedules, $workflowConnections) {
            $perf = $perfStats->get($agent->id);
            $schedule = $schedules->get($agent->id);
            $workflows = $workflowConnections->get($agent->id, collect());

            // Determine status based on recent runs
            $status = 'idle';
            if ($perf && $perf->last_run_at) {
                $lastRun = Carbon::parse($perf->last_run_at);
                if ($lastRun->gt(now()->subHours(1))) {
                    // Check last run status
                    $lastStatus = DB::table('execution_runs')
                        ->where('agent_id', $agent->id)
                        ->orderByDesc('created_at')
                        ->value('status');
                    $status = $lastStatus === 'failed' ? 'error' : 'active';
                }
            }

            return [
                'id' => $agent->id,
                'name' => $agent->name,
                'slug' => $agent->slug,
                'icon' => $agent->icon,
                'model' => $agent->model,
                'autonomy_level' => $agent->autonomy_level,
                'is_enabled' => (bool) $agent->is_enabled,
                'performance' => [
                    'run_count' => $perf ? (int) $perf->run_count : 0,
                    'success_rate' => $perf ? (float) $perf->success_rate : 0,
                    'avg_cost_usd' => $perf ? (float) $perf->avg_cost_usd : 0,
                    'last_run_at' => $perf?->last_run_at,
                ],
                'schedule' => [
                    'next_run_at' => $schedule?->next_run_at,
                ],
                'status' => $status,
                'workflows' => $workflows->map(fn ($w) => [
                    'workflow_id' => $w->workflow_id,
                    'workflow_name' => $w->workflow_name,
                ])->values()->toArray(),
            ];
        })->toArray();
    }

    /**
     * Onboarding status for the current user.
     */
    public function onboardingStatus(int $userId): array
    {
        $hasProject = DB::table('projects')->exists();
        $hasAgent = DB::table('agents')->exists();
        $hasSkill = DB::table('skills')->exists();
        $hasRun = DB::table('execution_runs')->exists();
        $hasSchedule = DB::table('agent_schedules')->exists();

        $completedSteps = collect([
            $hasProject,
            $hasAgent,
            $hasSkill,
            $hasRun,
            $hasSchedule,
        ])->filter()->count();

        return [
            'has_project' => $hasProject,
            'has_agent' => $hasAgent,
            'has_skill' => $hasSkill,
            'has_run' => $hasRun,
            'has_schedule' => $hasSchedule,
            'completed_steps' => $completedSteps,
            'total_steps' => 5,
        ];
    }
}
