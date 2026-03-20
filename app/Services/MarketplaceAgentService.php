<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\MarketplaceAgent;
use App\Models\Project;
use App\Models\ProjectAgent;
use App\Models\Skill;
use App\Models\Tag;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MarketplaceAgentService
{
    /**
     * Publish an agent (with its skills, workflow, and wiring) to the marketplace.
     */
    public function publish(Agent $agent, Project $project, array $meta): MarketplaceAgent
    {
        // Serialize the full agent config
        $agentConfig = $this->serializeAgentConfig($agent, $project);

        // Collect assigned skills with their full bodies
        $skillsConfig = $this->collectSkillsConfig($agent, $project);

        // Include workflow if agent has one in this project
        $workflowConfig = $this->collectWorkflowConfig($agent, $project);

        // Include MCP/A2A wiring
        $wiringConfig = $this->collectWiringConfig($agent, $project);

        // Build unique slug
        $slug = $this->uniqueSlug(Str::slug($agent->name));

        return MarketplaceAgent::create([
            'name' => $agent->name,
            'slug' => $slug,
            'description' => $meta['description'] ?? $agent->description,
            'category' => $meta['category'] ?? null,
            'tags' => $meta['tags'] ?? [],
            'agent_config' => $agentConfig,
            'skills_config' => $skillsConfig,
            'workflow_config' => $workflowConfig,
            'wiring_config' => $wiringConfig,
            'author' => $meta['author'] ?? 'Unknown',
            'author_url' => $meta['author_url'] ?? null,
            'source' => 'project',
            'version' => $meta['version'] ?? '1.0.0',
            'screenshots' => $meta['screenshots'] ?? null,
            'readme' => $meta['readme'] ?? null,
            'published_by' => $meta['user_id'] ?? null,
        ]);
    }

    /**
     * Install a marketplace agent into a project.
     */
    public function install(MarketplaceAgent $listing, Project $project, ?int $userId = null): Agent
    {
        return DB::transaction(function () use ($listing, $project, $userId) {
            $config = $listing->agent_config;

            // Create the agent from serialized config
            $agent = Agent::create([
                'name' => $config['name'],
                'role' => $config['role'] ?? 'general',
                'description' => $config['description'] ?? null,
                'base_instructions' => $config['base_instructions'] ?? '',
                'persona_prompt' => $config['persona_prompt'] ?? null,
                'persona' => $config['persona'] ?? null,
                'model' => $config['model'] ?? null,
                'icon' => $config['icon'] ?? null,
                'objective_template' => $config['objective_template'] ?? null,
                'success_criteria' => $config['success_criteria'] ?? null,
                'max_iterations' => $config['max_iterations'] ?? null,
                'timeout_seconds' => $config['timeout_seconds'] ?? null,
                'input_schema' => $config['input_schema'] ?? null,
                'memory_sources' => $config['memory_sources'] ?? null,
                'context_strategy' => $config['context_strategy'] ?? null,
                'planning_mode' => $config['planning_mode'] ?? null,
                'temperature' => $config['temperature'] ?? null,
                'system_prompt' => $config['system_prompt'] ?? null,
                'eval_criteria' => $config['eval_criteria'] ?? null,
                'output_schema' => $config['output_schema'] ?? null,
                'loop_condition' => $config['loop_condition'] ?? null,
                'delegation_rules' => $config['delegation_rules'] ?? null,
                'can_delegate' => $config['can_delegate'] ?? false,
                'custom_tools' => $config['custom_tools'] ?? null,
                'fallback_models' => $config['fallback_models'] ?? null,
                'routing_strategy' => $config['routing_strategy'] ?? 'default',
                'autonomy_level' => $config['autonomy_level'] ?? 'semi_autonomous',
                'allowed_tools' => $config['allowed_tools'] ?? null,
                'blocked_tools' => $config['blocked_tools'] ?? null,
                'data_access_scope' => $config['data_access_scope'] ?? null,
                'memory_enabled' => $config['memory_enabled'] ?? false,
                'auto_remember' => $config['auto_remember'] ?? false,
                'memory_recall_limit' => $config['memory_recall_limit'] ?? null,
                'created_by' => $userId,
            ]);

            // Attach agent to project
            ProjectAgent::create([
                'project_id' => $project->id,
                'agent_id' => $agent->id,
                'is_enabled' => true,
                'custom_instructions' => $config['custom_instructions'] ?? null,
            ]);

            // Import skills from skills_config
            $skillIdMap = $this->importSkills($listing->skills_config ?? [], $project);

            // Wire up agent-skill assignments
            if (! empty($skillIdMap)) {
                $rows = collect($skillIdMap)->map(fn ($skillId) => [
                    'project_id' => $project->id,
                    'agent_id' => $agent->id,
                    'skill_id' => $skillId,
                ])->values()->toArray();

                DB::table('agent_skill')->insert($rows);
            }

            // Increment downloads
            $listing->increment('downloads');

            return $agent;
        });
    }

    /**
     * Return a structured preview of a marketplace agent listing.
     */
    public function preview(MarketplaceAgent $listing): array
    {
        $agentConfig = $listing->agent_config;
        $skillsConfig = $listing->skills_config ?? [];
        $workflowConfig = $listing->workflow_config;
        $wiringConfig = $listing->wiring_config;

        // Count total tools across agent and skills
        $toolCount = count($agentConfig['custom_tools'] ?? []);
        foreach ($skillsConfig as $skill) {
            $toolCount += count($skill['tools'] ?? []);
        }

        // Build skill list
        $skills = collect($skillsConfig)->map(fn ($s) => [
            'name' => $s['name'] ?? $s['slug'] ?? 'Unnamed',
            'description' => $s['description'] ?? null,
            'model' => $s['model'] ?? null,
            'tool_count' => count($s['tools'] ?? []),
        ])->values()->all();

        // Workflow summary
        $workflowSummary = null;
        if ($workflowConfig) {
            $workflowSummary = [
                'name' => $workflowConfig['name'] ?? 'Unnamed Workflow',
                'description' => $workflowConfig['description'] ?? null,
                'step_count' => count($workflowConfig['steps'] ?? []),
                'trigger_type' => $workflowConfig['trigger_type'] ?? null,
            ];
        }

        // Wiring summary
        $wiringSummary = null;
        if ($wiringConfig) {
            $wiringSummary = [
                'mcp_server_count' => count($wiringConfig['mcp_servers'] ?? []),
                'a2a_agent_count' => count($wiringConfig['a2a_agents'] ?? []),
                'delegation_rules' => $wiringConfig['delegation_rules'] ?? [],
            ];
        }

        return [
            'agent' => [
                'name' => $agentConfig['name'] ?? $listing->name,
                'role' => $agentConfig['role'] ?? null,
                'description' => $agentConfig['description'] ?? $listing->description,
                'model' => $agentConfig['model'] ?? null,
                'icon' => $agentConfig['icon'] ?? null,
                'planning_mode' => $agentConfig['planning_mode'] ?? null,
                'context_strategy' => $agentConfig['context_strategy'] ?? null,
                'max_iterations' => $agentConfig['max_iterations'] ?? null,
                'can_delegate' => $agentConfig['can_delegate'] ?? false,
            ],
            'skills' => $skills,
            'skill_count' => count($skills),
            'tool_count' => $toolCount,
            'workflow' => $workflowSummary,
            'wiring' => $wiringSummary,
            'version' => $listing->version,
            'author' => $listing->author,
            'author_url' => $listing->author_url,
            'downloads' => $listing->downloads,
            'upvotes' => $listing->upvotes,
            'readme' => $listing->readme,
        ];
    }

    // --- Private helpers ---

    private function serializeAgentConfig(Agent $agent, Project $project): array
    {
        $pivot = $project->agents()->where('agents.id', $agent->id)->first()?->pivot;

        return array_filter([
            'name' => $agent->name,
            'role' => $agent->role,
            'description' => $agent->description,
            'base_instructions' => $agent->base_instructions,
            'persona_prompt' => $agent->persona_prompt,
            'persona' => $agent->persona,
            'model' => $agent->model,
            'fallback_models' => $agent->fallback_models,
            'routing_strategy' => $agent->routing_strategy,
            'icon' => $agent->icon,
            'objective_template' => $agent->objective_template,
            'success_criteria' => $agent->success_criteria,
            'max_iterations' => $agent->max_iterations,
            'timeout_seconds' => $agent->timeout_seconds,
            'input_schema' => $agent->input_schema,
            'memory_sources' => $agent->memory_sources,
            'context_strategy' => $agent->context_strategy,
            'planning_mode' => $agent->planning_mode,
            'temperature' => $agent->temperature ? (float) $agent->temperature : null,
            'system_prompt' => $agent->system_prompt,
            'eval_criteria' => $agent->eval_criteria,
            'output_schema' => $agent->output_schema,
            'loop_condition' => $agent->loop_condition,
            'delegation_rules' => $agent->delegation_rules,
            'can_delegate' => $agent->can_delegate,
            'custom_tools' => $agent->custom_tools,
            'autonomy_level' => $agent->autonomy_level,
            'allowed_tools' => $agent->allowed_tools,
            'blocked_tools' => $agent->blocked_tools,
            'data_access_scope' => $agent->data_access_scope,
            'memory_enabled' => $agent->memory_enabled,
            'auto_remember' => $agent->auto_remember,
            'memory_recall_limit' => $agent->memory_recall_limit,
            'custom_instructions' => $pivot?->custom_instructions,
        ], fn ($v) => $v !== null);
    }

    private function collectSkillsConfig(Agent $agent, Project $project): array
    {
        $skillIds = DB::table('agent_skill')
            ->where('project_id', $project->id)
            ->where('agent_id', $agent->id)
            ->pluck('skill_id');

        if ($skillIds->isEmpty()) {
            return [];
        }

        $skills = Skill::with('tags')
            ->whereIn('id', $skillIds)
            ->get();

        return $skills->map(fn (Skill $skill) => [
            'slug' => $skill->slug,
            'name' => $skill->name,
            'description' => $skill->description,
            'model' => $skill->model,
            'max_tokens' => $skill->max_tokens,
            'tools' => $skill->tools ?? [],
            'includes' => $skill->includes ?? [],
            'body' => $skill->body ?? '',
            'tags' => $skill->tags->pluck('name')->values()->all(),
            'template_variables' => $skill->template_variables ?? [],
        ])->values()->all();
    }

    private function collectWorkflowConfig(Agent $agent, Project $project): ?array
    {
        // Find workflows in this project that have steps assigned to this agent
        $workflow = $project->workflows()
            ->with(['steps.agent', 'edges'])
            ->whereHas('steps', fn ($q) => $q->where('agent_id', $agent->id))
            ->first();

        if (! $workflow) {
            return null;
        }

        return [
            'name' => $workflow->name,
            'slug' => $workflow->slug,
            'description' => $workflow->description,
            'trigger_type' => $workflow->trigger_type,
            'steps' => $workflow->steps->map(fn ($step) => [
                'type' => $step->type,
                'name' => $step->name,
                'agent_slug' => $step->agent?->slug,
                'position_x' => $step->position_x,
                'position_y' => $step->position_y,
                'config' => $step->config,
            ])->toArray(),
            'edges' => $workflow->edges->map(fn ($edge) => [
                'source_step_name' => $workflow->steps->firstWhere('id', $edge->source_step_id)?->name,
                'target_step_name' => $workflow->steps->firstWhere('id', $edge->target_step_id)?->name,
                'condition_expression' => $edge->condition_expression,
                'label' => $edge->label,
                'priority' => $edge->priority,
            ])->toArray(),
        ];
    }

    private function collectWiringConfig(Agent $agent, Project $project): ?array
    {
        $mcpServers = DB::table('agent_mcp_server')
            ->join('project_mcp_servers', 'agent_mcp_server.project_mcp_server_id', '=', 'project_mcp_servers.id')
            ->where('agent_mcp_server.project_id', $project->id)
            ->where('agent_mcp_server.agent_id', $agent->id)
            ->select('project_mcp_servers.*', 'agent_mcp_server.config_overrides')
            ->get()
            ->map(fn ($s) => [
                'name' => $s->name,
                'transport' => $s->transport,
                'command' => $s->command,
                'args' => json_decode($s->args, true),
                'url' => $s->url,
                'config_overrides' => json_decode($s->config_overrides, true),
            ])->all();

        $a2aAgents = DB::table('agent_a2a_agent')
            ->join('project_a2a_agents', 'agent_a2a_agent.project_a2a_agent_id', '=', 'project_a2a_agents.id')
            ->where('agent_a2a_agent.project_id', $project->id)
            ->where('agent_a2a_agent.agent_id', $agent->id)
            ->select('project_a2a_agents.*', 'agent_a2a_agent.config_overrides')
            ->get()
            ->map(fn ($a) => [
                'name' => $a->name,
                'url' => $a->url,
                'capabilities' => json_decode($a->capabilities ?? 'null', true),
                'config_overrides' => json_decode($a->config_overrides, true),
            ])->all();

        if (empty($mcpServers) && empty($a2aAgents) && empty($agent->delegation_rules)) {
            return null;
        }

        return [
            'mcp_servers' => $mcpServers,
            'a2a_agents' => $a2aAgents,
            'delegation_rules' => $agent->delegation_rules ?? [],
        ];
    }

    private function importSkills(array $skillsConfig, Project $project): array
    {
        $createdIds = [];

        foreach ($skillsConfig as $skillData) {
            $slug = $skillData['slug'] ?? Str::slug($skillData['name'] ?? 'imported-skill');
            $baseSlug = $slug;
            $counter = 1;

            // Avoid slug conflicts within the project
            while ($project->skills()->where('slug', $slug)->exists()) {
                $slug = "{$baseSlug}-{$counter}";
                $counter++;
            }

            $skill = $project->skills()->create([
                'slug' => $slug,
                'name' => $skillData['name'] ?? $slug,
                'description' => $skillData['description'] ?? null,
                'model' => $skillData['model'] ?? null,
                'max_tokens' => $skillData['max_tokens'] ?? null,
                'tools' => $skillData['tools'] ?? [],
                'includes' => $skillData['includes'] ?? [],
                'body' => $skillData['body'] ?? '',
                'template_variables' => $skillData['template_variables'] ?? [],
            ]);

            // Sync tags
            if (! empty($skillData['tags'])) {
                $tagIds = collect($skillData['tags'])->map(function (string $name) {
                    return Tag::firstOrCreate(['name' => trim($name)])->id;
                });
                $skill->tags()->sync($tagIds);
            }

            // Create v1 version
            $skill->versions()->create([
                'version_number' => 1,
                'frontmatter' => [
                    'id' => $slug,
                    'name' => $skill->name,
                    'description' => $skill->description,
                    'model' => $skill->model,
                    'max_tokens' => $skill->max_tokens,
                    'tools' => $skill->tools ?? [],
                    'tags' => $skillData['tags'] ?? [],
                ],
                'body' => $skill->body,
                'note' => 'Installed from agent marketplace template',
                'saved_at' => now(),
            ]);

            $createdIds[] = $skill->id;
        }

        return $createdIds;
    }

    private function uniqueSlug(string $slug): string
    {
        $baseSlug = $slug;
        $counter = 1;

        while (MarketplaceAgent::where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
