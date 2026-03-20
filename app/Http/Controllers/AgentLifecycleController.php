<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\AgentVersion;
use App\Models\Project;
use App\Services\AgentLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentLifecycleController extends Controller
{
    public function __construct(
        protected AgentLifecycleService $lifecycle,
    ) {}

    /**
     * GET /api/agents/{agent}/versions
     */
    public function versions(Agent $agent): JsonResponse
    {
        $versions = $agent->versions()
            ->with('creator:id,name,email')
            ->orderByDesc('version_number')
            ->get()
            ->map(fn (AgentVersion $v) => [
                'id' => $v->id,
                'version_number' => $v->version_number,
                'note' => $v->note,
                'created_by' => $v->creator ? [
                    'id' => $v->creator->id,
                    'name' => $v->creator->name,
                ] : null,
                'created_at' => $v->created_at?->toISOString(),
            ]);

        return response()->json(['data' => $versions]);
    }

    /**
     * POST /api/agents/{agent}/versions
     */
    public function createVersion(Request $request, Agent $agent): JsonResponse
    {
        $validated = $request->validate([
            'note' => 'nullable|string|max:500',
        ]);

        $version = $this->lifecycle->createVersion(
            $agent,
            $request->user()?->id,
            $validated['note'] ?? null,
        );

        return response()->json([
            'data' => [
                'id' => $version->id,
                'version_number' => $version->version_number,
                'note' => $version->note,
                'created_at' => $version->created_at?->toISOString(),
            ],
        ], 201);
    }

    /**
     * GET /api/agent-versions/{agent_version}
     */
    public function showVersion(AgentVersion $agentVersion): JsonResponse
    {
        $agentVersion->load('creator:id,name,email');

        return response()->json([
            'data' => [
                'id' => $agentVersion->id,
                'agent_id' => $agentVersion->agent_id,
                'version_number' => $agentVersion->version_number,
                'config_snapshot' => $agentVersion->config_snapshot,
                'skill_snapshot' => $agentVersion->skill_snapshot,
                'mcp_snapshot' => $agentVersion->mcp_snapshot,
                'a2a_snapshot' => $agentVersion->a2a_snapshot,
                'note' => $agentVersion->note,
                'created_by' => $agentVersion->creator ? [
                    'id' => $agentVersion->creator->id,
                    'name' => $agentVersion->creator->name,
                ] : null,
                'created_at' => $agentVersion->created_at?->toISOString(),
            ],
        ]);
    }

    /**
     * POST /api/agent-versions/{agent_version}/rollback
     */
    public function rollback(AgentVersion $agentVersion): JsonResponse
    {
        $this->lifecycle->rollback($agentVersion);

        return response()->json([
            'data' => [
                'message' => 'Agent rolled back to version ' . $agentVersion->version_number,
                'version_number' => $agentVersion->version_number,
            ],
        ]);
    }

    /**
     * POST /api/projects/{project}/agents/{agent}/health-check
     */
    public function healthChecks(Project $project, Agent $agent): JsonResponse
    {
        $checks = $this->lifecycle->runHealthChecks($agent, $project);

        return response()->json([
            'data' => collect($checks)->map(fn ($c) => [
                'id' => $c->id,
                'check_type' => $c->check_type,
                'status' => $c->status,
                'details' => $c->details,
                'checked_at' => $c->checked_at?->toISOString(),
            ]),
        ]);
    }

    /**
     * GET /api/projects/{project}/agents/{agent}/health-score
     */
    public function healthScore(Project $project, Agent $agent): JsonResponse
    {
        $score = $this->lifecycle->healthScore($agent, $project);

        return response()->json([
            'data' => [
                'agent_id' => $agent->id,
                'project_id' => $project->id,
                'score' => $score,
            ],
        ]);
    }

    /**
     * POST /api/agents/{agent}/retire
     */
    public function retire(Request $request, Agent $agent): JsonResponse
    {
        $validated = $request->validate([
            'successor_agent_id' => 'nullable|integer|exists:agents,id',
        ]);

        $successor = null;
        if (! empty($validated['successor_agent_id'])) {
            $successor = Agent::find($validated['successor_agent_id']);
        }

        $this->lifecycle->retire($agent, $successor);

        return response()->json([
            'data' => [
                'message' => "Agent '{$agent->name}' has been retired",
                'successor_agent_id' => $successor?->id,
            ],
        ]);
    }
}
