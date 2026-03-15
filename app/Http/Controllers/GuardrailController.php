<?php

namespace App\Http\Controllers;

use App\Models\GuardrailPolicy;
use App\Models\GuardrailProfile;
use App\Services\Execution\Guards\GuardrailPolicyEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GuardrailController extends Controller
{
    public function __construct(
        private GuardrailPolicyEngine $engine,
    ) {}

    // ─── Policies ────────────────────────────────────────────────

    /**
     * GET /api/organizations/{org}/guardrails
     */
    public function index(int $org): JsonResponse
    {
        $policies = GuardrailPolicy::forOrganization($org)
            ->orderBy('priority', 'desc')
            ->get();

        return response()->json($policies);
    }

    /**
     * POST /api/organizations/{org}/guardrails
     */
    public function store(Request $request, int $org): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'scope' => 'required|in:organization,project,agent',
            'scope_id' => 'nullable|integer',
            'budget_limits' => 'nullable|array',
            'tool_restrictions' => 'nullable|array',
            'output_rules' => 'nullable|array',
            'access_rules' => 'nullable|array',
            'approval_level' => 'nullable|in:supervised,semi_autonomous,autonomous',
            'priority' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        $policy = GuardrailPolicy::create([
            'organization_id' => $org,
            ...$validated,
        ]);

        return response()->json($policy, 201);
    }

    /**
     * PUT /api/guardrails/{policy}
     */
    public function update(Request $request, GuardrailPolicy $policy): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'scope' => 'sometimes|in:organization,project,agent',
            'scope_id' => 'nullable|integer',
            'budget_limits' => 'nullable|array',
            'tool_restrictions' => 'nullable|array',
            'output_rules' => 'nullable|array',
            'access_rules' => 'nullable|array',
            'approval_level' => 'nullable|in:supervised,semi_autonomous,autonomous',
            'priority' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        $policy->update($validated);

        return response()->json($policy);
    }

    /**
     * DELETE /api/guardrails/{policy}
     */
    public function destroy(GuardrailPolicy $policy): JsonResponse
    {
        $policy->delete();

        return response()->json(['message' => 'Policy deleted.']);
    }

    /**
     * GET /api/organizations/{org}/guardrails/resolve
     * Resolve effective config for a scope.
     */
    public function resolve(Request $request, int $org): JsonResponse
    {
        $projectId = $request->query('project_id') ? (int) $request->query('project_id') : null;
        $agentId = $request->query('agent_id') ? (int) $request->query('agent_id') : null;

        $config = $this->engine->resolveEffectiveConfig($org, $projectId, $agentId);

        return response()->json($config);
    }

    // ─── Profiles ────────────────────────────────────────────────

    /**
     * GET /api/guardrail-profiles
     */
    public function profiles(): JsonResponse
    {
        $profiles = GuardrailProfile::orderBy('is_system', 'desc')
            ->orderBy('name')
            ->get();

        return response()->json($profiles);
    }

    /**
     * GET /api/guardrail-profiles/{profile}
     */
    public function showProfile(GuardrailProfile $profile): JsonResponse
    {
        return response()->json($profile);
    }

    /**
     * POST /api/guardrail-profiles
     */
    public function storeProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:guardrail_profiles,slug',
            'description' => 'nullable|string',
            'budget_limits' => 'nullable|array',
            'tool_restrictions' => 'nullable|array',
            'output_rules' => 'nullable|array',
            'access_rules' => 'nullable|array',
            'approval_level' => 'nullable|in:supervised,semi_autonomous,autonomous',
            'input_sanitization' => 'nullable|array',
            'network_rules' => 'nullable|array',
        ]);

        $user = $request->user();
        $profile = GuardrailProfile::create([
            'organization_id' => $user->current_organization_id,
            ...$validated,
        ]);

        return response()->json($profile, 201);
    }

    /**
     * DELETE /api/guardrail-profiles/{profile}
     */
    public function destroyProfile(GuardrailProfile $profile): JsonResponse
    {
        if ($profile->is_system) {
            return response()->json(['error' => 'Cannot delete system profiles.'], 403);
        }

        $profile->delete();

        return response()->json(['message' => 'Profile deleted.']);
    }
}
