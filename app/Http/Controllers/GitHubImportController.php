<?php

namespace App\Http\Controllers;

use App\Services\GitHubOrgImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GitHubImportController extends Controller
{
    public function __construct(
        private GitHubOrgImportService $importService,
    ) {}

    /**
     * POST /api/import/github/discover — discover repos with .agentis/ in a GitHub org.
     */
    public function discover(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'organization' => 'required|string',
            'token' => 'nullable|string',
        ]);

        $result = $this->importService->discoverRepos(
            $validated['organization'],
            $validated['token'] ?? null,
        );

        return response()->json($result);
    }

    /**
     * POST /api/import/github/import — import skills from a GitHub repo.
     */
    public function import(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'repo' => 'required|string',
            'branch' => 'nullable|string',
            'token' => 'nullable|string',
        ]);

        $result = $this->importService->importFromRepo(
            $validated['repo'],
            $validated['branch'] ?? 'main',
            $validated['token'] ?? null,
        );

        return response()->json($result);
    }
}
