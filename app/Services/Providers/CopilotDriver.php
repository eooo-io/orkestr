<?php

namespace App\Services\Providers;

use App\Models\Project;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

class CopilotDriver implements ProviderDriverInterface
{
    use GeneratesMcpConfig;

    public function generate(Project $project, Collection $skills, array $composedAgents = [], array $resolvedBodies = []): array
    {
        $output = "# GitHub Copilot Instructions\n\n";

        foreach ($skills as $skill) {
            $body = $resolvedBodies[$skill->id] ?? $skill->body;
            $output .= "## {$skill->name}\n\n{$body}\n\n---\n\n";
        }

        if (! empty($composedAgents)) {
            $output .= "# Agents\n\n";
            foreach ($composedAgents as $composed) {
                $output .= $composed['content'] . "\n---\n\n";
            }
        }

        $base = rtrim($project->resolved_path, '/');
        $files = [$base . '/.github/copilot-instructions.md' => rtrim($output) . "\n"];

        // MCP servers → .vscode/mcp.json (VS Code workspace-scoped)
        $mcpFiles = $this->generateMcpFiles($project, $base . '/.vscode/mcp.json');
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
        $paths = [$base . '/.github/copilot-instructions.md'];

        $mcpPath = $base . '/.vscode/mcp.json';
        if (File::exists($mcpPath)) {
            $paths[] = $mcpPath;
        }

        return $paths;
    }

    public function clean(Project $project): void
    {
        $base = rtrim($project->resolved_path, '/');

        foreach ([$base . '/.github/copilot-instructions.md', $base . '/.vscode/mcp.json'] as $path) {
            if (File::exists($path)) {
                File::delete($path);
            }
        }
    }
}
