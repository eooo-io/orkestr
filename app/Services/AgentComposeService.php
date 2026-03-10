<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\Project;
use App\Models\Skill;
use App\Models\SkillVariable;
use Illuminate\Support\Facades\DB;

class AgentComposeService
{
    public function __construct(
        protected SkillCompositionService $compositionService,
        protected TemplateResolver $templateResolver,
    ) {}
    /**
     * Compose the full output for a single project agent.
     *
     * @return array{content: string, token_estimate: int, agent: array, skill_count: int}
     */
    public function compose(Project $project, Agent $agent): array
    {
        $projectAgent = $project->projectAgents()
            ->where('agent_id', $agent->id)
            ->first();

        $customInstructions = $projectAgent?->custom_instructions;

        // Get assigned skill IDs for this agent in this project
        $skillIds = DB::table('agent_skill')
            ->where('project_id', $project->id)
            ->where('agent_id', $agent->id)
            ->pluck('skill_id');

        $skills = $skillIds->isNotEmpty()
            ? Skill::whereIn('id', $skillIds)->orderBy('name')->get()
            : collect();

        $content = $this->render($project, $agent, $customInstructions, $skills);

        return [
            'content' => $content,
            'token_estimate' => $this->estimateTokens($content),
            'agent' => [
                'id' => $agent->id,
                'name' => $agent->name,
                'slug' => $agent->slug,
                'role' => $agent->role,
                'icon' => $agent->icon,
            ],
            'skill_count' => $skills->count(),
        ];
    }

    /**
     * Compose all enabled agents for a project.
     *
     * @return array<int, array{content: string, token_estimate: int, agent: array, skill_count: int}>
     */
    public function composeAll(Project $project): array
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

        return $agents->map(fn (Agent $agent) => $this->compose($project, $agent))->values()->all();
    }

    /**
     * Render the composed markdown output.
     */
    protected function render(Project $project, Agent $agent, ?string $customInstructions, \Illuminate\Support\Collection $skills): string
    {
        $sections = [];

        // Header
        $sections[] = "# {$agent->name}";

        // Base instructions
        if ($agent->base_instructions) {
            $sections[] = trim($agent->base_instructions);
        }

        // Custom project-specific instructions
        if ($customInstructions) {
            $sections[] = "## Project-Specific Instructions\n\n" . trim($customInstructions);
        }

        // Load skill variable values for all assigned skills in this project
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

        // Assigned skills
        if ($skills->isNotEmpty()) {
            $skillSections = ["## Assigned Skills"];

            foreach ($skills as $skill) {
                $resolvedBody = $this->compositionService->resolve($skill);

                // Apply template variable substitution
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

                $skillContent = "### {$skill->name}";
                if ($skill->description) {
                    $skillContent .= "\n\n> {$skill->description}";
                }
                $skillContent .= "\n\n" . trim($resolvedBody);
                $skillSections[] = $skillContent;
            }

            $sections[] = implode("\n\n", $skillSections);
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
