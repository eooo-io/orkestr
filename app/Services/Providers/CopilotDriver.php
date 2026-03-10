<?php

namespace App\Services\Providers;

use App\Models\Project;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

class CopilotDriver implements ProviderDriverInterface
{
    public function sync(Project $project, Collection $skills, array $composedAgents = []): void
    {
        $output = "# GitHub Copilot Instructions\n\n";

        foreach ($skills as $skill) {
            $output .= "## {$skill->name}\n\n{$skill->body}\n\n---\n\n";
        }

        if (! empty($composedAgents)) {
            $output .= "# Agents\n\n";
            foreach ($composedAgents as $composed) {
                $output .= $composed['content'] . "\n---\n\n";
            }
        }

        $dir = rtrim($project->resolved_path, '/') . '/.github';
        File::ensureDirectoryExists($dir);
        File::put($dir . '/copilot-instructions.md', rtrim($output) . "\n");
    }

    public function getOutputPaths(Project $project): array
    {
        return [rtrim($project->resolved_path, '/') . '/.github/copilot-instructions.md'];
    }

    public function clean(Project $project): void
    {
        $path = rtrim($project->resolved_path, '/') . '/.github/copilot-instructions.md';

        if (File::exists($path)) {
            File::delete($path);
        }
    }
}
