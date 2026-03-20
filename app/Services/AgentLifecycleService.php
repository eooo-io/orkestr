<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\AgentHealthCheck;
use App\Models\AgentVersion;
use App\Models\ExecutionRun;
use App\Models\Project;

class AgentLifecycleService
{
    /**
     * Create a versioned snapshot of the agent's current configuration.
     */
    public function createVersion(Agent $agent, ?int $userId = null, ?string $note = null): AgentVersion
    {
        $nextVersion = ($agent->versions()->max('version_number') ?? 0) + 1;

        // Snapshot current config (all non-relationship attributes)
        $configSnapshot = $agent->only([
            'name', 'slug', 'role', 'description', 'base_instructions',
            'persona_prompt', 'model', 'fallback_models', 'routing_strategy',
            'icon', 'persona', 'sort_order', 'objective_template',
            'success_criteria', 'max_iterations', 'timeout_seconds',
            'input_schema', 'memory_sources', 'context_strategy',
            'planning_mode', 'temperature', 'system_prompt',
            'eval_criteria', 'output_schema', 'loop_condition',
            'parent_agent_id', 'delegation_rules', 'can_delegate',
            'custom_tools', 'autonomy_level', 'budget_limit_usd',
            'daily_budget_limit_usd', 'allowed_tools', 'blocked_tools',
            'data_access_scope', 'memory_enabled', 'auto_remember',
            'memory_recall_limit',
        ]);

        // Snapshot skill assignments across all projects
        $skillSnapshot = $agent->projects()
            ->with('skills')
            ->get()
            ->flatMap(function ($project) use ($agent) {
                return $project->skills()
                    ->whereHas('agents', fn ($q) => $q->where('agents.id', $agent->id))
                    ->get()
                    ->map(fn ($skill) => [
                        'project_id' => $project->id,
                        'skill_id' => $skill->id,
                        'skill_slug' => $skill->slug,
                    ]);
            })
            ->values()
            ->toArray();

        // Snapshot MCP server bindings
        $mcpSnapshot = $agent->mcpServers->map(fn ($mcp) => [
            'id' => $mcp->id,
            'name' => $mcp->name ?? $mcp->server_name ?? null,
            'project_id' => $mcp->pivot->project_id ?? null,
            'config_overrides' => $mcp->pivot->config_overrides ?? null,
        ])->toArray();

        // Snapshot A2A agent bindings
        $a2aSnapshot = $agent->a2aAgents->map(fn ($a2a) => [
            'id' => $a2a->id,
            'name' => $a2a->name ?? null,
            'project_id' => $a2a->pivot->project_id ?? null,
            'config_overrides' => $a2a->pivot->config_overrides ?? null,
        ])->toArray();

        return AgentVersion::create([
            'agent_id' => $agent->id,
            'version_number' => $nextVersion,
            'config_snapshot' => $configSnapshot,
            'skill_snapshot' => $skillSnapshot ?: [],
            'mcp_snapshot' => $mcpSnapshot ?: [],
            'a2a_snapshot' => $a2aSnapshot ?: [],
            'created_by' => $userId,
            'note' => $note,
        ]);
    }

    /**
     * Rollback an agent to a previous version's configuration.
     */
    public function rollback(AgentVersion $version): void
    {
        $agent = $version->agent;
        $config = $version->config_snapshot;

        // Only restore config attributes that exist on the agent
        $fillable = $agent->getFillable();
        $restoreData = array_intersect_key($config, array_flip($fillable));

        $agent->update($restoreData);
    }

    /**
     * Run all health checks for an agent in a project context.
     *
     * @return array<AgentHealthCheck>
     */
    public function runHealthChecks(Agent $agent, Project $project): array
    {
        $results = [];

        foreach (AgentHealthCheck::validCheckTypes() as $checkType) {
            $result = $this->performCheck($agent, $project, $checkType);

            $check = AgentHealthCheck::create([
                'agent_id' => $agent->id,
                'project_id' => $project->id,
                'check_type' => $checkType,
                'status' => $result['status'],
                'details' => $result['details'],
                'checked_at' => now(),
            ]);

            $results[] = $check;
        }

        return $results;
    }

    /**
     * Calculate a composite health score (0-100).
     */
    public function healthScore(Agent $agent, Project $project): int
    {
        $checks = AgentHealthCheck::where('agent_id', $agent->id)
            ->where('project_id', $project->id)
            ->orderByDesc('checked_at')
            ->get()
            ->unique('check_type');

        if ($checks->isEmpty()) {
            return 0;
        }

        // Weight each check type equally
        $scorePerCheck = 100 / max(count(AgentHealthCheck::validCheckTypes()), 1);
        $total = 0;

        foreach ($checks as $check) {
            if ($check->status === 'passed') {
                $total += $scorePerCheck;
            } elseif ($check->status === 'warning') {
                $total += $scorePerCheck * 0.5;
            }
            // 'failed' adds 0
        }

        // Factor in recent execution success rate (last 20 runs)
        $recentRuns = ExecutionRun::where('agent_id', $agent->id)
            ->where('project_id', $project->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        if ($recentRuns->isNotEmpty()) {
            $successRate = $recentRuns->where('status', 'completed')->count() / $recentRuns->count();
            // Blend: 70% health checks, 30% execution success
            $total = ($total * 0.7) + ($successRate * 100 * 0.3);
        }

        return (int) min(100, round($total));
    }

    /**
     * Retire an agent, optionally transferring memories to a successor.
     */
    public function retire(Agent $agent, ?Agent $successor = null): void
    {
        if ($successor) {
            // Transfer memories to successor
            $agent->loadMissing('executionRuns');

            \App\Models\AgentMemory::where('agent_id', $agent->id)
                ->update(['agent_id' => $successor->id]);
        }

        // Disable agent on all projects
        $agent->projectAgents()->update(['is_enabled' => false]);

        // Mark as retired via description prefix
        if (! str_starts_with($agent->description ?? '', '[RETIRED]')) {
            $agent->update([
                'description' => '[RETIRED] ' . ($agent->description ?? ''),
            ]);
        }
    }

    /**
     * Perform a single health check.
     */
    protected function performCheck(Agent $agent, Project $project, string $checkType): array
    {
        return match ($checkType) {
            'mcp_connectivity' => $this->checkMcpConnectivity($agent, $project),
            'skill_validity' => $this->checkSkillValidity($agent, $project),
            'model_availability' => $this->checkModelAvailability($agent),
            'credential_access' => $this->checkCredentialAccess($agent, $project),
            default => ['status' => 'warning', 'details' => ['message' => 'Unknown check type']],
        };
    }

    protected function checkMcpConnectivity(Agent $agent, Project $project): array
    {
        $mcpServers = $agent->mcpServers()
            ->wherePivot('project_id', $project->id)
            ->get();

        if ($mcpServers->isEmpty()) {
            return ['status' => 'passed', 'details' => ['message' => 'No MCP servers bound']];
        }

        // Basic check: verify servers exist and are configured
        $issues = [];
        foreach ($mcpServers as $server) {
            if (empty($server->command) && empty($server->url)) {
                $issues[] = "MCP server '{$server->name}' has no command or URL configured";
            }
        }

        return [
            'status' => empty($issues) ? 'passed' : 'warning',
            'details' => ['servers' => $mcpServers->count(), 'issues' => $issues],
        ];
    }

    protected function checkSkillValidity(Agent $agent, Project $project): array
    {
        $skills = $project->skills()
            ->whereHas('agents', fn ($q) => $q->where('agents.id', $agent->id))
            ->get();

        $issues = [];
        foreach ($skills as $skill) {
            if (empty($skill->body)) {
                $issues[] = "Skill '{$skill->name}' has no body content";
            }
        }

        return [
            'status' => empty($issues) ? 'passed' : 'warning',
            'details' => ['skills' => $skills->count(), 'issues' => $issues],
        ];
    }

    protected function checkModelAvailability(Agent $agent): array
    {
        $model = $agent->model;

        if (empty($model)) {
            return ['status' => 'warning', 'details' => ['message' => 'No model configured']];
        }

        // Check if the required API key is configured
        $prefix = explode('-', $model)[0] ?? '';
        $keyMap = [
            'claude' => 'anthropic_api_key',
            'gpt' => 'openai_api_key',
            'o3' => 'openai_api_key',
            'gemini' => 'google_api_key',
        ];

        $settingKey = $keyMap[$prefix] ?? null;
        if ($settingKey) {
            $configured = ! empty(\App\Models\AppSetting::get($settingKey));

            return [
                'status' => $configured ? 'passed' : 'failed',
                'details' => [
                    'model' => $model,
                    'api_key_configured' => $configured,
                ],
            ];
        }

        return ['status' => 'passed', 'details' => ['model' => $model, 'message' => 'Local or custom model']];
    }

    protected function checkCredentialAccess(Agent $agent, Project $project): array
    {
        // Check if agent has vault access grants
        $grantCount = \App\Models\VaultAccessGrant::active()
            ->where(function ($q) use ($agent, $project) {
                $q->where(function ($q2) use ($agent) {
                    $q2->where('grantee_type', 'agent')
                        ->where('grantee_id', $agent->id);
                })->orWhere(function ($q2) use ($project) {
                    $q2->where('grantee_type', 'project')
                        ->where('grantee_id', $project->id);
                });
            })
            ->count();

        return [
            'status' => 'passed',
            'details' => [
                'vault_grants' => $grantCount,
                'message' => $grantCount > 0
                    ? "{$grantCount} credential(s) accessible"
                    : 'No vault credentials granted',
            ],
        ];
    }
}
