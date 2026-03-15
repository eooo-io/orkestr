<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GitHubOrgImportService
{
    /**
     * Discover repositories in a GitHub organization that contain .agentis/ directories.
     */
    public function discoverRepos(string $orgName, ?string $token = null): array
    {
        $headers = ['Accept' => 'application/vnd.github+json'];
        if ($token) {
            $headers['Authorization'] = "Bearer {$token}";
        }

        try {
            $response = Http::withHeaders($headers)
                ->get("https://api.github.com/orgs/{$orgName}/repos", [
                    'per_page' => 100,
                    'sort' => 'updated',
                ]);

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'error' => 'Failed to fetch repositories: ' . $response->status(),
                    'repos' => [],
                ];
            }

            $repos = $response->json();
            $discovered = [];

            foreach ($repos as $repo) {
                $hasAgentis = $this->checkForAgentisDir($repo['full_name'], $repo['default_branch'] ?? 'main', $token);

                if ($hasAgentis) {
                    $discovered[] = [
                        'name' => $repo['name'],
                        'full_name' => $repo['full_name'],
                        'description' => $repo['description'],
                        'default_branch' => $repo['default_branch'] ?? 'main',
                        'html_url' => $repo['html_url'],
                        'updated_at' => $repo['updated_at'],
                    ];
                }
            }

            return [
                'success' => true,
                'organization' => $orgName,
                'total_repos' => count($repos),
                'repos_with_agentis' => count($discovered),
                'repos' => $discovered,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'repos' => [],
            ];
        }
    }

    /**
     * Import skills from a GitHub repo's .agentis/skills/ directory.
     */
    public function importFromRepo(string $repoFullName, string $branch = 'main', ?string $token = null): array
    {
        $headers = ['Accept' => 'application/vnd.github+json'];
        if ($token) {
            $headers['Authorization'] = "Bearer {$token}";
        }

        try {
            // List files in .agentis/skills/
            $response = Http::withHeaders($headers)
                ->get("https://api.github.com/repos/{$repoFullName}/contents/.agentis/skills", [
                    'ref' => $branch,
                ]);

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'error' => 'No .agentis/skills/ directory found or inaccessible.',
                    'skills' => [],
                ];
            }

            $files = $response->json();
            $skills = [];

            foreach ($files as $file) {
                if (! str_ends_with($file['name'], '.md')) {
                    continue;
                }

                $contentResponse = Http::withHeaders($headers)
                    ->get($file['download_url'] ?? $file['url']);

                if ($contentResponse->successful()) {
                    $content = $contentResponse->body();

                    // If it came from the API endpoint, it's base64-encoded
                    if (isset($file['content'])) {
                        $content = base64_decode($file['content']);
                    }

                    $skills[] = [
                        'filename' => $file['name'],
                        'path' => $file['path'],
                        'content' => $content,
                        'size' => $file['size'],
                    ];
                }
            }

            return [
                'success' => true,
                'repo' => $repoFullName,
                'branch' => $branch,
                'skills_found' => count($skills),
                'skills' => $skills,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'skills' => [],
            ];
        }
    }

    /**
     * Check if a repo has a .agentis/ directory.
     */
    private function checkForAgentisDir(string $repoFullName, string $branch, ?string $token): bool
    {
        $headers = ['Accept' => 'application/vnd.github+json'];
        if ($token) {
            $headers['Authorization'] = "Bearer {$token}";
        }

        try {
            $response = Http::withHeaders($headers)
                ->get("https://api.github.com/repos/{$repoFullName}/contents/.agentis", [
                    'ref' => $branch,
                ]);

            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }
}
