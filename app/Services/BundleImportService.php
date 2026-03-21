<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Tag;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;
use ZipArchive;

class BundleImportService
{
    public function __construct(
        protected SkillFileParser $parser,
        protected ManifestService $manifestService,
    ) {}

    /**
     * Preview the contents of a ZIP bundle without importing.
     */
    public function previewZip(string $path): array
    {
        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            return ['error' => 'Failed to open ZIP file', 'skills' => [], 'agents' => [], 'metadata' => []];
        }

        $skills = [];
        $agents = [];
        $metadata = [];

        // Read metadata
        $bundleYaml = $zip->getFromName('bundle.yaml');
        if ($bundleYaml !== false) {
            $metadata = Yaml::parse($bundleYaml) ?? [];
        }

        // Read skills
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (str_starts_with($name, 'skills/') && str_ends_with($name, '.md')) {
                $content = $zip->getFromIndex($i);
                $parsed = $this->parser->parseContent($content);
                $skills[] = [
                    'slug' => basename($name, '.md'),
                    'name' => $parsed['frontmatter']['name'] ?? basename($name, '.md'),
                    'description' => $parsed['frontmatter']['description'] ?? null,
                    'tags' => $parsed['frontmatter']['tags'] ?? [],
                ];
            }
        }

        // Read agents
        $agentsYaml = $zip->getFromName('agents.yaml');
        if ($agentsYaml !== false) {
            $agentData = Yaml::parse($agentsYaml) ?? [];
            foreach ($agentData as $agent) {
                $agents[] = [
                    'name' => $agent['name'] ?? 'Unknown',
                    'slug' => $agent['slug'] ?? Str::slug($agent['name'] ?? 'unknown'),
                    'role' => $agent['role'] ?? '',
                    'description' => $agent['description'] ?? null,
                ];
            }
        }

        $zip->close();

        return [
            'metadata' => $metadata,
            'skills' => $skills,
            'agents' => $agents,
        ];
    }

    /**
     * Preview the contents of a JSON bundle without importing.
     */
    public function previewJson(array $data): array
    {
        $skills = [];
        $agents = [];

        foreach ($data['skills'] ?? [] as $skill) {
            $skills[] = [
                'slug' => $skill['slug'] ?? Str::slug($skill['name'] ?? 'unknown'),
                'name' => $skill['name'] ?? 'Unknown',
                'description' => $skill['description'] ?? null,
                'tags' => $skill['tags'] ?? [],
            ];
        }

        foreach ($data['agents'] ?? [] as $agent) {
            $agents[] = [
                'name' => $agent['name'] ?? 'Unknown',
                'slug' => $agent['slug'] ?? Str::slug($agent['name'] ?? 'unknown'),
                'role' => $agent['role'] ?? '',
                'description' => $agent['description'] ?? null,
            ];
        }

        return [
            'metadata' => $data['metadata'] ?? [],
            'skills' => $skills,
            'agents' => $agents,
        ];
    }

    /**
     * Import skills and agents into a project.
     *
     * @param  string  $conflictMode  'skip', 'overwrite', or 'rename'
     * @return array{imported: int, skipped: int, errors: string[]}
     */
    public function import(Project $project, array $skills, array $agents, string $conflictMode = 'skip', ?string $zipPath = null): array
    {
        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ($skills as $skillData) {
            try {
                $result = $this->importSkill($project, $skillData, $conflictMode);
                if ($result) {
                    $imported++;

                    // Extract assets from ZIP for folder skills
                    if ($zipPath && ! empty($skillData['_asset_entries'])) {
                        $this->extractSkillAssets($project, $skillData, $zipPath);
                    }
                } else {
                    $skipped++;
                }
            } catch (\Throwable $e) {
                $errors[] = "Skill '{$skillData['name']}': {$e->getMessage()}";
            }
        }

        // Import agents (always skip if exists)
        foreach ($agents as $agentData) {
            try {
                $this->importAgent($project, $agentData);
            } catch (\Throwable $e) {
                $errors[] = "Agent '{$agentData['name']}': {$e->getMessage()}";
            }
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * Extract skill asset files from a ZIP into the skill folder.
     */
    protected function extractSkillAssets(Project $project, array $skillData, string $zipPath): void
    {
        $slug = $skillData['slug'];
        $folderPath = $this->manifestService->ensureSkillFolder($project->resolved_path, $slug);

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return;
        }

        foreach ($skillData['_asset_entries'] ?? [] as $entryName) {
            // entryName: skills/slug/assets/foo.md → extract to folderPath/assets/foo.md
            $zipSlug = $skillData['_zip_slug'] ?? $slug;
            $relativePath = str_replace("skills/{$zipSlug}/", '', $entryName);
            $targetPath = $folderPath . '/' . $relativePath;

            File::ensureDirectoryExists(dirname($targetPath));

            $content = $zip->getFromName($entryName);
            if ($content !== false) {
                File::put($targetPath, $content);
            }
        }

        $zip->close();
    }

    /**
     * Extract full skill/agent data from a ZIP for import.
     *
     * Supports both flat skills (skills/slug.md) and folder skills (skills/slug/skill.md).
     */
    public function extractZip(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return ['skills' => [], 'agents' => []];
        }

        $skills = [];
        $agents = [];
        $seenSlugs = [];
        $assetEntries = []; // slug => [zip entry names]

        // First pass: find all skill.md (folder) and slug.md (flat) entries
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);

            // Folder skill: skills/slug/skill.md
            if (preg_match('#^skills/([^/]+)/skill\.md$#', $name, $m)) {
                $slug = $m[1];
                $content = $zip->getFromIndex($i);
                $parsed = $this->parser->parseContent($content);
                $fm = $parsed['frontmatter'];
                $skills[] = [
                    'slug' => $fm['id'] ?? $slug,
                    'name' => $fm['name'] ?? $slug,
                    'description' => $fm['description'] ?? null,
                    'model' => $fm['model'] ?? null,
                    'max_tokens' => $fm['max_tokens'] ?? null,
                    'tools' => $fm['tools'] ?? [],
                    'includes' => $fm['includes'] ?? [],
                    'tags' => $fm['tags'] ?? [],
                    'body' => $parsed['body'],
                    '_has_assets' => true,
                    '_zip_slug' => $slug,
                ];
                $seenSlugs[$slug] = true;
                continue;
            }

            // Collect asset entries for folder skills
            if (preg_match('#^skills/([^/]+)/(assets|scripts|data)/.+$#', $name, $m)) {
                $assetEntries[$m[1]][] = $name;
                continue;
            }

            // Flat skill: skills/slug.md
            if (str_starts_with($name, 'skills/') && str_ends_with($name, '.md')) {
                $slug = basename($name, '.md');
                if (isset($seenSlugs[$slug])) {
                    continue; // folder version takes precedence
                }
                $content = $zip->getFromIndex($i);
                $parsed = $this->parser->parseContent($content);
                $fm = $parsed['frontmatter'];
                $skills[] = [
                    'slug' => $fm['id'] ?? $slug,
                    'name' => $fm['name'] ?? $slug,
                    'description' => $fm['description'] ?? null,
                    'model' => $fm['model'] ?? null,
                    'max_tokens' => $fm['max_tokens'] ?? null,
                    'tools' => $fm['tools'] ?? [],
                    'includes' => $fm['includes'] ?? [],
                    'tags' => $fm['tags'] ?? [],
                    'body' => $parsed['body'],
                ];
            }
        }

        // Store asset entry mapping for import use
        foreach ($skills as &$skill) {
            if (! empty($skill['_has_assets']) && isset($assetEntries[$skill['_zip_slug']])) {
                $skill['_asset_entries'] = $assetEntries[$skill['_zip_slug']];
            }
        }
        unset($skill);

        $agentsYaml = $zip->getFromName('agents.yaml');
        if ($agentsYaml !== false) {
            $agents = Yaml::parse($agentsYaml) ?? [];
        }

        $zip->close();

        return ['skills' => $skills, 'agents' => $agents, '_zip_path' => $path];
    }

    protected function importSkill(Project $project, array $data, string $conflictMode): bool
    {
        $slug = $data['slug'] ?? Str::slug($data['name']);
        $existing = $project->skills()->where('slug', $slug)->first();

        if ($existing) {
            switch ($conflictMode) {
                case 'skip':
                    return false;

                case 'overwrite':
                    $existing->update([
                        'name' => $data['name'],
                        'description' => $data['description'] ?? null,
                        'model' => $data['model'] ?? null,
                        'max_tokens' => $data['max_tokens'] ?? null,
                        'tools' => $data['tools'] ?? [],
                        'includes' => $data['includes'] ?? [],
                        'body' => $data['body'] ?? '',
                    ]);
                    $this->syncSkillTags($existing, $data['tags'] ?? []);
                    $this->createVersion($existing);
                    $this->writeSkillFile($project, $existing);

                    return true;

                case 'rename':
                    $slug = $slug . '-imported';
                    $counter = 1;
                    while ($project->skills()->where('slug', $slug)->exists()) {
                        $slug = Str::slug($data['name']) . "-imported-{$counter}";
                        $counter++;
                    }
                    break;
            }
        }

        $skill = $project->skills()->create([
            'slug' => $slug,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'model' => $data['model'] ?? null,
            'max_tokens' => $data['max_tokens'] ?? null,
            'tools' => $data['tools'] ?? [],
            'includes' => $data['includes'] ?? [],
            'body' => $data['body'] ?? '',
        ]);

        $this->syncSkillTags($skill, $data['tags'] ?? []);
        $this->createVersion($skill);
        $this->writeSkillFile($project, $skill);

        return true;
    }

    protected function importAgent(Project $project, array $data): void
    {
        $agent = \App\Models\Agent::where('slug', $data['slug'])->first();

        if (! $agent) {
            $agent = \App\Models\Agent::create([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'role' => $data['role'] ?? '',
                'description' => $data['description'] ?? null,
                'base_instructions' => $data['base_instructions'] ?? '',
                'icon' => $data['icon'] ?? null,
            ]);
        }

        // Attach to project if not already attached
        if (! $project->agents()->where('agents.id', $agent->id)->exists()) {
            $project->agents()->attach($agent->id, [
                'custom_instructions' => $data['custom_instructions'] ?? null,
                'is_enabled' => true,
            ]);
        }
    }

    protected function syncSkillTags($skill, array $tagNames): void
    {
        $tagIds = collect($tagNames)->map(function (string $name) {
            return Tag::firstOrCreate(['name' => trim($name)])->id;
        });

        $skill->tags()->sync($tagIds);
    }

    protected function createVersion($skill): void
    {
        $nextVersion = ($skill->versions()->max('version_number') ?? 0) + 1;

        $skill->versions()->create([
            'version_number' => $nextVersion,
            'frontmatter' => [
                'id' => $skill->slug,
                'name' => $skill->name,
                'description' => $skill->description,
                'model' => $skill->model,
                'max_tokens' => $skill->max_tokens,
                'tools' => $skill->tools,
                'tags' => $skill->tags->pluck('name')->values()->all(),
            ],
            'body' => $skill->body,
            'saved_at' => now(),
        ]);
    }

    protected function writeSkillFile(Project $project, $skill): void
    {
        $frontmatter = [
            'id' => $skill->slug,
            'name' => $skill->name,
            'description' => $skill->description,
            'tags' => $skill->tags->pluck('name')->values()->all(),
            'model' => $skill->model,
            'max_tokens' => $skill->max_tokens,
            'tools' => $skill->tools ?? [],
            'includes' => $skill->includes ?? [],
            'created_at' => $skill->created_at->toIso8601String(),
            'updated_at' => $skill->updated_at->toIso8601String(),
        ];

        $this->manifestService->writeSkillFile($project->resolved_path, $frontmatter, $skill->body ?? '');
    }
}
