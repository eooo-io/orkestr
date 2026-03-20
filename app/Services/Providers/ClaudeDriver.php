<?php

namespace App\Services\Providers;

use App\Models\Project;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

class ClaudeDriver implements ProviderDriverInterface
{
    use GeneratesMcpConfig;
    use ResolvesSkillAssets;

    public function generate(Project $project, Collection $skills, array $composedAgents = [], array $resolvedBodies = []): array
    {
        $output = "# CLAUDE.md\n\n";

        foreach ($skills as $skill) {
            $body = $resolvedBodies[$skill->id] ?? $skill->body;
            $output .= "## {$skill->name}\n\n";

            if (! empty($skill->conditions['file_patterns'])) {
                $patterns = implode(', ', $skill->conditions['file_patterns']);
                $output .= "> **Applies to:** `{$patterns}`\n\n";
            }

            $assetContext = $this->buildAssetContext($skill);
            if ($assetContext) {
                $output .= "{$body}\n\n{$assetContext}\n---\n\n";
            } else {
                $output .= "{$body}\n\n---\n\n";
            }
        }

        if (! empty($composedAgents)) {
            $output .= "# Agents\n\n";
            foreach ($composedAgents as $composed) {
                $output .= $composed['content'] . "\n---\n\n";
            }
        }

        $base = rtrim($project->resolved_path, '/');
        $files = [$base . '/.claude/CLAUDE.md' => rtrim($output) . "\n"];

        // MCP servers → .mcp.json (project-scoped, team-shareable)
        $mcpFiles = $this->generateMcpFiles($project, $base . '/.mcp.json');
        $files = array_merge($files, $mcpFiles);

        return $files;
    }

    public function sync(Project $project, Collection $skills, array $composedAgents = [], array $resolvedBodies = []): void
    {
        $files = $this->generate($project, $skills, $composedAgents, $resolvedBodies);

        foreach ($files as $path => $content) {
            File::ensureDirectoryExists(dirname($path));
            File::put($path, $content);
        }
    }

    public function getOutputPaths(Project $project): array
    {
        $base = rtrim($project->resolved_path, '/');
        $paths = [$base . '/.claude/CLAUDE.md'];

        $mcpPath = $base . '/.mcp.json';
        if (File::exists($mcpPath)) {
            $paths[] = $mcpPath;
        }

        return $paths;
    }

    public function clean(Project $project): void
    {
        $base = rtrim($project->resolved_path, '/');

        foreach ([$base . '/.claude/CLAUDE.md', $base . '/.mcp.json'] as $path) {
            if (File::exists($path)) {
                File::delete($path);
            }
        }
    }
}
