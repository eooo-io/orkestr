<?php

namespace App\Services\Providers;

use App\Models\Project;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

class ClineDriver implements ProviderDriverInterface
{
    use GeneratesMcpConfig;

    public function generate(Project $project, Collection $skills, array $composedAgents = [], array $resolvedBodies = []): array
    {
        $output = '';

        foreach ($skills as $skill) {
            $body = $resolvedBodies[$skill->id] ?? $skill->body;
            $output .= "# {$skill->name}\n\n";

            if (! empty($skill->conditions['file_patterns'])) {
                $patterns = implode(', ', $skill->conditions['file_patterns']);
                $output .= "> **Applies to:** `{$patterns}`\n\n";
            }

            $output .= "{$body}\n\n---\n\n";
        }

        if (! empty($composedAgents)) {
            $output .= "# Agents\n\n";
            foreach ($composedAgents as $composed) {
                $output .= $composed['content'] . "\n---\n\n";
            }
        }

        $base = rtrim($project->resolved_path, '/');
        $files = [$base . '/.clinerules' => rtrim($output) . "\n"];

        // MCP servers → .cline/mcp_settings.json
        $mcpFiles = $this->generateMcpFiles($project, $base . '/.cline/mcp_settings.json');
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
        $paths = [$base . '/.clinerules'];

        $mcpPath = $base . '/.cline/mcp_settings.json';
        if (File::exists($mcpPath)) {
            $paths[] = $mcpPath;
        }

        return $paths;
    }

    public function clean(Project $project): void
    {
        $base = rtrim($project->resolved_path, '/');

        $path = $base . '/.clinerules';
        if (File::exists($path)) {
            File::delete($path);
        }

        $mcpPath = $base . '/.cline/mcp_settings.json';
        if (File::exists($mcpPath)) {
            File::delete($mcpPath);
        }
    }
}
