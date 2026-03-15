<?php

namespace App\Services\Execution\Guards;

use App\Models\Agent;
use App\Models\ExecutionRun;

class DelegationGuard
{
    /**
     * Check if an agent is allowed to delegate to a child agent.
     * Returns null if allowed, or a string violation message.
     */
    public function canDelegate(Agent $parent, Agent $child): ?string
    {
        if (!$parent->can_delegate) {
            return "Agent '{$parent->name}' is not allowed to delegate tasks.";
        }

        // Check delegation rules
        $rules = $parent->delegation_rules ?? [];
        $allowedAgents = $rules['allowed_agents'] ?? null;

        if (is_array($allowedAgents) && !in_array($child->id, $allowedAgents) && !in_array($child->slug, $allowedAgents)) {
            return "Agent '{$parent->name}' is not allowed to delegate to '{$child->name}'.";
        }

        return null;
    }

    /**
     * Compute the effective scope for a delegated child agent.
     * Child gets the INTERSECTION of parent scope and its own scope.
     */
    public function computeEffectiveScope(Agent $parent, Agent $child): array
    {
        $parentScope = $parent->data_access_scope ?? [];
        $childScope = $child->data_access_scope ?? [];

        // If parent has no scope restrictions, child keeps its own
        if (empty($parentScope)) {
            return $childScope;
        }

        // If child has no scope restrictions, inherit parent's
        if (empty($childScope)) {
            return $parentScope;
        }

        $effective = [];

        // Intersect project access
        $parentProjects = $parentScope['projects'] ?? '*';
        $childProjects = $childScope['projects'] ?? '*';
        if ($parentProjects === '*') {
            $effective['projects'] = $childProjects;
        } elseif ($childProjects === '*') {
            $effective['projects'] = $parentProjects;
        } elseif (is_array($parentProjects) && is_array($childProjects)) {
            $effective['projects'] = array_values(array_intersect($parentProjects, $childProjects));
        } else {
            $effective['projects'] = $parentProjects;
        }

        // Intersect file permissions (most restrictive wins)
        $parentFiles = $parentScope['files'] ?? null;
        $childFiles = $childScope['files'] ?? null;
        if ($parentFiles !== null && $childFiles !== null) {
            $effective['files'] = array_values(array_intersect($parentFiles, $childFiles));
        } elseif ($parentFiles !== null) {
            $effective['files'] = $parentFiles;
        } elseif ($childFiles !== null) {
            $effective['files'] = $childFiles;
        }

        // External API access: most restrictive wins (false beats true)
        $parentExternal = $parentScope['external_apis'] ?? true;
        $childExternal = $childScope['external_apis'] ?? true;
        $effective['external_apis'] = $parentExternal && $childExternal;

        return $effective;
    }

    /**
     * Compute effective tool restrictions for a delegated child.
     * Allowed tools = intersection, blocked tools = union.
     */
    public function computeEffectiveTools(Agent $parent, Agent $child): array
    {
        $parentAllowed = $parent->allowed_tools ?? [];
        $childAllowed = $child->allowed_tools ?? [];
        $parentBlocked = $parent->blocked_tools ?? [];
        $childBlocked = $child->blocked_tools ?? [];

        // Allowed: intersection (most restrictive)
        $effectiveAllowed = [];
        if (!empty($parentAllowed) && !empty($childAllowed)) {
            $effectiveAllowed = array_values(array_intersect($parentAllowed, $childAllowed));
        } elseif (!empty($parentAllowed)) {
            $effectiveAllowed = $parentAllowed;
        } elseif (!empty($childAllowed)) {
            $effectiveAllowed = $childAllowed;
        }

        // Blocked: union (all restrictions apply)
        $effectiveBlocked = array_values(array_unique(array_merge($parentBlocked, $childBlocked)));

        return [
            'allowed_tools' => $effectiveAllowed,
            'blocked_tools' => $effectiveBlocked,
        ];
    }

    /**
     * Compute the remaining budget a child agent can use.
     * Child cannot exceed parent's remaining budget.
     */
    public function computeEffectiveBudget(Agent $parent, Agent $child, ?ExecutionRun $parentRun = null): array
    {
        $parentRunBudget = $parent->budget_limit_usd;
        $childRunBudget = $child->budget_limit_usd;
        $parentDailyBudget = $parent->daily_budget_limit_usd;
        $childDailyBudget = $child->daily_budget_limit_usd;

        // Run budget: minimum of parent remaining and child limit
        $effectiveRunBudget = null;
        if ($parentRunBudget !== null && $parentRun) {
            $parentSpent = $parentRun->total_cost_microcents / 1_000_000;
            $parentRemaining = max(0, $parentRunBudget - $parentSpent);

            if ($childRunBudget !== null) {
                $effectiveRunBudget = min($parentRemaining, $childRunBudget);
            } else {
                $effectiveRunBudget = $parentRemaining;
            }
        } elseif ($parentRunBudget !== null) {
            $effectiveRunBudget = $childRunBudget !== null
                ? min($parentRunBudget, $childRunBudget)
                : $parentRunBudget;
        } else {
            $effectiveRunBudget = $childRunBudget;
        }

        // Daily budget: minimum of both
        $effectiveDailyBudget = null;
        if ($parentDailyBudget !== null && $childDailyBudget !== null) {
            $effectiveDailyBudget = min($parentDailyBudget, $childDailyBudget);
        } elseif ($parentDailyBudget !== null) {
            $effectiveDailyBudget = $parentDailyBudget;
        } else {
            $effectiveDailyBudget = $childDailyBudget;
        }

        return [
            'budget_limit_usd' => $effectiveRunBudget,
            'daily_budget_limit_usd' => $effectiveDailyBudget,
        ];
    }

    /**
     * Compute effective autonomy level for a delegated child.
     * Child cannot have MORE autonomy than parent.
     */
    public function computeEffectiveAutonomy(Agent $parent, Agent $child): string
    {
        $levels = ['supervised' => 0, 'semi_autonomous' => 1, 'autonomous' => 2];
        $parentLevel = $levels[$parent->autonomy_level ?? 'semi_autonomous'] ?? 1;
        $childLevel = $levels[$child->autonomy_level ?? 'semi_autonomous'] ?? 1;

        // Take the more restrictive (lower) level
        $effectiveLevel = min($parentLevel, $childLevel);

        return array_search($effectiveLevel, $levels) ?: 'semi_autonomous';
    }

    /**
     * Get the full chain of delegation from a child agent back to the root.
     * Prevents circular delegation.
     */
    public function getDelegationChain(Agent $agent, int $maxDepth = 10): array
    {
        $chain = [];
        $current = $agent;
        $seen = [];

        while ($current && count($chain) < $maxDepth) {
            if (in_array($current->id, $seen)) {
                // Circular delegation detected
                break;
            }
            $seen[] = $current->id;
            $chain[] = [
                'id' => $current->id,
                'name' => $current->name,
                'slug' => $current->slug,
                'autonomy_level' => $current->autonomy_level,
            ];

            if ($current->parent_agent_id) {
                $current = Agent::find($current->parent_agent_id);
            } else {
                break;
            }
        }

        return array_reverse($chain);
    }
}
