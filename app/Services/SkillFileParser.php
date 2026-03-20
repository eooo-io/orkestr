<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

class SkillFileParser
{
    /**
     * Allowed subdirectory names within a skill folder.
     */
    public const ASSET_DIRS = ['assets', 'scripts', 'data'];

    /**
     * Parse a skill file or folder into its components.
     *
     * Supports both flat files (.agentis/skills/slug.md) and
     * folder-based skills (.agentis/skills/slug/skill.md).
     *
     * @return array{frontmatter: array, body: string, assets: array}
     */
    public function parseFile(string $absolutePath): array
    {
        $content = file_get_contents($absolutePath);
        $result = $this->parseContent($content);

        // If this is a skill.md inside a folder, inventory assets
        if (basename($absolutePath) === 'skill.md') {
            $result['assets'] = $this->inventoryAssets(dirname($absolutePath));
        } else {
            $result['assets'] = [];
        }

        return $result;
    }

    /**
     * Parse skill content string into frontmatter and body.
     *
     * @return array{frontmatter: array, body: string}
     */
    public function parseContent(string $content): array
    {
        $content = ltrim($content);

        if (! str_starts_with($content, '---')) {
            return [
                'frontmatter' => [],
                'body' => trim($content),
            ];
        }

        // Find the closing --- delimiter
        $endPos = strpos($content, "\n---", 3);

        if ($endPos === false) {
            return [
                'frontmatter' => [],
                'body' => trim($content),
            ];
        }

        $yamlBlock = substr($content, 4, $endPos - 4);
        $body = substr($content, $endPos + 4);

        $frontmatter = Yaml::parse(trim($yamlBlock)) ?? [];

        return [
            'frontmatter' => $frontmatter,
            'body' => trim($body),
        ];
    }

    /**
     * Render a skill file from frontmatter and body.
     */
    public function renderFile(array $frontmatter, string $body): string
    {
        $yaml = Yaml::dump($frontmatter, 2, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);

        return "---\n{$yaml}---\n\n{$body}\n";
    }

    /**
     * Validate frontmatter and return an array of errors (empty = valid).
     *
     * @return string[]
     */
    public function validateFrontmatter(array $data): array
    {
        $errors = [];

        if (empty($data['id'])) {
            $errors[] = 'Missing required field: id';
        }

        if (empty($data['name'])) {
            $errors[] = 'Missing required field: name';
        }

        return $errors;
    }

    /**
     * Detect whether a skill path is a folder-based skill.
     */
    public function isFolderSkill(string $skillsDir, string $slug): bool
    {
        return File::isDirectory($skillsDir . '/' . $slug)
            && File::exists($skillsDir . '/' . $slug . '/skill.md');
    }

    /**
     * Resolve the skill file path (flat or folder) for a given slug.
     * Returns null if neither format exists.
     */
    public function resolveSkillPath(string $skillsDir, string $slug): ?string
    {
        // Folder format takes precedence
        $folderPath = $skillsDir . '/' . $slug . '/skill.md';
        if (File::exists($folderPath)) {
            return $folderPath;
        }

        $flatPath = $skillsDir . '/' . $slug . '.md';
        if (File::exists($flatPath)) {
            return $flatPath;
        }

        return null;
    }

    /**
     * Inventory all assets within a skill folder.
     *
     * @return array<int, array{path: string, name: string, directory: string, size: int, type: string}>
     */
    public function inventoryAssets(string $skillFolderPath): array
    {
        $assets = [];

        foreach (self::ASSET_DIRS as $dir) {
            $dirPath = $skillFolderPath . '/' . $dir;

            if (! File::isDirectory($dirPath)) {
                continue;
            }

            $files = File::allFiles($dirPath);

            foreach ($files as $file) {
                $relativePath = $dir . '/' . $file->getRelativePathname();
                $assets[] = [
                    'path' => $relativePath,
                    'name' => $file->getFilename(),
                    'directory' => $dir,
                    'size' => $file->getSize(),
                    'type' => $file->getExtension(),
                ];
            }
        }

        return $assets;
    }
}
