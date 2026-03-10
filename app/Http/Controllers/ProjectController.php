<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProjectResource;
use App\Jobs\ProjectScanJob;
use App\Models\Project;
use App\Models\ProjectProvider;
use App\Rules\SafeProjectPath;
use App\Services\ProviderSyncService;
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
        ]);

        $project = Project::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'path' => $validated['path'],
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

    public function sync(Project $project, ProviderSyncService $syncService): JsonResponse
    {
        $syncService->syncProject($project);

        return response()->json(['message' => 'Sync complete']);
    }
}
