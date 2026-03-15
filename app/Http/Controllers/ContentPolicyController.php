<?php

namespace App\Http\Controllers;

use App\Models\ContentPolicy;
use App\Models\Skill;
use App\Services\ContentPolicyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContentPolicyController extends Controller
{
    public function __construct(
        private ContentPolicyService $policyService,
    ) {}

    /**
     * GET /api/organizations/{organization}/content-policies
     */
    public function index(Request $request, int $organization): JsonResponse
    {
        $policies = ContentPolicy::forOrganization($organization)
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $policies]);
    }

    /**
     * POST /api/organizations/{organization}/content-policies
     */
    public function store(Request $request, int $organization): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'rules' => 'required|array|min:1',
            'rules.*.type' => 'required|string',
            'rules.*.action' => 'required|in:block,warn',
            'rules.*.value' => 'nullable',
            'rules.*.pattern' => 'nullable|string',
            'rules.*.message' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $policy = ContentPolicy::create([
            'organization_id' => $organization,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'rules' => $validated['rules'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json(['data' => $policy], 201);
    }

    /**
     * GET /api/content-policies/{contentPolicy}
     */
    public function show(ContentPolicy $contentPolicy): JsonResponse
    {
        return response()->json(['data' => $contentPolicy]);
    }

    /**
     * PUT /api/content-policies/{contentPolicy}
     */
    public function update(Request $request, ContentPolicy $contentPolicy): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'rules' => 'sometimes|required|array|min:1',
            'rules.*.type' => 'required_with:rules|string',
            'rules.*.action' => 'required_with:rules|in:block,warn',
            'rules.*.value' => 'nullable',
            'rules.*.pattern' => 'nullable|string',
            'rules.*.message' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $contentPolicy->update($validated);

        return response()->json(['data' => $contentPolicy->fresh()]);
    }

    /**
     * DELETE /api/content-policies/{contentPolicy}
     */
    public function destroy(ContentPolicy $contentPolicy): JsonResponse
    {
        $contentPolicy->delete();

        return response()->json(null, 204);
    }

    /**
     * POST /api/content-policies/{contentPolicy}/check/{skill}
     *
     * Check a specific skill against a specific policy.
     */
    public function checkSkill(ContentPolicy $contentPolicy, Skill $skill): JsonResponse
    {
        $violations = [];

        foreach ($contentPolicy->rules as $rule) {
            $ruleViolations = $this->evaluateRuleForSkill($rule, $skill);
            foreach ($ruleViolations as $violation) {
                $violations[] = [
                    'policy' => $contentPolicy->name,
                    'policy_id' => $contentPolicy->id,
                    'rule' => $rule['type'],
                    'action' => $rule['action'] ?? 'warn',
                    'message' => $violation,
                ];
            }
        }

        return response()->json([
            'compliant' => empty($violations),
            'violations' => $violations,
        ]);
    }

    /**
     * POST /api/skills/{skill}/check-policies
     *
     * Check a skill against all active policies for its organization.
     */
    public function checkSkillPolicies(Skill $skill): JsonResponse
    {
        $violations = $this->policyService->checkSkillCompliance($skill);

        $hasBlocking = collect($violations)->contains('action', 'block');

        return response()->json([
            'compliant' => empty($violations),
            'blocked' => $hasBlocking,
            'violations' => $violations,
        ]);
    }

    /**
     * GET /api/content-policies/rule-types
     */
    public function ruleTypes(): JsonResponse
    {
        return response()->json(['data' => ContentPolicyService::ruleTypes()]);
    }

    private function evaluateRuleForSkill(array $rule, Skill $skill): array
    {
        // Delegate to service — creates temporary policy-like context
        $tempPolicy = new ContentPolicy([
            'rules' => [$rule],
            'is_active' => true,
        ]);

        $service = app(ContentPolicyService::class);

        return collect($service->checkSkillCompliance($skill, $skill->project?->organization_id))
            ->pluck('message')
            ->toArray();
    }
}
