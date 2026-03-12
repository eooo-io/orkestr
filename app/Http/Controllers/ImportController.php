<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\ProviderImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImportController extends Controller
{
    public function __construct(
        protected ProviderImportService $importService,
    ) {}

    /**
     * Detect importable skills from provider config files at a given path.
     */
    public function detect(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'path' => 'required|string',
            'provider' => 'nullable|string|in:claude,cursor,copilot,windsurf,cline,openai',
        ]);

        $path = realpath($validated['path']);

        if (! $path || ! is_dir($path)) {
            return response()->json(['error' => 'Directory not found'], 404);
        }

        $detected = $this->importService->detect($path, $validated['provider'] ?? null);

        $summary = [];
        foreach ($detected as $provider => $skills) {
            $summary[$provider] = collect($skills)->map(fn ($s) => [
                'name' => $s['name'],
                'slug' => $s['slug'],
                'description' => $s['description'],
                'body_length' => strlen($s['body']),
                'tags' => $s['tags'],
            ])->all();
        }

        return response()->json(['data' => $summary]);
    }

    /**
     * Import detected skills into a project.
     */
    public function import(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'path' => 'required|string',
            'provider' => 'nullable|string|in:claude,cursor,copilot,windsurf,cline,openai',
        ]);

        $path = realpath($validated['path']);

        if (! $path || ! is_dir($path)) {
            return response()->json(['error' => 'Directory not found'], 404);
        }

        $detected = $this->importService->detect($path, $validated['provider'] ?? null);

        if (empty($detected)) {
            return response()->json(['data' => ['created' => 0, 'skipped' => 0, 'message' => 'No provider config files found']]);
        }

        $result = $this->importService->import($project, $detected);

        return response()->json(['data' => $result]);
    }
}
