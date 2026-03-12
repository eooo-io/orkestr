<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectA2aAgent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class A2aAgentController extends Controller
{
    public function index(Project $project): JsonResponse
    {
        return response()->json([
            'a2a_agents' => $project->a2aAgents()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'url' => 'required|string|max:500',
            'description' => 'nullable|string|max:2000',
            'skills' => 'nullable|array',
            'skills.*' => 'string',
            'enabled' => 'boolean',
        ]);

        $agent = $project->a2aAgents()->create($validated);

        return response()->json(['a2a_agent' => $agent], 201);
    }

    public function update(Request $request, ProjectA2aAgent $a2aAgent): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'url' => 'sometimes|string|max:500',
            'description' => 'nullable|string|max:2000',
            'skills' => 'nullable|array',
            'skills.*' => 'string',
            'enabled' => 'boolean',
        ]);

        $a2aAgent->update($validated);

        return response()->json(['a2a_agent' => $a2aAgent->fresh()]);
    }

    public function destroy(ProjectA2aAgent $a2aAgent): JsonResponse
    {
        $a2aAgent->delete();

        return response()->json(['message' => 'A2A agent removed.']);
    }
}
