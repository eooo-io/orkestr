<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\Project;
use App\Models\Skill;
use Symfony\Component\Yaml\Yaml;
use ZipArchive;

class BundleExportService
{
    public function __construct(
        protected SkillFileParser $parser,
    ) {}

    /**
     * Export selected skills and agents as a ZIP file.
     *
     * @return string Path to the temporary ZIP file
     */
    public function exportZip(Project $project, array $skillIds, array $agentIds): string
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'agentis_bundle_') . '.zip';
        $zip = new ZipArchive();
        $zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        // Add skills
        $skills = $project->skills()->with('tags')->whereIn('id', $skillIds)->get();
        foreach ($skills as $skill) {
            $frontmatter = $this->buildSkillFrontmatter($skill);
            $content = $this->parser->renderFile($frontmatter, $skill->body ?? '');
            $zip->addFromString("skills/{$skill->slug}.md", $content);
        }

        // Add agents
        $agents = $this->resolveAgents($project, $agentIds);
        if ($agents->isNotEmpty()) {
            $agentData = $agents->map(fn (Agent $agent) => $this->buildAgentData($agent, $project))->values()->all();
            $zip->addFromString('agents.yaml', Yaml::dump($agentData, 4, 2));
        }

        // Add bundle metadata
        $metadata = [
            'name' => $project->name,
            'exported_at' => now()->toIso8601String(),
            'skills_count' => $skills->count(),
            'agents_count' => $agents->count(),
            'version' => '1.0',
        ];
        $zip->addFromString('bundle.yaml', Yaml::dump($metadata, 4, 2));

        $zip->close();

        return $tempPath;
    }

    /**
     * Export selected skills and agents as a JSON-serializable array.
     */
    public function exportJson(Project $project, array $skillIds, array $agentIds): array
    {
        $skills = $project->skills()->with('tags')->whereIn('id', $skillIds)->get();
        $agents = $this->resolveAgents($project, $agentIds);

        return [
            'metadata' => [
                'name' => $project->name,
                'exported_at' => now()->toIso8601String(),
                'skills_count' => $skills->count(),
                'agents_count' => $agents->count(),
                'version' => '1.0',
            ],
            'skills' => $skills->map(function (Skill $skill) {
                return [
                    'slug' => $skill->slug,
                    'name' => $skill->name,
                    'description' => $skill->description,
                    'model' => $skill->model,
                    'max_tokens' => $skill->max_tokens,
                    'tools' => $skill->tools ?? [],
                    'includes' => $skill->includes ?? [],
                    'tags' => $skill->tags->pluck('name')->values()->all(),
                    'body' => $skill->body ?? '',
                ];
            })->values()->all(),
            'agents' => $agents->map(fn (Agent $agent) => $this->buildAgentData($agent, $project))->values()->all(),
        ];
    }

    protected function buildSkillFrontmatter(Skill $skill): array
    {
        return [
            'id' => $skill->slug,
            'name' => $skill->name,
            'description' => $skill->description,
            'tags' => $skill->tags->pluck('name')->values()->all(),
            'model' => $skill->model,
            'max_tokens' => $skill->max_tokens,
            'tools' => $skill->tools ?? [],
            'includes' => $skill->includes ?? [],
            'created_at' => $skill->created_at->toIso8601String(),
            'updated_at' => $skill->updated_at->toIso8601String(),
        ];
    }

    protected function buildAgentData(Agent $agent, Project $project): array
    {
        $pivot = $project->agents()->where('agents.id', $agent->id)->first()?->pivot;

        return [
            'name' => $agent->name,
            'slug' => $agent->slug,
            'role' => $agent->role,
            'description' => $agent->description,
            'base_instructions' => $agent->base_instructions,
            'icon' => $agent->icon,
            'custom_instructions' => $pivot?->custom_instructions,
        ];
    }

    protected function resolveAgents(Project $project, array $agentIds): \Illuminate\Support\Collection
    {
        if (empty($agentIds)) {
            return collect();
        }

        return $project->agents()->whereIn('agents.id', $agentIds)->get();
    }
}
