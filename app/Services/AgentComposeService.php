<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\Project;
use App\Models\ProjectAgent;
use App\Models\Skill;
use App\Models\SkillVariable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class AgentComposeService
{
    public function __construct(
        protected SkillCompositionService $compositionService,
        protected TemplateResolver $templateResolver,
    ) {}

    /**
     * Compose the full markdown output for a single project agent.
     *
     * @param  string  $depth  Compose depth: 'index', 'full', or 'deep'
     * @return array{content: string, token_estimate: int, agent: array, skill_count: int}
     */
    public function compose(Project $project, Agent $agent, string $depth = 'full'): array
    {
        $projectAgent = $project->projectAgents()
            ->where('agent_id', $agent->id)
            ->first();

        $customInstructions = $projectAgent?->custom_instructions;

        $skills = $this->getAssignedSkills($project, $agent);
        $content = $this->render($project, $agent, $customInstructions, $skills, $depth);

        return [
            'content' => $content,
            'token_estimate' => $this->estimateTokens($content),
            'agent' => [
                'id' => $agent->id,
                'name' => $agent->name,
                'slug' => $agent->slug,
                'role' => $agent->role,
                'icon' => $agent->icon,
                'persona' => $agent->persona,
                'display_name' => $agent->displayName(),
            ],
            'skill_count' => $skills->count(),
        ];
    }

    /**
     * Compose a structured agent definition for a single project agent.
     *
     * Returns the full loop definition with resolved fields, tools, and skills —
     * ready for export to Claude Agent SDK, LangGraph, CrewAI, or generic JSON.
     */
    public function composeStructured(Project $project, Agent $agent): array
    {
        $projectAgent = $project->projectAgents()
            ->where('agent_id', $agent->id)
            ->first();

        $skills = $this->getAssignedSkills($project, $agent);
        $resolvedSkills = $this->resolveSkillBodies($project, $skills);

        // Resolve MCP servers bound to this agent in this project
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
                'env' => json_decode($s->env, true),
                'config_overrides' => json_decode($s->config_overrides, true),
            ]);

        // Resolve A2A agents bound to this agent
        $a2aAgents = DB::table('agent_a2a_agent')
            ->join('project_a2a_agents', 'agent_a2a_agent.project_a2a_agent_id', '=', 'project_a2a_agents.id')
            ->where('agent_a2a_agent.project_id', $project->id)
            ->where('agent_a2a_agent.agent_id', $agent->id)
            ->select('project_a2a_agents.*', 'agent_a2a_agent.config_overrides')
            ->get()
            ->map(fn ($a) => [
                'name' => $a->name,
                'url' => $a->url,
                'description' => $a->description,
                'skills' => json_decode($a->skills, true),
                'config_overrides' => json_decode($a->config_overrides, true),
            ]);

        // Build the system prompt from composed markdown
        $systemPrompt = $this->render(
            $project,
            $agent,
            $projectAgent?->custom_instructions,
            $skills,
        );

        // Resolve overrides from project_agent pivot
        $model = $projectAgent?->model_override ?? $agent->model;
        $temperature = $projectAgent?->temperature_override ?? $agent->temperature;
        $maxIterations = $projectAgent?->max_iterations_override ?? $agent->max_iterations;
        $timeoutSeconds = $projectAgent?->timeout_override ?? $agent->timeout_seconds;
        $contextStrategy = $projectAgent?->context_strategy_override ?? $agent->context_strategy;
        $planningMode = $projectAgent?->planning_mode_override ?? $agent->planning_mode;
        $objective = $projectAgent?->objective_override ?? $agent->objective_template;
        $successCriteria = $projectAgent?->success_criteria_override ?? $agent->success_criteria;
        $customTools = $projectAgent?->custom_tools_override ?? $agent->custom_tools;

        return [
            // Identity
            'agent' => [
                'id' => $agent->id,
                'uuid' => $agent->uuid,
                'name' => $agent->name,
                'slug' => $agent->slug,
                'role' => $agent->role,
                'icon' => $agent->icon,
                'description' => $agent->description,
            ],

            // Composed system prompt (markdown)
            'system_prompt' => $systemPrompt,
            'token_estimate' => $this->estimateTokens($systemPrompt),

            // Model config
            'model' => $model,
            'temperature' => $temperature ? (float) $temperature : null,

            // Goal
            'goal' => [
                'objective' => $objective,
                'success_criteria' => $successCriteria,
                'max_iterations' => $maxIterations,
                'timeout_seconds' => $timeoutSeconds,
                'loop_condition' => $agent->loop_condition,
            ],

            // Perception
            'perception' => [
                'input_schema' => $agent->input_schema,
                'memory_sources' => $agent->memory_sources,
                'context_strategy' => $contextStrategy,
            ],

            // Reasoning
            'reasoning' => [
                'planning_mode' => $planningMode,
                'persona_prompt' => $agent->persona_prompt,
            ],

            // Actions / Tools
            'tools' => [
                'mcp_servers' => $mcpServers->values(),
                'a2a_agents' => $a2aAgents->values(),
                'custom_tools' => $customTools,
                'memory_tools' => $agent->memory_enabled
                    ? \App\Services\Mcp\MemoryMcpServer::toolDefinitions()
                    : [],
            ],

            // Memory config
            'memory' => [
                'enabled' => (bool) $agent->memory_enabled,
                'auto_remember' => (bool) $agent->auto_remember,
                'recall_limit' => $agent->memory_recall_limit ?? 5,
            ],

            // Skills (resolved)
            'skills' => $resolvedSkills,
            'skill_count' => $skills->count(),

            // Observation
            'observation' => [
                'eval_criteria' => $agent->eval_criteria,
                'output_schema' => $agent->output_schema,
            ],

            // Orchestration
            'orchestration' => [
                'can_delegate' => $agent->can_delegate,
                'delegation_rules' => $agent->delegation_rules,
                'parent_agent_id' => $agent->parent_agent_id,
                'parent_agent' => $agent->parentAgent ? [
                    'id' => $agent->parentAgent->id,
                    'name' => $agent->parentAgent->name,
                    'slug' => $agent->parentAgent->slug,
                ] : null,
            ],
        ];
    }

    /**
     * Compose all enabled agents for a project.
     *
     * @param  string  $depth  Compose depth: 'index', 'full', or 'deep'
     * @return array<int, array{content: string, token_estimate: int, agent: array, skill_count: int}>
     */
    public function composeAll(Project $project, string $depth = 'full'): array
    {
        $enabledAgentIds = $project->projectAgents()
            ->where('is_enabled', true)
            ->pluck('agent_id');

        if ($enabledAgentIds->isEmpty()) {
            return [];
        }

        $agents = Agent::whereIn('id', $enabledAgentIds)
            ->orderBy('sort_order')
            ->get();

        return $agents->map(fn (Agent $agent) => $this->compose($project, $agent, $depth))->values()->all();
    }

    /**
     * Compose all enabled agents as structured definitions.
     */
    public function composeAllStructured(Project $project): array
    {
        $enabledAgentIds = $project->projectAgents()
            ->where('is_enabled', true)
            ->pluck('agent_id');

        if ($enabledAgentIds->isEmpty()) {
            return [];
        }

        $agents = Agent::whereIn('id', $enabledAgentIds)
            ->with('parentAgent')
            ->orderBy('sort_order')
            ->get();

        return $agents->map(fn (Agent $agent) => $this->composeStructured($project, $agent))->values()->all();
    }

    /**
     * Get assigned skills for an agent in a project.
     */
    protected function getAssignedSkills(Project $project, Agent $agent): \Illuminate\Support\Collection
    {
        $skillIds = DB::table('agent_skill')
            ->where('project_id', $project->id)
            ->where('agent_id', $agent->id)
            ->pluck('skill_id');

        return $skillIds->isNotEmpty()
            ? Skill::whereIn('id', $skillIds)->orderBy('name')->get()
            : collect();
    }

    /**
     * Resolve skill bodies with includes and template variables.
     *
     * @param  string  $depth  One of 'index', 'full', 'deep'
     *   - index: name + summary only (~100 tokens per skill)
     *   - full: name + summary + resolved body (default, backward-compatible)
     *   - deep: full + inlined asset content from skill folders
     */
    protected function resolveSkillBodies(Project $project, \Illuminate\Support\Collection $skills, string $depth = 'full'): array
    {
        // Index mode: minimal info only
        if ($depth === 'index') {
            return $skills->map(function (Skill $skill) {
                $summary = $skill->summary ?? $skill->description ?? '';

                return [
                    'id' => $skill->id,
                    'slug' => $skill->slug,
                    'name' => $skill->name,
                    'summary' => $summary,
                    'description' => $skill->description,
                    'model' => $skill->model,
                    'tools' => $skill->tools,
                    'body' => '',
                    'token_estimate' => $this->estimateTokens("{$skill->name}\n{$summary}"),
                ];
            })->values()->all();
        }

        $skillIds = $skills->pluck('id')->all();
        $allVariables = [];

        if (! empty($skillIds)) {
            $allVariables = SkillVariable::where('project_id', $project->id)
                ->whereIn('skill_id', $skillIds)
                ->get()
                ->groupBy('skill_id')
                ->map(fn ($vars) => $vars->pluck('value', 'key')->all())
                ->all();
        }

        return $skills->map(function (Skill $skill) use ($project, $allVariables, $depth) {
            $resolvedBody = $this->compositionService->resolve($skill);

            $variables = $allVariables[$skill->id] ?? [];
            foreach ($skill->template_variables ?? [] as $def) {
                $name = $def['name'] ?? null;
                if ($name && ! array_key_exists($name, $variables) && isset($def['default'])) {
                    $variables[$name] = $def['default'];
                }
            }
            if (! empty($variables)) {
                $resolvedBody = $this->templateResolver->resolve($resolvedBody, $variables);
            }

            // Deep mode: append asset content
            if ($depth === 'deep') {
                $assetContent = $this->resolveAssetContent($project, $skill);
                if ($assetContent) {
                    $resolvedBody .= "\n\n" . $assetContent;
                }
            }

            // Append active gotchas section
            $gotchaSection = $this->buildGotchaSection($skill);
            if ($gotchaSection) {
                $resolvedBody .= "\n\n" . $gotchaSection;
            }

            return [
                'id' => $skill->id,
                'slug' => $skill->slug,
                'name' => $skill->name,
                'summary' => $skill->summary,
                'description' => $skill->description,
                'model' => $skill->model,
                'tools' => $skill->tools,
                'body' => $resolvedBody,
                'token_estimate' => $this->estimateTokens($resolvedBody),
            ];
        })->values()->all();
    }

    /**
     * Build a gotcha/known-issues section from active gotchas.
     */
    protected function buildGotchaSection(Skill $skill): string
    {
        $gotchas = $skill->activeGotchas()->orderByRaw(
            "CASE severity WHEN 'critical' THEN 0 WHEN 'warning' THEN 1 ELSE 2 END"
        )->get();

        if ($gotchas->isEmpty()) {
            return '';
        }

        $severityIcons = ['critical' => 'CRITICAL', 'warning' => 'WARNING', 'info' => 'NOTE'];
        $lines = ["### Known Issues\n"];

        foreach ($gotchas as $gotcha) {
            $icon = $severityIcons[$gotcha->severity] ?? 'NOTE';
            $lines[] = "- **[{$icon}]** {$gotcha->title}: {$gotcha->description}";
        }

        return implode("\n", $lines);
    }

    /**
     * Resolve text-based asset content for deep compose mode.
     */
    protected function resolveAssetContent(Project $project, Skill $skill): string
    {
        $manifestService = app(AgentisManifestService::class);
        $folderPath = $manifestService->getSkillFolderPath($project->resolved_path, $skill->slug);

        if (! $folderPath) {
            return '';
        }

        $parser = app(SkillFileParser::class);
        $assets = $parser->inventoryAssets($folderPath);

        if (empty($assets)) {
            return '';
        }

        $textExtensions = ['md', 'txt', 'json', 'yaml', 'yml', 'csv', 'xml', 'sh', 'py', 'js', 'ts', 'php', 'sql'];
        $sections = ["### Skill Assets\n"];

        foreach ($assets as $asset) {
            $ext = strtolower($asset['type']);
            if (! in_array($ext, $textExtensions) || $asset['size'] > 10240) {
                continue;
            }

            $fullPath = $folderPath . '/' . $asset['path'];
            if (File::exists($fullPath)) {
                $content = File::get($fullPath);
                $sections[] = "#### `{$asset['path']}`\n\n```{$ext}\n{$content}\n```\n";
            }
        }

        return count($sections) > 1 ? implode("\n", $sections) : '';
    }

    /**
     * Render the composed markdown output.
     *
     * @param  string  $depth  Compose depth: 'index', 'full', or 'deep'
     */
    protected function render(Project $project, Agent $agent, ?string $customInstructions, \Illuminate\Support\Collection $skills, string $depth = 'full'): string
    {
        $sections = [];

        // Header — use persona name if available
        $sections[] = "# {$agent->displayName()}";

        // Persona context
        if ($personaContext = $agent->personaContext()) {
            $sections[] = $personaContext;
        }

        // Base instructions
        if ($agent->base_instructions) {
            $sections[] = trim($agent->base_instructions);
        }

        // Custom project-specific instructions
        if ($customInstructions) {
            $sections[] = "## Project-Specific Instructions\n\n" . trim($customInstructions);
        }

        // Resolved skill sections
        $resolvedSkills = $this->resolveSkillBodies($project, $skills, $depth);

        if (! empty($resolvedSkills)) {
            if ($depth === 'index') {
                // Index mode: just list skill names and summaries
                $skillSections = ["## Available Skills"];
                foreach ($resolvedSkills as $skill) {
                    $summary = $skill['summary'] ?? $skill['description'] ?? '';
                    $skillSections[] = "- **{$skill['name']}**: {$summary}";
                }
                $sections[] = implode("\n", $skillSections);
            } else {
                $skillSections = ["## Assigned Skills"];
                foreach ($resolvedSkills as $skill) {
                    $skillContent = "### {$skill['name']}";
                    if ($skill['description']) {
                        $skillContent .= "\n\n> {$skill['description']}";
                    }
                    $skillContent .= "\n\n" . trim($skill['body']);
                    $skillSections[] = $skillContent;
                }
                $sections[] = implode("\n\n", $skillSections);
            }
        }

        return implode("\n\n", $sections) . "\n";
    }

    /**
     * Rough token estimate (1 token ≈ 4 characters for English text).
     */
    public function estimateTokens(string $text): int
    {
        return (int) ceil(mb_strlen($text) / 4);
    }
}
