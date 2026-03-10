<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Http;

class SkillsShService
{
    /**
     * Directories where skills.sh conventions place SKILL.md files.
     */
    private const SKILL_DIRS = [
        'skills/',
        'skills/.curated/',
        'skills/.experimental/',
        'skills/.system/',
        '.claude/skills/',
        '.agents/skills/',
        '.continue/skills/',
    ];

    /**
     * Discover all SKILL.md files in a GitHub repository.
     *
     * Uses a single API call (recursive tree) to find all SKILL.md paths.
     * Does NOT fetch file contents — that happens on import.
     * Names are derived from directory structure for speed.
     *
     * @return array<int, array{path: string, name: string}>
     */
    public function discoverSkills(string $ownerRepo): array
    {
        $this->validateRepo($ownerRepo);

        // Get the full file tree in one API call
        $response = $this->githubRequest(
            "https://api.github.com/repos/{$ownerRepo}/git/trees/HEAD",
            ['recursive' => '1'],
        );

        if ($response->failed()) {
            if ($response->status() === 404) {
                throw new \RuntimeException("Repository not found: {$ownerRepo}");
            }
            if ($response->status() === 403) {
                throw new \RuntimeException('GitHub API rate limit exceeded. Configure a GitHub token in Settings for higher limits.');
            }
            throw new \RuntimeException("GitHub API error: {$response->status()}");
        }

        $tree = $response->json('tree', []);

        return collect($tree)
            ->filter(fn (array $item) => $item['type'] === 'blob' && $this->isSkillFile($item['path']))
            ->map(fn (array $item) => [
                'path' => $item['path'],
                'name' => $this->deriveNameFromPath($item['path']),
            ])
            ->values()
            ->all();
    }

    /**
     * Fetch, parse and return details for a batch of SKILL.md files.
     * Used when the UI needs descriptions for a subset of discovered skills.
     *
     * @param  string[]  $paths
     * @return array<int, array{path: string, name: string, description: string|null, body: string, frontmatter: array}>
     */
    public function fetchSkillBatch(string $ownerRepo, array $paths): array
    {
        $this->validateRepo($ownerRepo);

        $parser = app(SkillFileParser::class);
        $skills = [];

        foreach (array_slice($paths, 0, 30) as $path) {
            try {
                $content = $this->fetchRawFile($ownerRepo, $path);
                $parsed = $parser->parseContent($content);

                $skills[] = [
                    'path' => $path,
                    'name' => $parsed['frontmatter']['name'] ?? $this->deriveNameFromPath($path),
                    'description' => $parsed['frontmatter']['description'] ?? null,
                    'body' => $parsed['body'],
                    'frontmatter' => $parsed['frontmatter'],
                ];
            } catch (\Throwable) {
                continue;
            }
        }

        return $skills;
    }

    /**
     * Fetch and parse a single SKILL.md file from a GitHub repository.
     *
     * @return array{path: string, name: string, description: string|null, body: string, frontmatter: array}
     */
    public function fetchSkill(string $ownerRepo, string $path): array
    {
        $this->validateRepo($ownerRepo);

        $content = $this->fetchRawFile($ownerRepo, $path);
        $parser = app(SkillFileParser::class);
        $parsed = $parser->parseContent($content);

        $dirName = basename(dirname($path));
        $name = $parsed['frontmatter']['name'] ?? $dirName;

        return [
            'path' => $path,
            'name' => $name,
            'description' => $parsed['frontmatter']['description'] ?? null,
            'body' => $parsed['body'],
            'frontmatter' => $parsed['frontmatter'],
        ];
    }

    /**
     * Check if a file path matches a SKILL.md location.
     *
     * Accepts any SKILL.md file in the repo — many popular repos
     * place skills at arbitrary paths (e.g. `artifacts-builder/SKILL.md`).
     */
    private function isSkillFile(string $path): bool
    {
        return str_ends_with($path, '/SKILL.md') || $path === 'SKILL.md';
    }

    /**
     * Derive a human-readable name from a SKILL.md path.
     */
    private function deriveNameFromPath(string $path): string
    {
        $dir = basename(dirname($path));

        if ($dir === '.' || $dir === '') {
            return 'Root Skill';
        }

        return str_replace('-', ' ', $dir);
    }

    /**
     * Fetch raw file content from GitHub.
     */
    private function fetchRawFile(string $ownerRepo, string $path): string
    {
        $response = $this->githubRequest(
            "https://raw.githubusercontent.com/{$ownerRepo}/HEAD/{$path}",
        );

        if ($response->failed()) {
            throw new \RuntimeException("Failed to fetch {$path}: HTTP {$response->status()}");
        }

        return $response->body();
    }

    /**
     * Make a GitHub API request with optional token auth.
     */
    private function githubRequest(string $url, array $query = []): \Illuminate\Http\Client\Response
    {
        $request = Http::timeout(15)
            ->withHeaders([
                'User-Agent' => 'Agentis-Studio/1.0',
                'Accept' => 'application/vnd.github.v3+json',
            ]);

        $token = AppSetting::get('github_token');
        if ($token) {
            $request = $request->withToken($token);
        }

        return $request->get($url, $query);
    }

    /**
     * Validate the owner/repo format.
     */
    private function validateRepo(string $ownerRepo): void
    {
        if (! preg_match('#^[\w.\-]+/[\w.\-]+$#', $ownerRepo)) {
            throw new \InvalidArgumentException("Invalid repository format: {$ownerRepo}. Expected: owner/repo");
        }
    }
}
