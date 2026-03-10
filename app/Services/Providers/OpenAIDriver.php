<?php

namespace App\Services\Providers;

use App\Models\Project;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

class OpenAIDriver implements ProviderDriverInterface
{
    public function sync(Project $project, Collection $skills, array $composedAgents = []): void
    {
        $output = "# Instructions\n\n";

        foreach ($skills as $skill) {
            $output .= "## {$skill->name}\n\n{$skill->body}\n\n---\n\n";
        }

        if (! empty($composedAgents)) {
            $output .= "# Agents\n\n";
            foreach ($composedAgents as $composed) {
                $output .= $composed['content'] . "\n---\n\n";
            }
        }

        $dir = rtrim($project->resolved_path, '/') . '/.openai';
        File::ensureDirectoryExists($dir);
        File::put($dir . '/instructions.md', rtrim($output) . "\n");
    }

    public function getOutputPaths(Project $project): array
    {
        return [rtrim($project->resolved_path, '/') . '/.openai/instructions.md'];
    }

    public function clean(Project $project): void
    {
        $path = rtrim($project->resolved_path, '/') . '/.openai/instructions.md';

        if (File::exists($path)) {
            File::delete($path);
        }
    }
}
