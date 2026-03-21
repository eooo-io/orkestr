<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\Project;
use App\Models\Skill;
use App\Models\Workflow;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;
use ZipArchive;

class BundleExportService
{
    public function __construct(
        protected SkillFileParser $parser,
        protected ManifestService $manifestService,
    ) {}

    /**
     * Export selected skills and agents as a ZIP file with the given content format.
     *
     * @param  string  $contentFormat  One of: json, yaml, markdown, toml
     * @return string Path to the temporary ZIP file
     */
    public function exportZip(Project $project, array $skillIds, array $agentIds, string $contentFormat = 'markdown', array $workflowIds = []): string
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'orkestr_bundle_') . '.zip';
        $zip = new ZipArchive();
        $zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $skills = $project->skills()->with('tags')->whereIn('id', $skillIds)->get();
        $agents = $this->resolveAgents($project, $agentIds);

        // Add skills in the chosen format
        foreach ($skills as $skill) {
            $this->addSkillToZip($zip, $skill, $contentFormat);
        }

        // Add agents
        if ($agents->isNotEmpty()) {
            $agentData = $agents->map(fn (Agent $agent) => $this->buildAgentData($agent, $project))->values()->all();
            $zip->addFromString('agents.yaml', Yaml::dump($agentData, 4, 2));
        }

        // Add workflows
        $workflows = $this->resolveWorkflows($project, $workflowIds);
        if ($workflows->isNotEmpty()) {
            $workflowData = $workflows->map(fn (Workflow $wf) => $this->buildWorkflowData($wf))->values()->all();
            $zip->addFromString('workflows.yaml', Yaml::dump($workflowData, 6, 2));
        }

        // Add bundle metadata
        $metadata = [
            'name' => $project->name,
            'exported_at' => now()->toIso8601String(),
            'skills_count' => $skills->count(),
            'agents_count' => $agents->count(),
            'workflows_count' => $workflows->count(),
            'content_format' => $contentFormat,
            'version' => '1.0',
        ];
        $zip->addFromString('bundle.yaml', Yaml::dump($metadata, 4, 2));

        $zip->close();

        return $tempPath;
    }

    protected function addSkillToZip(ZipArchive $zip, Skill $skill, string $format): void
    {
        $frontmatter = $this->buildSkillFrontmatter($skill);

        // Check if this skill has a folder with assets
        $project = $skill->project;
        $folderPath = $project
            ? $this->manifestService->getSkillFolderPath($project->resolved_path, $skill->slug)
            : null;
        $hasAssets = $folderPath !== null;
        $prefix = $hasAssets ? "skills/{$skill->slug}" : 'skills';

        match ($format) {
            'json' => $zip->addFromString(
                $hasAssets ? "{$prefix}/skill.json" : "{$prefix}/{$skill->slug}.json",
                json_encode(array_merge($frontmatter, ['body' => $skill->body ?? '']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            ),
            'yaml' => $zip->addFromString(
                $hasAssets ? "{$prefix}/skill.yaml" : "{$prefix}/{$skill->slug}.yaml",
                Yaml::dump(array_merge($frontmatter, ['body' => $skill->body ?? '']), 4, 2),
            ),
            'toon' => $zip->addFromString(
                $hasAssets ? "{$prefix}/skill.toon" : "{$prefix}/{$skill->slug}.toon",
                $this->renderToon($frontmatter, $skill->body ?? ''),
            ),
            default => $zip->addFromString(
                $hasAssets ? "{$prefix}/skill.md" : "{$prefix}/{$skill->slug}.md",
                $this->parser->renderFile($frontmatter, $skill->body ?? ''),
            ),
        };

        // Include folder assets in the ZIP
        if ($folderPath) {
            $assets = $this->parser->inventoryAssets($folderPath);
            foreach ($assets as $asset) {
                $fullPath = $folderPath . '/' . $asset['path'];
                if (File::exists($fullPath)) {
                    $zip->addFile($fullPath, "skills/{$skill->slug}/{$asset['path']}");
                }
            }
        }
    }

    /**
     * Render skill data in TOON (Token-Oriented Object Notation) format.
     *
     * @see https://github.com/toon-format/spec
     */
    protected function renderToon(array $frontmatter, string $body): string
    {
        $lines = [];

        foreach ($frontmatter as $key => $value) {
            if ($value === null) {
                $lines[] = "{$key}: null";
            } elseif (is_bool($value)) {
                $lines[] = "{$key}: " . ($value ? 'true' : 'false');
            } elseif (is_int($value) || is_float($value)) {
                $lines[] = "{$key}: {$value}";
            } elseif (is_array($value)) {
                $count = count($value);
                if ($count === 0) {
                    $lines[] = "{$key}[0]:";
                } else {
                    $encoded = implode(',', array_map(fn ($v) => $this->toonValue($v), $value));
                    $lines[] = "{$key}[{$count}]: {$encoded}";
                }
            } else {
                $lines[] = "{$key}: " . $this->toonValue($value);
            }
        }

        $lines[] = 'body: ' . $this->toonValue($body);

        return implode("\n", $lines) . "\n";
    }

    protected function toonValue(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        $str = (string) $value;

        // Determine if quoting is needed per TOON spec
        $needsQuote = $str === ''
            || in_array($str, ['true', 'false', 'null'], true)
            || is_numeric($str)
            || str_starts_with($str, '-')
            || str_contains($str, ':')
            || str_contains($str, '"')
            || str_contains($str, '\\')
            || str_contains($str, "\n")
            || str_contains($str, "\r")
            || str_contains($str, "\t")
            || str_contains($str, '[')
            || str_contains($str, ']')
            || str_contains($str, '{')
            || str_contains($str, '}')
            || str_contains($str, ',')
            || $str !== trim($str);

        if (! $needsQuote) {
            return $str;
        }

        // Escape: only \\, \", \n, \r, \t are valid TOON escapes
        $escaped = str_replace(
            ['\\', '"', "\n", "\r", "\t"],
            ['\\\\', '\\"', '\\n', '\\r', '\\t'],
            $str,
        );

        return '"' . $escaped . '"';
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

    protected function resolveWorkflows(Project $project, array $workflowIds): \Illuminate\Support\Collection
    {
        if (empty($workflowIds)) {
            return collect();
        }

        return $project->workflows()->with(['steps.agent', 'edges'])->whereIn('id', $workflowIds)->get();
    }

    protected function buildWorkflowData(Workflow $workflow): array
    {
        return [
            'name' => $workflow->name,
            'slug' => $workflow->slug,
            'description' => $workflow->description,
            'trigger_type' => $workflow->trigger_type,
            'status' => $workflow->status,
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
}
