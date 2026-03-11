<?php

namespace App\Services;

use App\Models\ProjectRepository;
use Illuminate\Support\Facades\Http;

class RepositoryFileService
{
    /**
     * Only these path prefixes are allowed for read/write operations.
     * This ensures repository access is strictly scoped to AI config files.
     */
    public const ALLOWED_PATHS = [
        '.agentis/',
        '.claude/',
        '.cursor/rules/',
        '.github/copilot-instructions.md',
        '.windsurf/rules/',
        '.clinerules',
        '.openai/',
    ];

    public function isPathAllowed(string $path): bool
    {
        $path = ltrim($path, '/');

        foreach (self::ALLOWED_PATHS as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
            // Exact match for single files like .clinerules
            if (! str_ends_with($prefix, '/') && $path === $prefix) {
                return true;
            }
        }

        return false;
    }

    public function assertPathAllowed(string $path): void
    {
        if (! $this->isPathAllowed($path)) {
            throw new \InvalidArgumentException(
                "Access denied: '{$path}' is outside the allowed AI configuration paths. " .
                'Only files in ' . implode(', ', self::ALLOWED_PATHS) . ' can be accessed.'
            );
        }
    }

    /**
     * List files in the repository that fall within allowed AI paths.
     */
    public function listAllowedFiles(ProjectRepository $repository, ?string $branch = null): array
    {
        $branch = $branch ?? $repository->default_branch;
        $tree = $this->fetchTree($repository, $branch);

        return collect($tree)
            ->filter(fn (array $item) => $item['type'] === 'blob' && $this->isPathAllowed($item['path']))
            ->map(fn (array $item) => [
                'path' => $item['path'],
                'size' => $item['size'] ?? null,
                'sha' => $item['sha'] ?? $item['id'] ?? null,
            ])
            ->values()
            ->all();
    }

    /**
     * Read a file from the repository (must be in allowed paths).
     */
    public function readFile(ProjectRepository $repository, string $path, ?string $branch = null): ?string
    {
        $this->assertPathAllowed($path);

        $branch = $branch ?? $repository->default_branch;

        if ($repository->isGitHub()) {
            $response = $this->githubRequest($repository, "/contents/{$path}", ['ref' => $branch]);

            if (! $response->successful()) {
                return null;
            }

            $data = $response->json();

            if (($data['encoding'] ?? '') === 'base64') {
                return base64_decode($data['content']);
            }

            return $data['content'] ?? null;
        }

        // GitLab
        $encodedPath = rawurlencode($path);
        $response = $this->gitlabRequest($repository, "/repository/files/{$encodedPath}/raw", ['ref' => $branch]);

        return $response->successful() ? $response->body() : null;
    }

    /**
     * Write a file to the repository (must be in allowed paths).
     * Creates or updates the file with a commit.
     */
    public function writeFile(
        ProjectRepository $repository,
        string $path,
        string $content,
        string $commitMessage,
        ?string $branch = null,
    ): array {
        $this->assertPathAllowed($path);

        $branch = $branch ?? $repository->default_branch;

        if ($repository->isGitHub()) {
            return $this->githubWriteFile($repository, $path, $content, $commitMessage, $branch);
        }

        return $this->gitlabWriteFile($repository, $path, $content, $commitMessage, $branch);
    }

    /**
     * Delete a file from the repository (must be in allowed paths).
     */
    public function deleteFile(
        ProjectRepository $repository,
        string $path,
        string $commitMessage,
        ?string $branch = null,
    ): bool {
        $this->assertPathAllowed($path);

        $branch = $branch ?? $repository->default_branch;

        if ($repository->isGitHub()) {
            $sha = $this->getGitHubFileSha($repository, $path, $branch);
            if (! $sha) {
                return false;
            }

            $response = Http::withHeaders($this->githubHeaders($repository))
                ->timeout(15)
                ->delete($repository->apiBaseUrl() . "/contents/{$path}", [
                    'message' => $commitMessage,
                    'sha' => $sha,
                    'branch' => $branch,
                ]);

            return $response->successful();
        }

        // GitLab
        $encodedPath = rawurlencode($path);
        $response = Http::withHeaders($this->gitlabHeaders($repository))
            ->timeout(15)
            ->delete($repository->apiBaseUrl() . "/repository/files/{$encodedPath}", [
                'commit_message' => $commitMessage,
                'branch' => $branch,
            ]);

        return $response->successful();
    }

    /**
     * Push multiple skill files to the repository in a single commit.
     */
    public function pushSkillFiles(
        ProjectRepository $repository,
        array $files,
        string $commitMessage,
        ?string $branch = null,
    ): array {
        foreach ($files as $file) {
            $this->assertPathAllowed($file['path']);
        }

        $branch = $branch ?? $repository->default_branch;
        $results = [];

        // GitHub doesn't have a multi-file commit API via REST — use individual writes
        // (for a production app, you'd use the Git Trees API for atomic commits)
        foreach ($files as $file) {
            try {
                $result = $this->writeFile($repository, $file['path'], $file['content'], $commitMessage, $branch);
                $results[] = ['path' => $file['path'], 'status' => 'success', 'sha' => $result['sha'] ?? null];
            } catch (\Exception $e) {
                $results[] = ['path' => $file['path'], 'status' => 'error', 'message' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * Pull skill files from the repository (reads all .agentis/skills/ files).
     */
    public function pullSkillFiles(ProjectRepository $repository, ?string $branch = null): array
    {
        $allowedFiles = $this->listAllowedFiles($repository, $branch);

        $skillFiles = collect($allowedFiles)
            ->filter(fn (array $f) => str_starts_with($f['path'], '.agentis/skills/'))
            ->all();

        $results = [];
        foreach ($skillFiles as $file) {
            $content = $this->readFile($repository, $file['path'], $branch);
            if ($content !== null) {
                $results[] = [
                    'path' => $file['path'],
                    'content' => $content,
                    'sha' => $file['sha'],
                ];
            }
        }

        return $results;
    }

    // --- Private GitHub helpers ---

    protected function fetchTree(ProjectRepository $repository, string $branch): array
    {
        if ($repository->isGitHub()) {
            $response = $this->githubRequest($repository, "/git/trees/{$branch}", ['recursive' => '1']);

            return $response->successful() ? ($response->json()['tree'] ?? []) : [];
        }

        // GitLab
        $allItems = [];
        $page = 1;
        do {
            $response = $this->gitlabRequest($repository, '/repository/tree', [
                'ref' => $branch,
                'recursive' => 'true',
                'per_page' => 100,
                'page' => $page,
            ]);

            if (! $response->successful()) {
                break;
            }

            $items = $response->json();
            $allItems = array_merge($allItems, $items);
            $page++;
        } while (count($items) === 100);

        return $allItems;
    }

    protected function githubWriteFile(
        ProjectRepository $repository,
        string $path,
        string $content,
        string $commitMessage,
        string $branch,
    ): array {
        $sha = $this->getGitHubFileSha($repository, $path, $branch);

        $payload = [
            'message' => $commitMessage,
            'content' => base64_encode($content),
            'branch' => $branch,
        ];

        if ($sha) {
            $payload['sha'] = $sha;
        }

        $response = Http::withHeaders($this->githubHeaders($repository))
            ->timeout(15)
            ->put($repository->apiBaseUrl() . "/contents/{$path}", $payload);

        if (! $response->successful()) {
            throw new \RuntimeException("GitHub write failed for {$path}: {$response->status()}");
        }

        return [
            'sha' => $response->json()['content']['sha'] ?? null,
            'path' => $path,
        ];
    }

    protected function gitlabWriteFile(
        ProjectRepository $repository,
        string $path,
        string $content,
        string $commitMessage,
        string $branch,
    ): array {
        $encodedPath = rawurlencode($path);
        $exists = $this->readFile($repository, $path, $branch) !== null;

        $payload = [
            'branch' => $branch,
            'commit_message' => $commitMessage,
            'content' => $content,
        ];

        $method = $exists ? 'put' : 'post';
        $response = Http::withHeaders($this->gitlabHeaders($repository))
            ->timeout(15)
            ->{$method}($repository->apiBaseUrl() . "/repository/files/{$encodedPath}", $payload);

        if (! $response->successful()) {
            throw new \RuntimeException("GitLab write failed for {$path}: {$response->status()}");
        }

        return [
            'sha' => $response->json()['content_sha256'] ?? null,
            'path' => $path,
        ];
    }

    protected function getGitHubFileSha(ProjectRepository $repository, string $path, string $branch): ?string
    {
        $response = $this->githubRequest($repository, "/contents/{$path}", ['ref' => $branch]);

        return $response->successful() ? ($response->json()['sha'] ?? null) : null;
    }

    protected function githubRequest(ProjectRepository $repository, string $endpoint, array $query = []): \Illuminate\Http\Client\Response
    {
        return Http::withHeaders($this->githubHeaders($repository))
            ->timeout(10)
            ->get($repository->apiBaseUrl() . $endpoint, $query);
    }

    protected function gitlabRequest(ProjectRepository $repository, string $endpoint, array $query = []): \Illuminate\Http\Client\Response
    {
        return Http::withHeaders($this->gitlabHeaders($repository))
            ->timeout(10)
            ->get($repository->apiBaseUrl() . $endpoint, $query);
    }

    protected function githubHeaders(ProjectRepository $repository): array
    {
        $headers = ['Accept' => 'application/vnd.github.v3+json'];
        if ($repository->hasAccessToken()) {
            $headers['Authorization'] = "Bearer {$repository->access_token}";
        }

        return $headers;
    }

    protected function gitlabHeaders(ProjectRepository $repository): array
    {
        $headers = ['Accept' => 'application/json'];
        if ($repository->hasAccessToken()) {
            $headers['Authorization'] = "Bearer {$repository->access_token}";
        }

        return $headers;
    }
}
