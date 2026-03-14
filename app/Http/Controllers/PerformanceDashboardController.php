<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\Project;
use App\Services\PerformanceAnalytics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PerformanceDashboardController extends Controller
{
    public function __construct(
        private PerformanceAnalytics $analytics,
    ) {}

    /**
     * GET /api/performance/overview
     *
     * Org-wide performance summary.
     */
    public function overview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period' => 'nullable|string|in:7d,30d,90d',
            'agent_id' => 'nullable|integer|exists:agents,id',
            'project_id' => 'nullable|integer|exists:projects,id',
        ]);

        $data = $this->analytics->overview(
            period: $validated['period'] ?? '7d',
            agentId: $validated['agent_id'] ?? null,
            projectId: $validated['project_id'] ?? null,
        );

        return response()->json(['data' => $data]);
    }

    /**
     * GET /api/performance/agents
     *
     * Per-agent performance comparison.
     */
    public function agents(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period' => 'nullable|string|in:7d,30d,90d',
            'project_id' => 'nullable|integer|exists:projects,id',
            'sort_by' => 'nullable|string|in:run_count,success_rate,avg_cost_usd,avg_duration_ms,total_cost_usd,last_run_at',
            'sort_dir' => 'nullable|string|in:asc,desc',
        ]);

        $data = $this->analytics->agentPerformance(
            period: $validated['period'] ?? '7d',
            projectId: $validated['project_id'] ?? null,
            sortBy: $validated['sort_by'] ?? 'run_count',
            sortDir: $validated['sort_dir'] ?? 'desc',
        );

        return response()->json(['data' => $data]);
    }

    /**
     * GET /api/performance/trends
     *
     * Time-series data for charts.
     */
    public function trends(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period' => 'nullable|string|in:7d,30d,90d',
            'agent_id' => 'nullable|integer|exists:agents,id',
            'project_id' => 'nullable|integer|exists:projects,id',
        ]);

        $data = $this->analytics->trends(
            period: $validated['period'] ?? '7d',
            agentId: $validated['agent_id'] ?? null,
            projectId: $validated['project_id'] ?? null,
        );

        return response()->json(['data' => $data]);
    }

    /**
     * GET /api/performance/models
     *
     * Model usage breakdown.
     */
    public function models(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period' => 'nullable|string|in:7d,30d,90d',
            'project_id' => 'nullable|integer|exists:projects,id',
        ]);

        $data = $this->analytics->modelUsage(
            period: $validated['period'] ?? '7d',
            projectId: $validated['project_id'] ?? null,
        );

        return response()->json(['data' => $data]);
    }

    /**
     * GET /api/performance/cost-breakdown
     *
     * Cost analysis by agent, model, or project.
     */
    public function costBreakdown(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'group_by' => 'nullable|string|in:agent,model,project',
            'period' => 'nullable|string|in:7d,30d,90d',
        ]);

        $data = $this->analytics->costBreakdown(
            groupBy: $validated['group_by'] ?? 'agent',
            period: $validated['period'] ?? '7d',
        );

        return response()->json(['data' => $data]);
    }

    /**
     * GET /api/agents/overview
     *
     * Agents-first navigation dashboard data.
     */
    public function agentsOverview(): JsonResponse
    {
        $data = $this->analytics->agentsOverview();

        return response()->json(['data' => $data]);
    }

    /**
     * GET /api/projects/{project}/agent-team
     *
     * Agent team overview for a project.
     */
    public function agentTeam(Project $project): JsonResponse
    {
        $data = $this->analytics->agentTeamOverview($project->id);

        return response()->json(['data' => $data]);
    }

    /**
     * GET /api/onboarding/status
     *
     * Returns onboarding progress.
     */
    public function onboardingStatus(Request $request): JsonResponse
    {
        $data = $this->analytics->onboardingStatus($request->user()->id);

        return response()->json(['data' => $data]);
    }

    /**
     * POST /api/onboarding/quick-start
     *
     * Creates a starter project with a pre-configured agent.
     */
    public function quickStart(Request $request): JsonResponse
    {
        $project = Project::create([
            'name' => 'My First Project',
            'path' => '/tmp/my-first-project',
        ]);

        // Find or create a default agent
        $agent = Agent::first();
        if (! $agent) {
            $agent = Agent::create([
                'name' => 'My First Agent',
                'slug' => 'my-first-agent',
                'role' => 'assistant',
                'model' => 'claude-sonnet-4-6',
                'base_instructions' => 'You are a helpful AI assistant.',
                'autonomy_level' => 'semi_autonomous',
            ]);
        }

        // Attach agent to project
        $project->agents()->attach($agent->id, ['is_enabled' => true]);

        return response()->json([
            'data' => [
                'project_id' => $project->id,
                'agent_id' => $agent->id,
                'project_name' => $project->name,
                'agent_name' => $agent->name,
            ],
        ], 201);
    }
}
