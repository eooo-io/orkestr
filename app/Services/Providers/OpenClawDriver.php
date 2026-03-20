<?php

namespace App\Services\Providers;

use App\Models\Project;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

class OpenClawDriver implements ProviderDriverInterface
{
    use ResolvesSkillAssets;
    public function generate(Project $project, Collection $skills, array $composedAgents = [], array $resolvedBodies = []): array
    {
        $base = rtrim($project->resolved_path, '/') . '/.openclaw';
        $files = [];

        // ─── SOUL.md ─────────────────────────────────────────────
        $config = $project->openclawConfig;
        $soulContent = $config?->soul_content;

        if ($soulContent) {
            $files[$base . '/SOUL.md'] = $soulContent . "\n";
        }

        // ─── Skills → SKILL.md per skill ─────────────────────────
        foreach ($skills as $skill) {
            $body = $resolvedBodies[$skill->id] ?? $skill->body;

            $frontmatter = [
                'name' => $skill->slug,
                'description' => $skill->description ?? $skill->name,
            ];

            // Map Agentis tags to OpenClaw metadata
            if ($skill->tags->isNotEmpty()) {
                $frontmatter['metadata'] = json_encode([
                    'openclaw' => [
                        'tags' => $skill->tags->pluck('name')->values()->all(),
                    ],
                ], JSON_UNESCAPED_SLASHES);
            }

            $assetContext = $this->buildAssetContext($skill);
            $fullBody = $assetContext ? "{$body}\n\n{$assetContext}" : $body;

            $yaml = Yaml::dump($frontmatter, 2, 2);
            $content = "---\n{$yaml}---\n\n{$fullBody}\n";

            $files[$base . '/skills/' . $skill->slug . '/SKILL.md'] = $content;
        }

        // ─── Agent skills (composed agents → SKILL.md) ───────────
        foreach ($composedAgents as $composed) {
            $slug = 'agent-' . $composed['agent']['slug'];
            $frontmatter = [
                'name' => $slug,
                'description' => "Agent: {$composed['agent']['name']} ({$composed['agent']['role']})",
            ];

            $yaml = Yaml::dump($frontmatter, 2, 2);
            $content = "---\n{$yaml}---\n\n{$composed['content']}\n";

            $files[$base . '/skills/' . $slug . '/SKILL.md'] = $content;
        }

        // ─── TOOLS.md ────────────────────────────────────────────
        $toolsContent = $this->generateToolsMd($config);
        if ($toolsContent) {
            $files[$base . '/TOOLS.md'] = $toolsContent;
        }

        // ─── AGENTS.md ───────────────────────────────────────────
        $a2aAgentsForMd = $project->a2aAgents()->where('enabled', true)->get();
        $agentsMd = $this->generateAgentsMd($a2aAgentsForMd, $composedAgents);
        if ($agentsMd) {
            $files[$base . '/AGENTS.md'] = $agentsMd;
        }

        // ─── openclaw.json (MCP servers + A2A + tool settings) ───
        $mcpServers = $project->mcpServers()->where('enabled', true)->get();
        $a2aAgents = $project->a2aAgents()->where('enabled', true)->get();
        $openclawJson = $this->generateOpenClawJson($config, $mcpServers, $a2aAgents);
        if ($openclawJson) {
            $files[$base . '/openclaw.json'] = $openclawJson;
        }

        return $files;
    }

    public function sync(Project $project, Collection $skills, array $composedAgents = [], array $resolvedBodies = []): void
    {
        $base = rtrim($project->resolved_path, '/') . '/.openclaw';

        // Clean existing skills directory to handle renames/deletes
        $skillsDir = $base . '/skills';
        if (File::isDirectory($skillsDir)) {
            File::deleteDirectory($skillsDir);
        }

        $files = $this->generate($project, $skills, $composedAgents, $resolvedBodies);

        foreach ($files as $path => $content) {
            File::ensureDirectoryExists(dirname($path));
            File::put($path, $content);
        }
    }

    public function getOutputPaths(Project $project): array
    {
        $base = rtrim($project->resolved_path, '/') . '/.openclaw';
        $paths = [];

        foreach (['SOUL.md', 'TOOLS.md', 'AGENTS.md', 'openclaw.json'] as $file) {
            $path = $base . '/' . $file;
            if (File::exists($path)) {
                $paths[] = $path;
            }
        }

        $skillsDir = $base . '/skills';
        if (File::isDirectory($skillsDir)) {
            foreach (File::glob($skillsDir . '/*/SKILL.md') as $skillFile) {
                $paths[] = $skillFile;
            }
        }

        return $paths;
    }

    public function clean(Project $project): void
    {
        $base = rtrim($project->resolved_path, '/') . '/.openclaw';

        if (File::isDirectory($base)) {
            File::deleteDirectory($base);
        }
    }

    // ─── Private Generators ───────────────────────────────────────

    private function generateToolsMd($config): ?string
    {
        if (! $config) {
            return null;
        }

        $tools = $config->tools ?? [];
        if (empty($tools)) {
            return null;
        }

        $output = "# Tools\n\n";

        foreach ($tools as $tool) {
            $name = $tool['name'] ?? 'unnamed';
            $description = $tool['description'] ?? '';
            $enabled = ($tool['enabled'] ?? true) ? 'enabled' : 'disabled';

            $output .= "## {$name}\n\n";
            $output .= "**Status:** {$enabled}\n\n";
            if ($description) {
                $output .= "{$description}\n\n";
            }
            if (! empty($tool['instructions'])) {
                $output .= "{$tool['instructions']}\n\n";
            }
            $output .= "---\n\n";
        }

        return rtrim($output) . "\n";
    }

    private function generateAgentsMd(Collection $a2aAgents, array $composedAgents): ?string
    {
        if ($a2aAgents->isEmpty() && empty($composedAgents)) {
            return null;
        }

        $output = "# Agents\n\n";

        // A2A agents from shared project config
        if ($a2aAgents->isNotEmpty()) {
            $output .= "## Agent-to-Agent (A2A) Connections\n\n";
            foreach ($a2aAgents as $agent) {
                $output .= "### {$agent->name}\n\n";
                if ($agent->url) {
                    $output .= "**Endpoint:** `{$agent->url}`\n\n";
                }
                if ($agent->description) {
                    $output .= "{$agent->description}\n\n";
                }
                $output .= "---\n\n";
            }
        }

        // Composed agents from Agentis
        if (! empty($composedAgents)) {
            $output .= "## Composed Agents\n\n";
            foreach ($composedAgents as $composed) {
                $output .= "### {$composed['agent']['name']}\n\n";
                $output .= "**Role:** {$composed['agent']['role']}\n\n";
                $output .= $composed['content'] . "\n\n---\n\n";
            }
        }

        return rtrim($output) . "\n";
    }

    private function generateOpenClawJson($config, Collection $mcpServers, Collection $a2aAgents): ?string
    {
        $json = [];

        // MCP servers (from shared project-level table)
        if ($mcpServers->isNotEmpty()) {
            $json['mcp'] = ['servers' => []];
            foreach ($mcpServers as $server) {
                $entry = [];

                if ($server->command) {
                    $entry['command'] = $server->command;
                }
                if (! empty($server->args)) {
                    $entry['args'] = $server->args;
                }
                if ($server->url) {
                    $entry['url'] = $server->url;
                }
                if (! empty($server->env)) {
                    $entry['env'] = $server->env;
                }

                $json['mcp']['servers'][$server->name] = $entry;
            }
        }

        // A2A agents (from shared project-level table)
        if ($a2aAgents->isNotEmpty()) {
            $json['a2a'] = ['agents' => []];
            foreach ($a2aAgents as $agent) {
                $json['a2a']['agents'][] = [
                    'name' => $agent->name,
                    'url' => $agent->url,
                    'description' => $agent->description ?? '',
                    'skills' => $agent->skills ?? [],
                ];
            }
        }

        // OpenClaw-specific tool/skill settings
        $tools = $config?->tools ?? [];
        if (! empty($tools)) {
            $json['skills'] = ['entries' => []];
            foreach ($tools as $tool) {
                $name = $tool['name'] ?? 'unnamed';
                $entry = ['enabled' => $tool['enabled'] ?? true];

                if (! empty($tool['api_key_env'])) {
                    $entry['apiKey'] = [
                        'source' => 'env',
                        'provider' => 'default',
                        'id' => $tool['api_key_env'],
                    ];
                }
                if (! empty($tool['env'])) {
                    $entry['env'] = $tool['env'];
                }
                if (! empty($tool['config'])) {
                    $entry['config'] = $tool['config'];
                }

                $json['skills']['entries'][$name] = $entry;
            }
        }

        if (empty($json)) {
            return null;
        }

        return json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    }
}
