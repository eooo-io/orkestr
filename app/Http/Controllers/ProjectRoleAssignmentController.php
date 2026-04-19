<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectRoleAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectRoleAssignmentController extends Controller
{
    public function index(Project $project): JsonResponse
    {
        $assignments = ProjectRoleAssignment::where('project_id', $project->id)
            ->active()
            ->with('user:id,name,email')
            ->orderBy('role')
            ->get();

        return response()->json(['data' => $assignments]);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'role' => 'required|string|in:ic,dri,coach',
            'scope' => 'nullable|string|max:200',
            'started_at' => 'nullable|date',
        ]);

        $assignment = ProjectRoleAssignment::create([
            'project_id' => $project->id,
            'user_id' => $validated['user_id'],
            'role' => $validated['role'],
            'scope' => $validated['scope'] ?? null,
            'started_at' => $validated['started_at'] ?? now(),
        ]);

        return response()->json([
            'data' => $assignment->load('user:id,name,email'),
        ], 201);
    }

    public function update(Request $request, Project $project, ProjectRoleAssignment $assignment): JsonResponse
    {
        if ($assignment->project_id !== $project->id) {
            abort(404);
        }

        $validated = $request->validate([
            'role' => 'sometimes|string|in:ic,dri,coach',
            'scope' => 'nullable|string|max:200',
            'ended_at' => 'nullable|date',
        ]);

        $assignment->update($validated);

        return response()->json(['data' => $assignment->fresh('user:id,name,email')]);
    }

    public function destroy(Project $project, ProjectRoleAssignment $assignment): JsonResponse
    {
        if ($assignment->project_id !== $project->id) {
            abort(404);
        }

        $assignment->update(['ended_at' => now()]);

        return response()->json(['message' => 'Assignment ended']);
    }
}
