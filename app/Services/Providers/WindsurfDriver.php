<?php

namespace App\Services\Providers;

use App\Models\Project;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

class WindsurfDriver implements ProviderDriverInterface
{
    use GeneratesMcpConfig;

    public function generate(Project $project, Collection $skills, array $composedAgents = [], array $resolvedBodies = []): array
    {
        $base = rtrim($project->resolved_path, '/');
        $dir = $base . '/.windsurf/rules';
        $files = [];

        foreach ($skills as $skill) {
            $body = $resolvedBodies[$skill->id] ?? $skill->body;
            $content = "# {$skill->name}\n\n{$body}\n";
            $files[$dir . '/' . $skill->slug . '.md'] = $content;
        }

        foreach ($composedAgents as $composed) {
            $slug = $composed['agent']['slug'];
            $files[$dir . '/agent-' . $slug . '.md'] = $composed['content'];
        }

        // MCP servers → .windsurf/mcp_config.json
        $mcpFiles = $this->generateMcpFiles($project, $base . '/.windsurf/mcp_config.json');
        $files = array_merge($files, $mcpFiles);

        return $files;
    }

    public function sync(Project $project, Collection $skills, array $composedAgents = [], array $resolvedBodies = []): void
    {
        $dir = rtrim($project->resolved_path, '/') . '/.windsurf/rules';
        File::ensureDirectoryExists($dir);

        // Remove existing files to handle deleted/renamed skills
        foreach (File::glob($dir . '/*.md') as $existing) {
            File::delete($existing);
        }

        $files = $this->generate($project, $skills, $composedAgents, $resolvedBodies);

        foreach ($files as $path => $content) {
            File::ensureDirectoryExists(dirname($path));
            File::put($path, $content);
        }
    }

    public function getOutputPaths(Project $project): array
    {
        $base = rtrim($project->resolved_path, '/');
        $dir = $base . '/.windsurf/rules';
        $paths = [];

        if (File::isDirectory($dir)) {
            $paths = File::glob($dir . '/*.md');
        }

        $mcpPath = $base . '/.windsurf/mcp_config.json';
        if (File::exists($mcpPath)) {
            $paths[] = $mcpPath;
        }

        return $paths;
    }

    public function clean(Project $project): void
    {
        $base = rtrim($project->resolved_path, '/');

        $dir = $base . '/.windsurf/rules';
        if (File::isDirectory($dir)) {
            File::deleteDirectory($dir);
        }

        $mcpPath = $base . '/.windsurf/mcp_config.json';
        if (File::exists($mcpPath)) {
            File::delete($mcpPath);
        }
    }
}
