<?php

namespace App\Services\Providers;

use App\Models\Project;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

class OpenAIDriver implements ProviderDriverInterface
{
    use ResolvesSkillAssets;

    public function generate(Project $project, Collection $skills, array $composedAgents = [], array $resolvedBodies = []): array
    {
        $output = "# Instructions\n\n";

        foreach ($skills as $skill) {
            $body = $resolvedBodies[$skill->id] ?? $skill->body;
            $assetContext = $this->buildAssetContext($skill);
            $fullBody = $assetContext ? "{$body}\n\n{$assetContext}" : $body;
            $output .= "## {$skill->name}\n\n{$fullBody}\n\n---\n\n";
        }

        if (! empty($composedAgents)) {
            $output .= "# Agents\n\n";
            foreach ($composedAgents as $composed) {
                $output .= $composed['content'] . "\n---\n\n";
            }
        }

        $path = rtrim($project->resolved_path, '/') . '/.openai/instructions.md';

        return [$path => rtrim($output) . "\n"];
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
