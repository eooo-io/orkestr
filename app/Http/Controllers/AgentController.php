<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\Project;
use App\Models\ProjectAgent;
use App\Services\AgentComposeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        $agents = Agent::orderBy('sort_order')->get();

        return response()->json(['data' => $agents]);
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

        $result = $agents->map(function (Agent $agent) use ($projectAgents, $skillAssignments) {
            $pa = $projectAgents->get($agent->id);

            return [
                'id' => $agent->id,
                'uuid' => $agent->uuid,
                'name' => $agent->name,
                'slug' => $agent->slug,
                'role' => $agent->role,
                'description' => $agent->description,
                'base_instructions' => $agent->base_instructions,
                'icon' => $agent->icon,
                'sort_order' => $agent->sort_order,
                'is_enabled' => $pa?->is_enabled ?? false,
                'custom_instructions' => $pa?->custom_instructions,
                'skill_ids' => $skillAssignments->get($agent->id, collect())->values(),
            ];
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
     * Compose the full output for a single project agent.
     */
    public function compose(Project $project, Agent $agent): JsonResponse
    {
        return response()->json([
            'data' => $this->composeService->compose($project, $agent),
        ]);
    }

    /**
     * Compose all enabled agents for a project.
     */
    public function composeAll(Project $project): JsonResponse
    {
        return response()->json([
            'data' => $this->composeService->composeAll($project),
        ]);
    }
}
