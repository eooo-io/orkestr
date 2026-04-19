<?php

namespace App\Http\Controllers;

use App\Http\Resources\AgentResource;
use App\Models\Agent;
use App\Models\Project;
use App\Models\ProjectAgent;
use App\Services\AgentComposeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\Yaml\Yaml;

class AgentController extends Controller
{
    public function __construct(
        protected AgentComposeService $composeService,
    ) {}

    /**
     * List all global agents.
     */
    public function index(): JsonResponse
    {
        $agents = Agent::with('parentAgent', 'childAgents')
            ->orderBy('sort_order')
            ->get();

        return AgentResource::collection($agents)->response();
    }

    /**
     * Show a single agent.
     */
    public function show(Agent $agent): JsonResponse
    {
        $agent->load('parentAgent', 'childAgents');

        return (new AgentResource($agent))->response();
    }

    /**
     * Create a new agent.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'role' => 'required|string|max:255',
            'description' => 'nullable|string',
            'base_instructions' => 'nullable|string',
            'persona_prompt' => 'nullable|string',
            'persona' => 'nullable|array',
            'persona.name' => 'nullable|string|max:255',
            'persona.aliases' => 'nullable|array',
            'persona.aliases.*' => 'string|max:100',
            'persona.avatar' => 'nullable|string|max:50',
            'persona.personality' => 'nullable|string|max:500',
            'persona.bio' => 'nullable|string|max:1000',
            'model' => 'nullable|string|max:100',
            'icon' => 'nullable|string|max:50',
            'sort_order' => 'nullable|integer',
            'objective_template' => 'nullable|string',
            'success_criteria' => 'nullable|array',
            'max_iterations' => 'nullable|integer|min:1|max:1000',
            'timeout_seconds' => 'nullable|integer|min:1|max:3600',
            'input_schema' => 'nullable|array',
            'memory_sources' => 'nullable|array',
            'context_strategy' => 'nullable|string|in:full,summary,sliding_window,rag',
            'planning_mode' => 'nullable|string|in:none,act,plan_then_act,react',
            'temperature' => 'nullable|numeric|min:0|max:2',
            'system_prompt' => 'nullable|string',
            'eval_criteria' => 'nullable|array',
            'output_schema' => 'nullable|array',
            'loop_condition' => 'nullable|string|in:goal_met,max_iterations,timeout,manual',
            'parent_agent_id' => 'nullable|integer|exists:agents,id',
            'delegation_rules' => 'nullable|array',
            'can_delegate' => 'nullable|boolean',
            'custom_tools' => 'nullable|array',
            'is_template' => 'nullable|boolean',
        ]);

        $agent = Agent::create($validated);
        $agent->load('parentAgent');

        return (new AgentResource($agent))->response()->setStatusCode(201);
    }

    /**
     * Update an agent.
     */
    public function update(Request $request, Agent $agent): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'role' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'base_instructions' => 'nullable|string',
            'persona_prompt' => 'nullable|string',
            'persona' => 'nullable|array',
            'persona.name' => 'nullable|string|max:255',
            'persona.aliases' => 'nullable|array',
            'persona.aliases.*' => 'string|max:100',
            'persona.avatar' => 'nullable|string|max:50',
            'persona.personality' => 'nullable|string|max:500',
            'persona.bio' => 'nullable|string|max:1000',
            'model' => 'nullable|string|max:100',
            'icon' => 'nullable|string|max:50',
            'sort_order' => 'nullable|integer',
            'objective_template' => 'nullable|string',
            'success_criteria' => 'nullable|array',
            'max_iterations' => 'nullable|integer|min:1|max:1000',
            'timeout_seconds' => 'nullable|integer|min:1|max:3600',
            'input_schema' => 'nullable|array',
            'memory_sources' => 'nullable|array',
            'context_strategy' => 'nullable|string|in:full,summary,sliding_window,rag',
            'planning_mode' => 'nullable|string|in:none,act,plan_then_act,react',
            'temperature' => 'nullable|numeric|min:0|max:2',
            'system_prompt' => 'nullable|string',
            'eval_criteria' => 'nullable|array',
            'output_schema' => 'nullable|array',
            'loop_condition' => 'nullable|string|in:goal_met,max_iterations,timeout,manual',
            'parent_agent_id' => 'nullable|integer|exists:agents,id',
            'delegation_rules' => 'nullable|array',
            'can_delegate' => 'nullable|boolean',
            'custom_tools' => 'nullable|array',
            'is_template' => 'nullable|boolean',
        ]);

        $agent->update($validated);
        $agent->load('parentAgent', 'childAgents');

        return (new AgentResource($agent))->response();
    }

    /**
     * Delete an agent.
     */
    public function destroy(Agent $agent): JsonResponse
    {
        $agent->delete();

        return response()->json(['message' => 'Agent deleted']);
    }

    /**
     * Duplicate an agent.
     */
    public function duplicate(Agent $agent): JsonResponse
    {
        $copy = $agent->replicate(['uuid', 'slug']);
        $copy->name = $agent->name . ' (Copy)';
        $copy->is_template = false;
        $copy->save();

        return (new AgentResource($copy))->response()->setStatusCode(201);
    }

    /**
     * Export an agent as JSON or YAML.
     */
    public function export(Request $request, Agent $agent): JsonResponse
    {
        $format = $request->query('format', 'json');

        $data = [
            'name' => $agent->name,
            'role' => $agent->role,
            'description' => $agent->description,
            'model' => $agent->model,
            'icon' => $agent->icon,

            // Identity
            'base_instructions' => $agent->base_instructions,
            'persona_prompt' => $agent->persona_prompt,

            // Goal
            'objective_template' => $agent->objective_template,
            'success_criteria' => $agent->success_criteria,
            'max_iterations' => $agent->max_iterations,
            'timeout_seconds' => $agent->timeout_seconds,

            // Perception
            'input_schema' => $agent->input_schema,
            'memory_sources' => $agent->memory_sources,
            'context_strategy' => $agent->context_strategy,

            // Reasoning
            'planning_mode' => $agent->planning_mode,
            'temperature' => $agent->temperature ? (float) $agent->temperature : null,
            'system_prompt' => $agent->system_prompt,

            // Observation
            'eval_criteria' => $agent->eval_criteria,
            'output_schema' => $agent->output_schema,
            'loop_condition' => $agent->loop_condition,

            // Orchestration
            'delegation_rules' => $agent->delegation_rules,
            'can_delegate' => $agent->can_delegate,

            // Actions
            'custom_tools' => $agent->custom_tools,
        ];

        // Remove null values for cleaner export
        $data = array_filter($data, fn ($v) => $v !== null);

        if ($format === 'yaml') {
            return response()->json([
                'format' => 'yaml',
                'content' => Yaml::dump($data, 4, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK),
                'filename' => $agent->slug . '.yaml',
            ]);
        }

        return response()->json([
            'format' => 'json',
            'content' => $data,
            'filename' => $agent->slug . '.json',
        ]);
    }

    /**
     * Quick-create an agent and attach it to a project.
     *
     * POST /api/projects/{project}/agents/quick-create
     */
    public function quickCreate(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'model' => 'nullable|string|max:100',
            'role' => 'nullable|string|max:255',
        ]);

        $agent = Agent::create([
            'name' => $validated['name'],
            'role' => $validated['role'] ?? 'general',
            'model' => $validated['model'] ?? 'claude-sonnet-4-6',
            'base_instructions' => '',
            'planning_mode' => 'plan_then_act',
            'context_strategy' => 'full',
            'max_iterations' => 10,
            'can_delegate' => false,
        ]);

        // Attach to project with is_enabled = true
        ProjectAgent::create([
            'project_id' => $project->id,
            'agent_id' => $agent->id,
            'is_enabled' => true,
        ]);

        $agent->load('parentAgent', 'childAgents');

        return (new AgentResource($agent))->response()->setStatusCode(201);
    }

    /**
     * List agents for a project with their enabled status and assigned skills.
     */
    public function projectAgents(Project $project): JsonResponse
    {
        $agents = Agent::orderBy('sort_order')->get();

        $projectAgents = $project->projectAgents()
            ->with('agent')
            ->get()
            ->keyBy('agent_id');

        // Get skill assignments: agent_id => [skill_ids]
        $skillAssignments = \DB::table('agent_skill')
            ->where('project_id', $project->id)
            ->get()
            ->groupBy('agent_id')
            ->map(fn ($rows) => $rows->pluck('skill_id')->values());

        // Get MCP server assignments
        $mcpAssignments = \DB::table('agent_mcp_server')
            ->where('project_id', $project->id)
            ->get()
            ->groupBy('agent_id')
            ->map(fn ($rows) => $rows->pluck('project_mcp_server_id')->values());

        // Get A2A agent assignments
        $a2aAssignments = \DB::table('agent_a2a_agent')
            ->where('project_id', $project->id)
            ->get()
            ->groupBy('agent_id')
            ->map(fn ($rows) => $rows->pluck('project_a2a_agent_id')->values());

        $result = $agents->map(function (Agent $agent) use ($projectAgents, $skillAssignments, $mcpAssignments, $a2aAssignments) {
            $pa = $projectAgents->get($agent->id);

            $agentData = (new AgentResource($agent))->resolve();
            $agentData['is_enabled'] = $pa?->is_enabled ?? false;
            $agentData['custom_instructions'] = $pa?->custom_instructions;
            $agentData['skill_ids'] = $skillAssignments->get($agent->id, collect())->values();
            $agentData['mcp_server_ids'] = $mcpAssignments->get($agent->id, collect())->values();
            $agentData['a2a_agent_ids'] = $a2aAssignments->get($agent->id, collect())->values();

            // Include override fields from project_agent pivot
            if ($pa) {
                $agentData['objective_override'] = $pa->objective_override;
                $agentData['success_criteria_override'] = $pa->success_criteria_override;
                $agentData['max_iterations_override'] = $pa->max_iterations_override;
                $agentData['timeout_override'] = $pa->timeout_override;
                $agentData['model_override'] = $pa->model_override;
                $agentData['temperature_override'] = $pa->temperature_override;
                $agentData['context_strategy_override'] = $pa->context_strategy_override;
                $agentData['planning_mode_override'] = $pa->planning_mode_override;
                $agentData['custom_tools_override'] = $pa->custom_tools_override;
            }

            return $agentData;
        });

        return response()->json(['data' => $result]);
    }

    /**
     * Toggle an agent on/off for a project.
     */
    public function toggle(Request $request, Project $project, Agent $agent): JsonResponse
    {
        $validated = $request->validate([
            'is_enabled' => 'required|boolean',
        ]);

        ProjectAgent::updateOrCreate(
            ['project_id' => $project->id, 'agent_id' => $agent->id],
            ['is_enabled' => $validated['is_enabled']],
        );

        return response()->json(['message' => 'Agent toggled']);
    }

    /**
     * Update custom instructions for a project agent.
     */
    public function updateInstructions(Request $request, Project $project, Agent $agent): JsonResponse
    {
        $validated = $request->validate([
            'custom_instructions' => 'nullable|string|max:10000',
        ]);

        ProjectAgent::updateOrCreate(
            ['project_id' => $project->id, 'agent_id' => $agent->id],
            ['custom_instructions' => $validated['custom_instructions']],
        );

        return response()->json(['message' => 'Instructions updated']);
    }

    /**
     * Assign skills to a project agent.
     */
    public function assignSkills(Request $request, Project $project, Agent $agent): JsonResponse
    {
        $validated = $request->validate([
            'skill_ids' => 'present|array',
            'skill_ids.*' => 'integer|exists:skills,id',
        ]);

        // Verify all skills belong to this project
        $projectSkillIds = $project->skills()->pluck('id')->toArray();
        $invalidIds = array_diff($validated['skill_ids'], $projectSkillIds);

        if (! empty($invalidIds)) {
            return response()->json([
                'message' => 'Some skills do not belong to this project.',
            ], 422);
        }

        // Sync agent_skill pivot
        \DB::table('agent_skill')
            ->where('project_id', $project->id)
            ->where('agent_id', $agent->id)
            ->delete();

        $rows = collect($validated['skill_ids'])->map(fn ($skillId) => [
            'project_id' => $project->id,
            'agent_id' => $agent->id,
            'skill_id' => $skillId,
        ])->toArray();

        if (! empty($rows)) {
            \DB::table('agent_skill')->insert($rows);
        }

        return response()->json(['message' => 'Skills assigned']);
    }

    /**
     * Bind MCP servers to an agent for a project.
     */
    public function bindMcpServers(Request $request, Project $project, Agent $agent): JsonResponse
    {
        $validated = $request->validate([
            'mcp_server_ids' => 'present|array',
            'mcp_server_ids.*' => 'integer|exists:project_mcp_servers,id',
        ]);

        // Verify MCP servers belong to this project
        $projectMcpIds = $project->mcpServers()->pluck('id')->toArray();
        $invalidIds = array_diff($validated['mcp_server_ids'], $projectMcpIds);

        if (! empty($invalidIds)) {
            return response()->json([
                'message' => 'Some MCP servers do not belong to this project.',
            ], 422);
        }

        // Sync pivot
        \DB::table('agent_mcp_server')
            ->where('project_id', $project->id)
            ->where('agent_id', $agent->id)
            ->delete();

        $rows = collect($validated['mcp_server_ids'])->map(fn ($id) => [
            'project_id' => $project->id,
            'agent_id' => $agent->id,
            'project_mcp_server_id' => $id,
            'created_at' => now(),
            'updated_at' => now(),
        ])->toArray();

        if (! empty($rows)) {
            \DB::table('agent_mcp_server')->insert($rows);
        }

        return response()->json(['message' => 'MCP servers bound']);
    }

    /**
     * Bind A2A agents to an agent for a project.
     */
    public function bindA2aAgents(Request $request, Project $project, Agent $agent): JsonResponse
    {
        $validated = $request->validate([
            'a2a_agent_ids' => 'present|array',
            'a2a_agent_ids.*' => 'integer|exists:project_a2a_agents,id',
        ]);

        // Verify A2A agents belong to this project
        $projectA2aIds = $project->a2aAgents()->pluck('id')->toArray();
        $invalidIds = array_diff($validated['a2a_agent_ids'], $projectA2aIds);

        if (! empty($invalidIds)) {
            return response()->json([
                'message' => 'Some A2A agents do not belong to this project.',
            ], 422);
        }

        // Sync pivot
        \DB::table('agent_a2a_agent')
            ->where('project_id', $project->id)
            ->where('agent_id', $agent->id)
            ->delete();

        $rows = collect($validated['a2a_agent_ids'])->map(fn ($id) => [
            'project_id' => $project->id,
            'agent_id' => $agent->id,
            'project_a2a_agent_id' => $id,
            'created_at' => now(),
            'updated_at' => now(),
        ])->toArray();

        if (! empty($rows)) {
            \DB::table('agent_a2a_agent')->insert($rows);
        }

        return response()->json(['message' => 'A2A agents bound']);
    }

    /**
     * Compose the full output for a single project agent.
     *
     * Query param ?depth=index|full|deep controls progressive disclosure.
     */
    public function compose(Request $request, Project $project, Agent $agent): JsonResponse
    {
        $depth = $request->query('depth', 'full');
        $modelOverride = $request->query('model');

        return response()->json([
            'data' => $this->composeService->compose($project, $agent, $depth, $modelOverride),
        ]);
    }

    /**
     * Compose structured output for a single project agent.
     */
    public function composeStructured(Project $project, Agent $agent): JsonResponse
    {
        return response()->json([
            'data' => $this->composeService->composeStructured($project, $agent),
        ]);
    }

    /**
     * Compose all enabled agents for a project.
     *
     * Query param ?depth=index|full|deep controls progressive disclosure.
     */
    public function composeAll(Request $request, Project $project): JsonResponse
    {
        $depth = $request->query('depth', 'full');

        return response()->json([
            'data' => $this->composeService->composeAll($project, $depth),
        ]);
    }

    /**
     * Public-facing agent profile: owner, specialization, reputation, recent runs.
     * Used by the directory + routing views.
     */
    public function profile(Agent $agent): JsonResponse
    {
        $agent->load(['owner']);

        $skillNames = \Illuminate\Support\Facades\DB::table('agent_skill')
            ->join('skills', 'skills.id', '=', 'agent_skill.skill_id')
            ->where('agent_skill.agent_id', $agent->id)
            ->pluck('skills.name', 'skills.slug');

        $runCount = \App\Models\ExecutionRun::where('agent_id', $agent->id)->count();
        $recentRunsQuery = \App\Models\ExecutionRun::where('agent_id', $agent->id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->select(['id', 'uuid', 'status', 'created_at', 'total_tokens', 'total_duration_ms']);

        if (\Illuminate\Support\Facades\Schema::hasColumn('execution_runs', 'visibility')) {
            $recentRunsQuery->where('visibility', '!=', 'private');
        }

        $recentRuns = $recentRunsQuery->get();

        $domainSummary = $skillNames->isEmpty()
            ? 'No attached skills'
            : 'Specializes in: ' . $skillNames->values()->take(5)->implode(', ');

        return response()->json([
            'data' => [
                'id' => $agent->id,
                'uuid' => $agent->uuid,
                'name' => $agent->name,
                'slug' => $agent->slug,
                'icon' => $agent->icon,
                'role' => $agent->role,
                'description' => $agent->description,
                'owner' => $agent->owner ? [
                    'id' => $agent->owner->id,
                    'name' => $agent->owner->name,
                    'email' => $agent->owner->email,
                ] : null,
                'reputation_score' => $agent->reputation_score,
                'reputation_last_computed_at' => $agent->reputation_last_computed_at?->toIso8601String(),
                'domain_summary' => $domainSummary,
                'skill_slugs' => $skillNames->keys()->values(),
                'total_invocations' => $runCount,
                'recent_runs' => $recentRuns,
            ],
        ]);
    }
}
