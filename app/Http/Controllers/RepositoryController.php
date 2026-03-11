<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectRepository;
use App\Services\RepositoryConnectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RepositoryController extends Controller
{
    public function __construct(
        private RepositoryConnectionService $repositoryService,
    ) {}

    public function show(Project $project): JsonResponse
    {
        $repositories = $project->repositories()->get();

        return response()->json([
            'data' => $repositories->map(fn (ProjectRepository $repo) => $this->formatRepository($repo)),
        ]);
    }

    public function connect(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'provider' => 'required|string|in:github,gitlab',
            'full_name' => 'required|string|max:255', // "owner/repo"
            'access_token' => 'nullable|string|max:500',
            'auto_scan_on_push' => 'nullable|boolean',
            'auto_sync_on_push' => 'nullable|boolean',
        ]);

        try {
            $repository = $this->repositoryService->connect($project, $validated);

            return response()->json([
                'data' => $this->formatRepository($repository),
                'message' => 'Repository connected successfully.',
            ], 201);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => 'Failed to connect repository.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function disconnect(Project $project, string $provider): JsonResponse
    {
        $repository = $project->repositories()
            ->where('provider', $provider)
            ->firstOrFail();

        $this->repositoryService->disconnect($repository);

        return response()->json(['message' => 'Repository disconnected.']);
    }

    public function status(Project $project, string $provider): JsonResponse
    {
        $repository = $project->repositories()
            ->where('provider', $provider)
            ->firstOrFail();

        $status = $this->repositoryService->fetchStatus($repository);

        return response()->json(['data' => $status]);
    }

    public function branches(Project $project, string $provider): JsonResponse
    {
        $repository = $project->repositories()
            ->where('provider', $provider)
            ->firstOrFail();

        $branches = $this->repositoryService->fetchBranches($repository);

        return response()->json(['data' => $branches]);
    }

    public function latestCommit(Project $project, string $provider): JsonResponse
    {
        $repository = $project->repositories()
            ->where('provider', $provider)
            ->firstOrFail();

        $commit = $this->repositoryService->fetchLatestCommit($repository);

        if (! $commit) {
            return response()->json(['message' => 'Could not fetch latest commit.'], 422);
        }

        return response()->json(['data' => $commit]);
    }

    public function update(Request $request, Project $project, string $provider): JsonResponse
    {
        $repository = $project->repositories()
            ->where('provider', $provider)
            ->firstOrFail();

        $validated = $request->validate([
            'access_token' => 'nullable|string|max:500',
            'auto_scan_on_push' => 'nullable|boolean',
            'auto_sync_on_push' => 'nullable|boolean',
            'default_branch' => 'nullable|string|max:255',
        ]);

        $repository->update($validated);

        return response()->json([
            'data' => $this->formatRepository($repository->fresh()),
            'message' => 'Repository settings updated.',
        ]);
    }

    private function formatRepository(ProjectRepository $repo): array
    {
        return [
            'id' => $repo->id,
            'provider' => $repo->provider,
            'owner' => $repo->owner,
            'name' => $repo->name,
            'full_name' => $repo->full_name,
            'default_branch' => $repo->default_branch,
            'url' => $repo->url,
            'has_access_token' => $repo->hasAccessToken(),
            'auto_scan_on_push' => $repo->auto_scan_on_push,
            'auto_sync_on_push' => $repo->auto_sync_on_push,
            'last_synced_at' => $repo->last_synced_at?->toIso8601String(),
            'last_commit_sha' => $repo->last_commit_sha,
            'created_at' => $repo->created_at->toIso8601String(),
        ];
    }
}
