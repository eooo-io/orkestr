<?php

namespace App\Http\Controllers;

use App\Models\Artifact;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ArtifactController extends Controller
{
    public function index(Request $request, Project $project): JsonResponse
    {
        $query = $project->artifacts()
            ->with(['agent:id,name,slug,icon'])
            ->whereNull('parent_artifact_id') // Only root artifacts (latest versions)
            ->latest();

        if ($type = $request->query('type')) {
            $query->ofType($type);
        }
        if ($status = $request->query('status')) {
            $query->withStatus($status);
        }
        if ($agentId = $request->query('agent_id')) {
            $query->where('agent_id', $agentId);
        }

        return response()->json(['data' => $query->get()]);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string|in:report,code,dataset,decision,document,image,other',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'content' => 'nullable|string',
            'metadata' => 'nullable|array',
            'format' => 'sometimes|string|in:markdown,json,csv,html,pdf,plain,binary',
            'status' => 'sometimes|string|in:draft,pending_review,approved,published',
            'agent_id' => 'nullable|integer|exists:agents,id',
            'execution_run_id' => 'nullable|integer',
        ]);

        $artifact = $project->artifacts()->create($validated);

        return response()->json(['data' => $artifact->load('agent:id,name,slug,icon')], 201);
    }

    public function show(Artifact $artifact): JsonResponse
    {
        return response()->json([
            'data' => $artifact->load(['agent:id,name,slug,icon', 'reviewer:id,name']),
        ]);
    }

    public function update(Request $request, Artifact $artifact): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'content' => 'nullable|string',
            'metadata' => 'nullable|array',
            'status' => 'sometimes|string|in:draft,pending_review,approved,rejected,published',
        ]);

        $artifact->update($validated);

        return response()->json(['data' => $artifact]);
    }

    public function destroy(Artifact $artifact): JsonResponse
    {
        // Delete file if stored on disk
        if ($artifact->file_path && File::exists($artifact->file_path)) {
            File::delete($artifact->file_path);
        }

        $artifact->delete();

        return response()->json(['message' => 'Artifact deleted']);
    }

    public function versions(Artifact $artifact): JsonResponse
    {
        // Get all versions in the chain
        $root = $artifact->rootArtifact();
        $versions = Artifact::where('parent_artifact_id', $root->id)
            ->orWhere('id', $root->id)
            ->orderByDesc('version_number')
            ->get();

        return response()->json(['data' => $versions]);
    }

    public function approve(Request $request, Artifact $artifact): JsonResponse
    {
        $artifact->update([
            'status' => 'approved',
            'reviewed_by' => $request->user()?->id,
            'reviewed_at' => now(),
        ]);

        return response()->json(['data' => $artifact]);
    }

    public function reject(Request $request, Artifact $artifact): JsonResponse
    {
        $artifact->update([
            'status' => 'rejected',
            'reviewed_by' => $request->user()?->id,
            'reviewed_at' => now(),
        ]);

        return response()->json(['data' => $artifact]);
    }

    public function download(Artifact $artifact): BinaryFileResponse|JsonResponse
    {
        if ($artifact->file_path && File::exists($artifact->file_path)) {
            return response()->file($artifact->file_path);
        }

        // For content-based artifacts, return as download
        if ($artifact->content) {
            $ext = match ($artifact->format) {
                'json' => 'json',
                'csv' => 'csv',
                'html' => 'html',
                'plain' => 'txt',
                default => 'md',
            };

            $tempPath = tempnam(sys_get_temp_dir(), 'artifact_') . '.' . $ext;
            File::put($tempPath, $artifact->content);

            return response()->download($tempPath, "{$artifact->title}.{$ext}")->deleteFileAfterSend();
        }

        return response()->json(['error' => 'No downloadable content'], 404);
    }

    /**
     * List artifacts for a specific execution run.
     */
    public function forExecution(int $executionRunId): JsonResponse
    {
        $artifacts = Artifact::where('execution_run_id', $executionRunId)
            ->with('agent:id,name,slug,icon')
            ->orderBy('created_at')
            ->get();

        return response()->json(['data' => $artifacts]);
    }
}
