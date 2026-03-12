<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\ProviderSyncService;
use Illuminate\Http\JsonResponse;

class VisualizationController extends Controller
{
    public function __construct(
        protected ProviderSyncService $syncService,
    ) {}

    /**
     * GET /api/projects/{project}/graph
     *
     * Returns the full project configuration graph for visualization.
     */
    public function graph(Project $project): JsonResponse
    {
        $project->load([
            'skills.tags',
            'providers',
        ]);

        // Skills
        $skills = $project->skills->map(fn ($skill) => [
            'id' => $skill->id,
            'slug' => $skill->slug,
            'name' => $skill->name,
            'description' => $skill->description,
            'model' => $skill->model,
            'includes' => $skill->includes ?? [],
            'conditions' => $skill->conditions,
            'template_variables' => $skill->template_variables,
            'tags' => $skill->tags->pluck('name')->values()->all(),
            'token_estimate' => (int) ceil(strlen($skill->body ?? '') / 4),
        ]);

        // Build skill slug → id map for includes resolution
        $slugToId = $project->skills->pluck('id', 'slug')->toArray();

        // Skill dependency edges
        $skillEdges = [];
        foreach ($project->skills as $skill) {
            foreach ($skill->includes ?? [] as $includedSlug) {
                if (isset($slugToId[$includedSlug])) {
                    $skillEdges[] = [
                        'source' => $skill->id,
                        'target' => $slugToId[$includedSlug],
                        'type' => 'includes',
                    ];
                }
            }
        }

        // Detect circular dependencies
        $circularDeps = $this->detectCircularDeps($project->skills);

        // Agents with project-specific config
        $projectAgents = $project->projectAgents()
            ->with(['agent', 'skills'])
            ->get();

        $agents = $projectAgents->map(fn ($pa) => [
            'id' => $pa->agent->id,
            'name' => $pa->agent->name,
            'slug' => $pa->agent->slug,
            'role' => $pa->agent->role,
            'icon' => $pa->agent->icon,
            'is_enabled' => $pa->is_enabled,
            'has_custom_instructions' => ! empty($pa->custom_instructions),
            'skill_ids' => $pa->skills->pluck('id')->values()->all(),
        ]);

        // Agent → skill edges
        $agentEdges = [];
        foreach ($projectAgents as $pa) {
            foreach ($pa->skills as $skill) {
                $agentEdges[] = [
                    'source_type' => 'agent',
                    'source' => $pa->agent->id,
                    'target_type' => 'skill',
                    'target' => $skill->id,
                    'type' => 'assigned',
                ];
            }
        }

        // Providers
        $providers = $project->providers->map(fn ($p) => [
            'slug' => $p->provider_slug,
            'name' => ucfirst($p->provider_slug),
        ]);

        // MCP Servers
        $mcpServers = $project->mcpServers->map(fn ($s) => [
            'id' => $s->id,
            'name' => $s->name,
            'transport' => $s->transport,
        ]);

        // Sync output paths per provider
        $syncOutputs = [];
        foreach ($project->providers as $provider) {
            $driver = $this->syncService->getDriver($provider->provider_slug);
            if ($driver) {
                $paths = $driver->getOutputPaths($project);
                $syncOutputs[$provider->provider_slug] = array_map(
                    fn ($p) => str_replace(rtrim($project->resolved_path, '/') . '/', '', $p),
                    $paths,
                );
            }
        }

        return response()->json([
            'data' => [
                'project' => [
                    'id' => $project->id,
                    'name' => $project->name,
                    'synced_at' => $project->synced_at?->toIso8601String(),
                ],
                'skills' => $skills,
                'skill_edges' => $skillEdges,
                'circular_deps' => $circularDeps,
                'agents' => $agents,
                'agent_edges' => $agentEdges,
                'providers' => $providers,
                'mcp_servers' => $mcpServers,
                'sync_outputs' => $syncOutputs,
            ],
        ]);
    }

    /**
     * Detect circular dependencies in skill includes.
     */
    private function detectCircularDeps($skills): array
    {
        $slugMap = $skills->keyBy('slug');
        $circular = [];

        foreach ($skills as $skill) {
            $visited = [];
            $this->walkIncludes($skill->slug, $slugMap, $visited, $circular);
        }

        return array_unique($circular);
    }

    private function walkIncludes(string $slug, $slugMap, array &$visited, array &$circular): void
    {
        if (in_array($slug, $visited)) {
            $circular[] = $slug;

            return;
        }

        $visited[] = $slug;
        $skill = $slugMap->get($slug);

        if ($skill) {
            foreach ($skill->includes ?? [] as $includedSlug) {
                $this->walkIncludes($includedSlug, $slugMap, $visited, $circular);
            }
        }

        array_pop($visited);
    }
}
