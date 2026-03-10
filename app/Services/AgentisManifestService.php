<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Facades\File;

class AgentisManifestService
{
    public function __construct(
        protected SkillFileParser $parser,
    ) {}

    /**
     * Scan a project directory and return manifest + parsed skills.
     *
     * @return array{manifest: array|null, skills: array}
     */
    public function scanProject(string $absolutePath): array
    {
        $agentisDir = rtrim($absolutePath, '/') . '/.agentis';
        $manifest = null;
        $skills = [];

        $manifestPath = $agentisDir . '/manifest.json';
        if (File::exists($manifestPath)) {
            $manifest = json_decode(File::get($manifestPath), true);
        }

        $skillsDir = $agentisDir . '/skills';
        if (File::isDirectory($skillsDir)) {
            $files = File::glob($skillsDir . '/*.md');

            foreach ($files as $file) {
                $parsed = $this->parser->parseFile($file);
                $parsed['filename'] = basename($file, '.md');
                $skills[] = $parsed;
            }
        }

        return [
            'manifest' => $manifest,
            'skills' => $skills,
        ];
    }

    /**
     * Write the .agentis/manifest.json from current DB state.
     */
    public function writeManifest(Project $project): void
    {
        $project->loadMissing(['providers', 'skills']);

        $manifest = [
            'id' => $project->uuid,
            'name' => $project->name,
            'path' => $project->path,
            'description' => $project->description,
            'providers' => $project->providers->pluck('provider_slug')->values()->all(),
            'skills' => $project->skills->pluck('slug')->values()->all(),
            'created_at' => $project->created_at?->toIso8601String(),
            'synced_at' => $project->synced_at?->toIso8601String(),
        ];

        $dir = rtrim($project->resolved_path, '/') . '/.agentis';
        File::ensureDirectoryExists($dir);
        File::put($dir . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    }

    /**
     * Scaffold a new .agentis/ directory structure.
     */
    public function scaffoldProject(string $absolutePath, string $name): void
    {
        $agentisDir = rtrim($absolutePath, '/') . '/.agentis';

        File::ensureDirectoryExists($agentisDir . '/skills');

        $manifest = [
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => $name,
            'path' => $absolutePath,
            'description' => '',
            'providers' => [],
            'skills' => [],
            'created_at' => now()->toIso8601String(),
            'synced_at' => null,
        ];

        File::put($agentisDir . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    }

    /**
     * Write a skill file to the project's .agentis/skills/ directory.
     */
    public function writeSkillFile(string $projectPath, array $frontmatter, string $body): void
    {
        $slug = $frontmatter['id'] ?? \Illuminate\Support\Str::slug($frontmatter['name'] ?? 'untitled');
        $dir = rtrim($projectPath, '/') . '/.agentis/skills';

        File::ensureDirectoryExists($dir);
        File::put($dir . '/' . $slug . '.md', $this->parser->renderFile($frontmatter, $body));
    }

    /**
     * Delete a skill file from the project's .agentis/skills/ directory.
     */
    public function deleteSkillFile(string $projectPath, string $slug): void
    {
        $file = rtrim($projectPath, '/') . '/.agentis/skills/' . $slug . '.md';

        if (File::exists($file)) {
            File::delete($file);
        }
    }

    /**
     * Check if a skill file exists in the project's .agentis/skills/ directory.
     */
    public function skillExists(string $projectPath, string $slug): bool
    {
        return File::exists(rtrim($projectPath, '/') . '/.agentis/skills/' . $slug . '.md');
    }
}
