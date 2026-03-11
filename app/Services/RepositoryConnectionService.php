<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectRepository;
use Illuminate\Support\Facades\Http;

class RepositoryConnectionService
{
    public function connect(Project $project, array $data): ProjectRepository
    {
        $repoInfo = $this->fetchRepositoryInfo($data['provider'], $data['full_name'], $data['access_token'] ?? null);

        return ProjectRepository::updateOrCreate(
            [
                'project_id' => $project->id,
                'provider' => $data['provider'],
            ],
            [
                'owner' => $repoInfo['owner'],
                'name' => $repoInfo['name'],
                'full_name' => $repoInfo['full_name'],
                'default_branch' => $repoInfo['default_branch'],
                'url' => $repoInfo['url'],
                'clone_url' => $repoInfo['clone_url'],
                'access_token' => $data['access_token'] ?? null,
                'auto_scan_on_push' => $data['auto_scan_on_push'] ?? true,
                'auto_sync_on_push' => $data['auto_sync_on_push'] ?? false,
            ],
        );
    }

    public function disconnect(ProjectRepository $repository): void
    {
        $repository->delete();
    }

    public function fetchStatus(ProjectRepository $repository): array
    {
        if (! $repository->hasAccessToken()) {
            return [
                'connected' => true,
                'accessible' => false,
                'reason' => 'No access token configured',
            ];
        }

        try {
            $response = $this->apiRequest($repository, '');

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'connected' => true,
                    'accessible' => true,
                    'default_branch' => $data['default_branch'] ?? $data['default_branch'] ?? null,
                    'visibility' => $data['visibility'] ?? ($data['private'] ? 'private' : 'public'),
                    'last_push' => $data['pushed_at'] ?? $data['last_activity_at'] ?? null,
                    'open_issues' => $data['open_issues_count'] ?? $data['open_issues_count'] ?? null,
                ];
            }

            return [
                'connected' => true,
                'accessible' => false,
                'reason' => 'API returned ' . $response->status(),
            ];
        } catch (\Exception $e) {
            return [
                'connected' => true,
                'accessible' => false,
                'reason' => $e->getMessage(),
            ];
        }
    }

    public function fetchBranches(ProjectRepository $repository): array
    {
        $endpoint = $repository->isGitHub() ? '/branches' : '/repository/branches';

        $response = $this->apiRequest($repository, $endpoint);

        if (! $response->successful()) {
            return [];
        }

        return collect($response->json())
            ->pluck('name')
            ->values()
            ->all();
    }

    public function fetchLatestCommit(ProjectRepository $repository, ?string $branch = null): ?array
    {
        $branch = $branch ?? $repository->default_branch;

        if ($repository->isGitHub()) {
            $response = $this->apiRequest($repository, "/commits/{$branch}");
        } else {
            $response = $this->apiRequest($repository, "/repository/commits/{$branch}");
        }

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();

        if ($repository->isGitHub()) {
            return [
                'sha' => $data['sha'],
                'message' => $data['commit']['message'],
                'author' => $data['commit']['author']['name'],
                'date' => $data['commit']['author']['date'],
            ];
        }

        return [
            'sha' => $data['id'],
            'message' => $data['message'],
            'author' => $data['author_name'],
            'date' => $data['created_at'],
        ];
    }

    protected function fetchRepositoryInfo(string $provider, string $fullName, ?string $token): array
    {
        $headers = ['Accept' => 'application/json'];

        if ($token) {
            $headers['Authorization'] = match ($provider) {
                'github' => "Bearer {$token}",
                'gitlab' => "Bearer {$token}",
            };
        }

        $url = match ($provider) {
            'github' => "https://api.github.com/repos/{$fullName}",
            'gitlab' => "https://gitlab.com/api/v4/projects/" . urlencode($fullName),
            default => throw new \InvalidArgumentException("Unsupported provider: {$provider}"),
        };

        $response = Http::withHeaders($headers)
            ->timeout(10)
            ->get($url);

        if (! $response->successful()) {
            throw new \RuntimeException("Failed to fetch repository info: {$response->status()} {$response->body()}");
        }

        $data = $response->json();

        if ($provider === 'github') {
            return [
                'owner' => $data['owner']['login'],
                'name' => $data['name'],
                'full_name' => $data['full_name'],
                'default_branch' => $data['default_branch'],
                'url' => $data['html_url'],
                'clone_url' => $data['clone_url'],
            ];
        }

        // GitLab
        $pathParts = explode('/', $data['path_with_namespace']);

        return [
            'owner' => implode('/', array_slice($pathParts, 0, -1)),
            'name' => $data['path'],
            'full_name' => $data['path_with_namespace'],
            'default_branch' => $data['default_branch'],
            'url' => $data['web_url'],
            'clone_url' => $data['http_url_to_repo'],
        ];
    }

    protected function apiRequest(ProjectRepository $repository, string $endpoint): \Illuminate\Http\Client\Response
    {
        $headers = ['Accept' => 'application/json'];

        if ($repository->hasAccessToken()) {
            $headers['Authorization'] = "Bearer {$repository->access_token}";
        }

        return Http::withHeaders($headers)
            ->timeout(10)
            ->get($repository->apiBaseUrl() . $endpoint);
    }
}
