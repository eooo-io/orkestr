<?php

namespace App\Services\Providers;

use App\Models\Project;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

class CursorDriver implements ProviderDriverInterface
{
    public function sync(Project $project, Collection $skills, array $composedAgents = []): void
    {
        $dir = rtrim($project->resolved_path, '/') . '/.cursor/rules';
        File::ensureDirectoryExists($dir);

        // Remove existing .mdc files to handle deleted/renamed skills
        foreach (File::glob($dir . '/*.mdc') as $existing) {
            File::delete($existing);
        }

        foreach ($skills as $skill) {
            $frontmatter = [
                'description' => $skill->description ?? '',
                'alwaysApply' => false,
            ];

            if ($skill->tags->isNotEmpty()) {
                $frontmatter['tags'] = $skill->tags->pluck('name')->values()->all();
            }

            $yaml = Yaml::dump($frontmatter, 2, 2);
            $content = "---\n{$yaml}---\n\n{$skill->body}\n";

            File::put($dir . '/' . $skill->slug . '.mdc', $content);
        }

        foreach ($composedAgents as $composed) {
            $slug = $composed['agent']['slug'];
            $frontmatter = [
                'description' => "Agent: {$composed['agent']['name']} ({$composed['agent']['role']})",
                'alwaysApply' => false,
            ];

            $yaml = Yaml::dump($frontmatter, 2, 2);
            $content = "---\n{$yaml}---\n\n{$composed['content']}\n";

            File::put($dir . '/agent-' . $slug . '.mdc', $content);
        }
    }

    public function getOutputPaths(Project $project): array
    {
        $dir = rtrim($project->resolved_path, '/') . '/.cursor/rules';

        if (! File::isDirectory($dir)) {
            return [];
        }

        return File::glob($dir . '/*.mdc');
    }

    public function clean(Project $project): void
    {
        $dir = rtrim($project->resolved_path, '/') . '/.cursor/rules';

        if (File::isDirectory($dir)) {
            File::deleteDirectory($dir);
        }
    }
}
