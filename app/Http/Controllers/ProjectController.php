<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProjectResource;
use App\Jobs\ProjectScanJob;
use App\Models\Project;
use App\Models\ProjectProvider;
use App\Rules\SafeProjectPath;
use App\Services\GitService;
use App\Services\ProviderSyncService;
use App\Services\WebhookDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProjectController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $projects = Project::withCount('skills')
            ->with('providers')
            ->orderBy('name')
            ->get();

        return ProjectResource::collection($projects);
    }

    public function store(Request $request): ProjectResource
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'path' => ['required', 'string', 'max:500', new SafeProjectPath],
            'providers' => 'nullable|array',
            'providers.*' => 'string|in:claude,cursor,copilot,windsurf,cline,openai',
            'git_auto_commit' => 'nullable|boolean',
        ]);

        $project = Project::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'path' => $validated['path'],
            'git_auto_commit' => $validated['git_auto_commit'] ?? false,
        ]);

        if (! empty($validated['providers'])) {
            foreach ($validated['providers'] as $slug) {
                $project->providers()->create(['provider_slug' => $slug]);
            }
        }

        return new ProjectResource($project->load('providers')->loadCount('skills'));
    }

    public function show(Project $project): ProjectResource
    {
        return new ProjectResource($project->load('providers')->loadCount('skills'));
    }

    public function update(Request $request, Project $project): ProjectResource
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'path' => ['sometimes', 'required', 'string', 'max:500', new SafeProjectPath],
            'providers' => 'nullable|array',
            'providers.*' => 'string|in:claude,cursor,copilot,windsurf,cline,openai',
            'git_auto_commit' => 'nullable|boolean',
        ]);

        $project->update(collect($validated)->except('providers')->toArray());

        if (array_key_exists('providers', $validated)) {
            $project->providers()->delete();

            foreach ($validated['providers'] ?? [] as $slug) {
                $project->providers()->create(['provider_slug' => $slug]);
            }
        }

        return new ProjectResource($project->load('providers')->loadCount('skills'));
    }

    public function destroy(Project $project): JsonResponse
    {
        $project->delete();

        return response()->json(['message' => 'Project deleted']);
    }

    public function scan(Project $project): JsonResponse
    {
        ProjectScanJob::dispatch($project);

        return response()->json(['message' => 'Scan queued']);
    }

    public function sync(Project $project, ProviderSyncService $syncService, WebhookDispatcher $webhookDispatcher): JsonResponse
    {
        $syncService->syncProject($project);

        $webhookDispatcher->dispatch('project.synced', $project, [
            'project_id' => $project->id,
            'project_name' => $project->name,
            'providers' => $project->providers->pluck('provider_slug')->all(),
        ]);

        return response()->json(['message' => 'Sync complete']);
    }

    public function syncPreview(Project $project, ProviderSyncService $syncService): JsonResponse
    {
        $preview = $syncService->preview($project);

        return response()->json(['data' => $preview]);
    }

    public function gitLog(Request $request, Project $project, GitService $gitService): JsonResponse
    {
        $file = $request->query('file');

        if (! $file) {
            return response()->json(['message' => 'file parameter required'], 422);
        }

        try {
            $log = $gitService->log($project->resolved_path, $file);
            $branch = $gitService->currentBranch($project->resolved_path);

            return response()->json([
                'data' => $log,
                'branch' => $branch,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function gitDiff(Request $request, Project $project, GitService $gitService): JsonResponse
    {
        $file = $request->query('file');
        $ref = $request->query('ref');

        if (! $file) {
            return response()->json(['message' => 'file parameter required'], 422);
        }

        try {
            $diff = $gitService->diff($project->resolved_path, $file, $ref);

            $content = null;
            if ($ref) {
                $content = $gitService->showAtRef($project->resolved_path, $file, $ref);
            }

            return response()->json([
                'diff' => $diff,
                'content' => $content,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
