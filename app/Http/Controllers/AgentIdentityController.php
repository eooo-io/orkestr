<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\AgentIdentity;
use App\Models\AgentPermission;
use App\Models\AgentResourceQuota;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentIdentityController extends Controller
{
    /**
     * GET /api/agents/{agent}/identities
     */
    public function listIdentities(Agent $agent): JsonResponse
    {
        $identities = $agent->identities()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (AgentIdentity $i) => [
                'id' => $i->id,
                'name' => $i->name,
                'token_hint' => '...' . substr($i->token_hash, -4),
                'scopes' => $i->scopes,
                'rate_limit_per_minute' => $i->rate_limit_per_minute,
                'rate_limit_per_hour' => $i->rate_limit_per_hour,
                'allowed_ips' => $i->allowed_ips,
                'expires_at' => $i->expires_at?->toISOString(),
                'last_used_at' => $i->last_used_at?->toISOString(),
                'is_expired' => $i->isExpired(),
                'created_at' => $i->created_at?->toISOString(),
            ]);

        return response()->json(['data' => $identities]);
    }

    /**
     * POST /api/agents/{agent}/identities
     */
    public function createIdentity(Request $request, Agent $agent): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'scopes' => 'nullable|array',
            'scopes.*' => 'string',
            'rate_limit_per_minute' => 'nullable|integer|min:1',
            'rate_limit_per_hour' => 'nullable|integer|min:1',
            'allowed_ips' => 'nullable|array',
            'allowed_ips.*' => 'string|ip',
            'expires_at' => 'nullable|date|after:now',
        ]);

        [$plainToken, $hashedToken] = AgentIdentity::generateToken();

        $identity = AgentIdentity::create([
            'agent_id' => $agent->id,
            'name' => $validated['name'],
            'token_hash' => $hashedToken,
            'scopes' => $validated['scopes'] ?? null,
            'rate_limit_per_minute' => $validated['rate_limit_per_minute'] ?? null,
            'rate_limit_per_hour' => $validated['rate_limit_per_hour'] ?? null,
            'allowed_ips' => $validated['allowed_ips'] ?? null,
            'expires_at' => $validated['expires_at'] ?? null,
        ]);

        // Return plain token ONCE — it cannot be retrieved again
        return response()->json([
            'data' => [
                'id' => $identity->id,
                'name' => $identity->name,
                'token' => $plainToken,
                'token_hint' => '...' . substr($hashedToken, -4),
                'scopes' => $identity->scopes,
                'expires_at' => $identity->expires_at?->toISOString(),
                'created_at' => $identity->created_at?->toISOString(),
            ],
        ], 201);
    }

    /**
     * DELETE /api/agent-identities/{agent_identity}
     */
    public function deleteIdentity(AgentIdentity $agentIdentity): JsonResponse
    {
        $agentIdentity->delete();

        return response()->json(null, 204);
    }

    /**
     * GET /api/projects/{project}/agents/{agent}/quota
     */
    public function getQuota(Project $project, Agent $agent): JsonResponse
    {
        $quota = AgentResourceQuota::where('agent_id', $agent->id)
            ->where('project_id', $project->id)
            ->first();

        if (! $quota) {
            return response()->json([
                'data' => [
                    'agent_id' => $agent->id,
                    'project_id' => $project->id,
                    'max_tokens_per_day' => null,
                    'max_cost_per_day' => null,
                    'max_concurrent_executions' => 3,
                    'max_execution_duration_seconds' => 3600,
                    'max_mcp_connections' => 10,
                    'allowed_domains' => null,
                ],
            ]);
        }

        return response()->json(['data' => $quota]);
    }

    /**
     * PUT /api/projects/{project}/agents/{agent}/quota
     */
    public function updateQuota(Request $request, Project $project, Agent $agent): JsonResponse
    {
        $validated = $request->validate([
            'max_tokens_per_day' => 'nullable|integer|min:0',
            'max_cost_per_day' => 'nullable|numeric|min:0',
            'max_concurrent_executions' => 'nullable|integer|min:1',
            'max_execution_duration_seconds' => 'nullable|integer|min:60',
            'max_mcp_connections' => 'nullable|integer|min:0',
            'allowed_domains' => 'nullable|array',
            'allowed_domains.*' => 'string',
        ]);

        $quota = AgentResourceQuota::updateOrCreate(
            ['agent_id' => $agent->id, 'project_id' => $project->id],
            $validated,
        );

        return response()->json(['data' => $quota]);
    }

    /**
     * GET /api/agents/{agent}/permissions
     */
    public function listPermissions(Agent $agent): JsonResponse
    {
        $permissions = $agent->permissions()
            ->orderBy('permission_type')
            ->orderBy('permission_target')
            ->get();

        return response()->json(['data' => $permissions]);
    }

    /**
     * POST /api/agents/{agent}/permissions
     */
    public function setPermission(Request $request, Agent $agent): JsonResponse
    {
        $validated = $request->validate([
            'permission_type' => 'required|string|in:' . implode(',', AgentPermission::validTypes()),
            'permission_target' => 'required|string|max:255',
            'allowed' => 'required|boolean',
        ]);

        $permission = AgentPermission::updateOrCreate(
            [
                'agent_id' => $agent->id,
                'permission_type' => $validated['permission_type'],
                'permission_target' => $validated['permission_target'],
            ],
            ['allowed' => $validated['allowed']],
        );

        return response()->json(['data' => $permission], $permission->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * DELETE /api/agent-permissions/{agent_permission}
     */
    public function deletePermission(AgentPermission $agentPermission): JsonResponse
    {
        $agentPermission->delete();

        return response()->json(null, 204);
    }
}
