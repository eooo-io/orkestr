<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Facades\File;

class ManifestService
{
    public function __construct(
        protected SkillFileParser $parser,
    ) {}

    /**
     * Scan a project directory and return manifest + parsed skills.
     *
     * Discovers both flat-file skills (slug.md) and folder-based skills (slug/skill.md).
     *
     * @return array{manifest: array|null, skills: array}
     */
    public function scanProject(string $absolutePath): array
    {
        $orkestrDir = rtrim($absolutePath, '/') . '/.orkestr';
        $manifest = null;
        $skills = [];
        $seen = [];

        $manifestPath = $orkestrDir . '/manifest.json';
        if (File::exists($manifestPath)) {
            $manifest = json_decode(File::get($manifestPath), true);
        }

        $skillsDir = $orkestrDir . '/skills';
        if (! File::isDirectory($skillsDir)) {
            return ['manifest' => $manifest, 'skills' => $skills];
        }

        // 1. Discover folder-based skills (slug/skill.md) — takes precedence
        foreach (File::directories($skillsDir) as $dir) {
            $skillMd = $dir . '/skill.md';
            if (! File::exists($skillMd)) {
                continue;
            }

            $slug = basename($dir);
            $parsed = $this->parser->parseFile($skillMd);
            $parsed['filename'] = $slug;
            $parsed['is_folder'] = true;
            $skills[] = $parsed;
            $seen[$slug] = true;
        }

        // 2. Discover flat-file skills (slug.md) — skip if folder version exists
        foreach (File::glob($skillsDir . '/*.md') as $file) {
            $slug = basename($file, '.md');
            if (isset($seen[$slug])) {
                continue;
            }

            $parsed = $this->parser->parseFile($file);
            $parsed['filename'] = $slug;
            $parsed['is_folder'] = false;
            $parsed['assets'] = [];
            $skills[] = $parsed;
        }

        return [
            'manifest' => $manifest,
            'skills' => $skills,
        ];
    }

    /**
     * Write the .orkestr/manifest.json from current DB state.
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

        $dir = rtrim($project->resolved_path, '/') . '/.orkestr';
        File::ensureDirectoryExists($dir);
        File::put($dir . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    }

    /**
     * Scaffold a new .orkestr/ directory structure.
     */
    public function scaffoldProject(string $absolutePath, string $name): void
    {
        $orkestrDir = rtrim($absolutePath, '/') . '/.orkestr';

        File::ensureDirectoryExists($orkestrDir . '/skills');

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

        File::put($orkestrDir . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    }

    /**
     * Write a skill file to the project's .orkestr/skills/ directory.
     *
     * If the skill already has a folder structure (slug/skill.md), writes there.
     * Otherwise writes as a flat file (slug.md).
     * Use writeSkillFolder() to explicitly create/upgrade to folder format.
     */
    public function writeSkillFile(string $projectPath, array $frontmatter, string $body): void
    {
        $slug = $frontmatter['id'] ?? \Illuminate\Support\Str::slug($frontmatter['name'] ?? 'untitled');
        $skillsDir = rtrim($projectPath, '/') . '/.orkestr/skills';

        File::ensureDirectoryExists($skillsDir);

        // If a folder version already exists, write into the folder
        if ($this->parser->isFolderSkill($skillsDir, $slug)) {
            File::put($skillsDir . '/' . $slug . '/skill.md', $this->parser->renderFile($frontmatter, $body));
        } else {
            File::put($skillsDir . '/' . $slug . '.md', $this->parser->renderFile($frontmatter, $body));
        }
    }

    /**
     * Ensure a skill exists as a folder structure (slug/skill.md).
     * Migrates from flat file if needed.
     */
    public function ensureSkillFolder(string $projectPath, string $slug): string
    {
        $skillsDir = rtrim($projectPath, '/') . '/.orkestr/skills';
        $folderPath = $skillsDir . '/' . $slug;
        $flatPath = $skillsDir . '/' . $slug . '.md';

        File::ensureDirectoryExists($folderPath);

        // Migrate flat file to folder if it exists
        if (File::exists($flatPath) && ! File::exists($folderPath . '/skill.md')) {
            File::move($flatPath, $folderPath . '/skill.md');
        }

        // Ensure asset subdirectories exist
        foreach (SkillFileParser::ASSET_DIRS as $dir) {
            File::ensureDirectoryExists($folderPath . '/' . $dir);
        }

        return $folderPath;
    }

    /**
     * Delete a skill file or folder from the project's .orkestr/skills/ directory.
     */
    public function deleteSkillFile(string $projectPath, string $slug): void
    {
        $skillsDir = rtrim($projectPath, '/') . '/.orkestr/skills';

        // Delete folder version
        $folderPath = $skillsDir . '/' . $slug;
        if (File::isDirectory($folderPath)) {
            File::deleteDirectory($folderPath);
        }

        // Delete flat file version
        $flatPath = $skillsDir . '/' . $slug . '.md';
        if (File::exists($flatPath)) {
            File::delete($flatPath);
        }
    }

    /**
     * Check if a skill file exists in the project's .orkestr/skills/ directory.
     * Checks both flat-file and folder-based formats.
     */
    public function skillExists(string $projectPath, string $slug): bool
    {
        $skillsDir = rtrim($projectPath, '/') . '/.orkestr/skills';

        return File::exists($skillsDir . '/' . $slug . '.md')
            || $this->parser->isFolderSkill($skillsDir, $slug);
    }

    /**
     * Get the skill folder path for a given slug.
     * Returns null if the skill is not in folder format.
     */
    public function getSkillFolderPath(string $projectPath, string $slug): ?string
    {
        $skillsDir = rtrim($projectPath, '/') . '/.orkestr/skills';

        if ($this->parser->isFolderSkill($skillsDir, $slug)) {
            return $skillsDir . '/' . $slug;
        }

        return null;
    }
}
