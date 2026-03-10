<?php

namespace App\Services\Providers;

use App\Models\Project;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

class ClineDriver implements ProviderDriverInterface
{
    public function sync(Project $project, Collection $skills, array $composedAgents = []): void
    {
        $output = '';

        foreach ($skills as $skill) {
            $output .= "# {$skill->name}\n\n{$skill->body}\n\n---\n\n";
        }

        if (! empty($composedAgents)) {
            $output .= "# Agents\n\n";
            foreach ($composedAgents as $composed) {
                $output .= $composed['content'] . "\n---\n\n";
            }
        }

        $path = rtrim($project->resolved_path, '/') . '/.clinerules';
        File::put($path, rtrim($output) . "\n");
    }

    public function getOutputPaths(Project $project): array
    {
        return [rtrim($project->resolved_path, '/') . '/.clinerules'];
    }

    public function clean(Project $project): void
    {
        $path = rtrim($project->resolved_path, '/') . '/.clinerules';

        if (File::exists($path)) {
            File::delete($path);
        }
    }
}
