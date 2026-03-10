<?php

namespace App\Services\Providers;

use App\Models\Project;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

class CursorDriver implements ProviderDriverInterface
{
    public function generate(Project $project, Collection $skills, array $composedAgents = [], array $resolvedBodies = []): array
    {
        $dir = rtrim($project->resolved_path, '/') . '/.cursor/rules';
        $files = [];

        foreach ($skills as $skill) {
            $body = $resolvedBodies[$skill->id] ?? $skill->body;
            $frontmatter = [
                'description' => $skill->description ?? '',
                'alwaysApply' => false,
            ];

            if ($skill->tags->isNotEmpty()) {
                $frontmatter['tags'] = $skill->tags->pluck('name')->values()->all();
            }

            $yaml = Yaml::dump($frontmatter, 2, 2);
            $content = "---\n{$yaml}---\n\n{$body}\n";

            $files[$dir . '/' . $skill->slug . '.mdc'] = $content;
        }

        foreach ($composedAgents as $composed) {
            $slug = $composed['agent']['slug'];
            $frontmatter = [
                'description' => "Agent: {$composed['agent']['name']} ({$composed['agent']['role']})",
                'alwaysApply' => false,
            ];

            $yaml = Yaml::dump($frontmatter, 2, 2);
            $content = "---\n{$yaml}---\n\n{$composed['content']}\n";

            $files[$dir . '/agent-' . $slug . '.mdc'] = $content;
        }

        return $files;
    }

    public function sync(Project $project, Collection $skills, array $composedAgents = [], array $resolvedBodies = []): void
    {
        $dir = rtrim($project->resolved_path, '/') . '/.cursor/rules';
        File::ensureDirectoryExists($dir);

        // Remove existing .mdc files to handle deleted/renamed skills
        foreach (File::glob($dir . '/*.mdc') as $existing) {
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
