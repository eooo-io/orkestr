<?php

namespace App\Services\Execution\Guards;

use App\Models\Agent;
use App\Models\GuardrailPolicy;
use App\Models\GuardrailProfile;
use App\Models\GuardrailViolation;
use App\Models\Project;

class GuardrailPolicyEngine
{
    /**
     * Resolve the effective guardrail configuration for an agent in a project.
     * Cascading order: org defaults → project overrides → agent overrides.
     * Each level can tighten but not loosen restrictions.
     */
    public function resolveEffectiveConfig(int $organizationId, ?int $projectId = null, ?int $agentId = null): array
    {
        $policies = GuardrailPolicy::active()
            ->forOrganization($organizationId)
            ->orderBy('priority', 'desc')
            ->get();

        // Start with org-level defaults
        $orgPolicies = $policies->where('scope', 'organization');
        $config = $this->mergePolicies($orgPolicies);

        // Layer project-level policies (tighten only)
        if ($projectId) {
            $projectPolicies = $policies->where('scope', 'project')->where('scope_id', $projectId);
            $config = $this->tightenConfig($config, $this->mergePolicies($projectPolicies));
        }

        // Layer agent-level policies (tighten only)
        if ($agentId) {
            $agentPolicies = $policies->where('scope', 'agent')->where('scope_id', $agentId);
            $config = $this->tightenConfig($config, $this->mergePolicies($agentPolicies));
        }

        return $config;
    }

    /**
     * Apply a guardrail profile as the base configuration.
     */
    public function applyProfile(GuardrailProfile $profile, array $existingConfig = []): array
    {
        $profileConfig = $profile->toPolicyConfig();

        if (empty($existingConfig)) {
            return $profileConfig;
        }

        return $this->tightenConfig($profileConfig, $existingConfig);
    }

    /**
     * Record a guardrail violation.
     */
    public function recordViolation(array $data): GuardrailViolation
    {
        return GuardrailViolation::create($data);
    }

    /**
     * Check a tool call against resolved policies.
     * Returns null if allowed, or violation data if blocked.
     */
    public function checkToolCall(array $config, string $toolName, array $input = []): ?array
    {
        $restrictions = $config['tool_restrictions'] ?? [];

        // Check blocklist
        $blocklist = $restrictions['blocklist'] ?? [];
        if (! empty($blocklist) && in_array($toolName, $blocklist, true)) {
            return [
                'guard_type' => 'tool',
                'rule_name' => 'tool_blocklist',
                'severity' => 'error',
                'message' => "Tool '{$toolName}' is blocked by organization policy.",
                'context' => ['tool' => $toolName],
            ];
        }

        // Check allowlist
        $allowlist = $restrictions['allowlist'] ?? [];
        if (! empty($allowlist) && ! in_array($toolName, $allowlist, true)) {
            return [
                'guard_type' => 'tool',
                'rule_name' => 'tool_allowlist',
                'severity' => 'error',
                'message' => "Tool '{$toolName}' is not in the organization allowlist.",
                'context' => ['tool' => $toolName, 'allowlist' => $allowlist],
            ];
        }

        return null;
    }

    /**
     * Check if budget limits would be exceeded.
     */
    public function checkBudgetLimits(array $config, float $currentCostUsd, int $currentTokens): ?array
    {
        $limits = $config['budget_limits'] ?? [];

        $maxCost = $limits['max_cost_usd'] ?? null;
        if ($maxCost !== null && $currentCostUsd >= $maxCost) {
            return [
                'guard_type' => 'budget',
                'rule_name' => 'org_cost_limit',
                'severity' => 'error',
                'message' => "Organization cost limit exceeded: \${$currentCostUsd} >= \${$maxCost}.",
                'context' => ['current' => $currentCostUsd, 'limit' => $maxCost],
            ];
        }

        $maxTokens = $limits['max_tokens'] ?? null;
        if ($maxTokens !== null && $currentTokens >= $maxTokens) {
            return [
                'guard_type' => 'budget',
                'rule_name' => 'org_token_limit',
                'severity' => 'error',
                'message' => "Organization token limit exceeded: {$currentTokens} >= {$maxTokens}.",
                'context' => ['current' => $currentTokens, 'limit' => $maxTokens],
            ];
        }

        return null;
    }

    /**
     * Get the required approval level from config.
     */
    public function getApprovalLevel(array $config): string
    {
        return $config['approval_level'] ?? 'semi_autonomous';
    }

    /**
     * Merge multiple policies into a single config (highest priority wins per field).
     */
    private function mergePolicies($policies): array
    {
        $config = [
            'budget_limits' => [],
            'tool_restrictions' => [],
            'output_rules' => [],
            'access_rules' => [],
            'approval_level' => null,
        ];

        foreach ($policies as $policy) {
            if ($policy->budget_limits) {
                $config['budget_limits'] = array_merge($config['budget_limits'], $policy->budget_limits);
            }
            if ($policy->tool_restrictions) {
                $config['tool_restrictions'] = $this->mergeToolRestrictions(
                    $config['tool_restrictions'],
                    $policy->tool_restrictions
                );
            }
            if ($policy->output_rules) {
                $config['output_rules'] = array_merge($config['output_rules'], $policy->output_rules);
            }
            if ($policy->access_rules) {
                $config['access_rules'] = array_merge($config['access_rules'], $policy->access_rules);
            }
            if ($policy->approval_level) {
                $config['approval_level'] = $policy->approval_level;
            }
        }

        return $config;
    }

    /**
     * Tighten a configuration — child level can only make things MORE restrictive.
     */
    private function tightenConfig(array $base, array $override): array
    {
        $result = $base;

        // Budget: take the lower limit
        if (! empty($override['budget_limits'])) {
            foreach ($override['budget_limits'] as $key => $value) {
                if ($value === null) {
                    continue;
                }
                if (! isset($result['budget_limits'][$key]) || $value < $result['budget_limits'][$key]) {
                    $result['budget_limits'][$key] = $value;
                }
            }
        }

        // Tool restrictions: union of blocklists, intersection of allowlists
        if (! empty($override['tool_restrictions'])) {
            $result['tool_restrictions'] = $this->tightenToolRestrictions(
                $result['tool_restrictions'] ?? [],
                $override['tool_restrictions']
            );
        }

        // Output rules: merge (stricter values win)
        if (! empty($override['output_rules'])) {
            foreach ($override['output_rules'] as $key => $value) {
                if ($key === 'max_output_length') {
                    $result['output_rules'][$key] = min(
                        $result['output_rules'][$key] ?? PHP_INT_MAX,
                        $value
                    );
                } else {
                    // Boolean flags: true (more restrictive) wins
                    $result['output_rules'][$key] = ($result['output_rules'][$key] ?? false) || $value;
                }
            }
        }

        // Access rules: tighten
        if (! empty($override['access_rules'])) {
            if (isset($override['access_rules']['external_apis']) && $override['access_rules']['external_apis'] === false) {
                $result['access_rules']['external_apis'] = false;
            }
        }

        // Approval level: most restrictive wins
        if (! empty($override['approval_level'])) {
            $result['approval_level'] = $this->mostRestrictiveApproval(
                $result['approval_level'] ?? 'autonomous',
                $override['approval_level']
            );
        }

        return $result;
    }

    private function mergeToolRestrictions(array $base, array $new): array
    {
        $result = $base;
        $result['blocklist'] = array_values(array_unique(array_merge(
            $base['blocklist'] ?? [],
            $new['blocklist'] ?? []
        )));
        if (! empty($new['allowlist'])) {
            $result['allowlist'] = empty($base['allowlist'])
                ? $new['allowlist']
                : array_values(array_intersect($base['allowlist'], $new['allowlist']));
        }

        return $result;
    }

    private function tightenToolRestrictions(array $base, array $override): array
    {
        return $this->mergeToolRestrictions($base, $override);
    }

    private function mostRestrictiveApproval(string $a, string $b): string
    {
        $levels = ['supervised' => 0, 'semi_autonomous' => 1, 'autonomous' => 2];

        $levelA = $levels[$a] ?? 1;
        $levelB = $levels[$b] ?? 1;

        return array_search(min($levelA, $levelB), $levels) ?: 'semi_autonomous';
    }
}
