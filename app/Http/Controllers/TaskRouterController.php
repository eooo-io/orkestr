<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\AgentCapability;
use App\Models\Project;
use App\Models\RoutingDecision;
use App\Models\RoutingRule;
use App\Services\Routing\CapabilityTracker;
use App\Services\Routing\SlaMonitor;
use App\Services\Routing\SmartTaskRouter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskRouterController extends Controller
{
    public function __construct(
        protected SmartTaskRouter $router,
        protected CapabilityTracker $capabilityTracker,
        protected SlaMonitor $slaMonitor,
    ) {}

    /**
     * GET /api/projects/{project}/routing-rules
     * List routing rules for a project.
     */
    public function rules(Project $project): JsonResponse
    {
        $rules = RoutingRule::where('project_id', $project->id)
            ->orderByDesc('priority')
            ->get();

        return response()->json(['data' => $rules]);
    }

    /**
     * POST /api/projects/{project}/routing-rules
     * Create a routing rule.
     */
    public function storeRule(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'conditions' => 'required|array',
            'conditions.task_type' => 'nullable|string|max:100',
            'conditions.priority' => 'nullable|array',
            'conditions.tags' => 'nullable|array',
            'conditions.keywords' => 'nullable|array',
            'target_strategy' => 'required|string|in:best_fit,round_robin,least_loaded,cost_optimized,fastest',
            'target_agents' => 'nullable|array',
            'target_agents.*' => 'integer|exists:agents,id',
            'sla_config' => 'nullable|array',
            'sla_config.max_wait_seconds' => 'nullable|integer|min:1',
            'sla_config.max_cost' => 'nullable|integer|min:0',
            'sla_config.priority_boost' => 'nullable|boolean',
            'priority' => 'nullable|integer|min:0',
            'enabled' => 'nullable|boolean',
        ]);

        $validated['project_id'] = $project->id;

        $rule = RoutingRule::create($validated);

        return response()->json(['data' => $rule], 201);
    }

    /**
     * PUT /api/routing-rules/{routingRule}
     * Update a routing rule.
     */
    public function updateRule(Request $request, RoutingRule $routingRule): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'conditions' => 'sometimes|required|array',
            'conditions.task_type' => 'nullable|string|max:100',
            'conditions.priority' => 'nullable|array',
            'conditions.tags' => 'nullable|array',
            'conditions.keywords' => 'nullable|array',
            'target_strategy' => 'sometimes|required|string|in:best_fit,round_robin,least_loaded,cost_optimized,fastest',
            'target_agents' => 'nullable|array',
            'target_agents.*' => 'integer|exists:agents,id',
            'sla_config' => 'nullable|array',
            'sla_config.max_wait_seconds' => 'nullable|integer|min:1',
            'sla_config.max_cost' => 'nullable|integer|min:0',
            'sla_config.priority_boost' => 'nullable|boolean',
            'priority' => 'nullable|integer|min:0',
            'enabled' => 'nullable|boolean',
        ]);

        $routingRule->update($validated);

        return response()->json(['data' => $routingRule->fresh()]);
    }

    /**
     * DELETE /api/routing-rules/{routingRule}
     * Delete a routing rule.
     */
    public function destroyRule(RoutingRule $routingRule): JsonResponse
    {
        $routingRule->delete();

        return response()->json(['message' => 'Routing rule deleted']);
    }

    /**
     * GET /api/projects/{project}/capabilities
     * List all agent capabilities for a project.
     */
    public function capabilities(Project $project): JsonResponse
    {
        $capabilities = AgentCapability::where('project_id', $project->id)
            ->with('agent:id,name,slug,icon')
            ->orderBy('agent_id')
            ->orderByDesc('proficiency')
            ->get()
            ->map(fn ($cap) => [
                'id' => $cap->id,
                'agent_id' => $cap->agent_id,
                'agent_name' => $cap->agent?->name,
                'agent_slug' => $cap->agent?->slug,
                'agent_icon' => $cap->agent?->icon,
                'capability' => $cap->capability,
                'proficiency' => (float) $cap->proficiency,
                'success_rate' => (float) $cap->success_rate,
                'avg_duration_ms' => $cap->avg_duration_ms,
                'avg_cost_microcents' => $cap->avg_cost_microcents,
                'task_count' => $cap->task_count,
                'last_used_at' => $cap->last_used_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $capabilities]);
    }

    /**
     * GET /api/agents/{agent}/capabilities
     * Get a single agent's capabilities.
     */
    public function agentCapabilities(Agent $agent, Request $request): JsonResponse
    {
        $projectId = $request->input('project_id');

        $query = AgentCapability::where('agent_id', $agent->id);

        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        $capabilities = $query->orderByDesc('proficiency')->get();

        return response()->json(['data' => $capabilities]);
    }

    /**
     * POST /api/agents/{agent}/infer-capabilities
     * Trigger capability inference for an agent.
     */
    public function inferCapabilities(Request $request, Agent $agent): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => 'required|integer|exists:projects,id',
        ]);

        $capabilities = $this->capabilityTracker->inferCapabilities($agent, $validated['project_id']);

        return response()->json([
            'data' => $capabilities->map(fn ($cap) => [
                'id' => $cap->id,
                'capability' => $cap->capability,
                'proficiency' => (float) $cap->proficiency,
                'success_rate' => (float) $cap->success_rate,
                'task_count' => $cap->task_count,
            ]),
            'message' => sprintf('Inferred %d capabilities for %s', $capabilities->count(), $agent->name),
        ]);
    }

    /**
     * GET /api/projects/{project}/routing-decisions
     * Paginated routing decision audit log.
     */
    public function decisions(Request $request, Project $project): JsonResponse
    {
        $decisions = RoutingDecision::whereHas('task', function ($q) use ($project) {
            $q->where('project_id', $project->id);
        })
            ->with(['selectedAgent:id,name,slug', 'task:id,title,priority,status'])
            ->orderByDesc('created_at')
            ->paginate($request->input('per_page', 20));

        return response()->json([
            'data' => collect($decisions->items())->map(fn ($d) => [
                'id' => $d->id,
                'task_id' => $d->task_id,
                'task_title' => $d->task?->title,
                'task_priority' => $d->task?->priority,
                'selected_agent_id' => $d->selected_agent_id,
                'selected_agent_name' => $d->selectedAgent?->name,
                'strategy_used' => $d->strategy_used,
                'candidates' => $d->candidates,
                'reasoning' => $d->reasoning,
                'sla_met' => $d->sla_met,
                'created_at' => $d->created_at?->toIso8601String(),
            ]),
            'meta' => [
                'current_page' => $decisions->currentPage(),
                'last_page' => $decisions->lastPage(),
                'total' => $decisions->total(),
            ],
        ]);
    }

    /**
     * POST /api/projects/{project}/routing/simulate
     * Dry-run: simulate routing for a task description.
     */
    public function simulate(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'description' => 'required|string|max:5000',
            'task_type' => 'required|string|max:100',
            'priority' => 'nullable|string|in:low,medium,high,critical',
        ]);

        $result = $this->router->simulate(
            $validated['description'],
            $validated['task_type'],
            $project->id,
            $validated['priority'] ?? 'medium',
        );

        return response()->json(['data' => $result]);
    }

    /**
     * GET /api/projects/{project}/sla-summary
     * Get SLA summary for a project.
     */
    public function slaSummary(Project $project): JsonResponse
    {
        $summary = $this->slaMonitor->getSlaSummary($project->id);

        return response()->json(['data' => $summary]);
    }
}
