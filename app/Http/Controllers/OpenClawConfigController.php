<?php

namespace App\Http\Controllers;

use App\Models\OpenClawConfig;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OpenClawConfigController extends Controller
{
    public function show(Project $project): JsonResponse
    {
        $config = $project->openclawConfig;

        return response()->json([
            'config' => $config ? [
                'soul_content' => $config->soul_content,
                'tools' => $config->tools ?? [],
                'a2a_agents' => $config->a2a_agents ?? [],
            ] : null,
        ]);
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'soul_content' => 'nullable|string|max:50000',
            'tools' => 'nullable|array',
            'tools.*.name' => 'required|string',
            'tools.*.description' => 'nullable|string',
            'tools.*.instructions' => 'nullable|string',
            'tools.*.enabled' => 'boolean',
            'tools.*.api_key_env' => 'nullable|string',
            'tools.*.env' => 'nullable|array',
            'tools.*.config' => 'nullable|array',
            'a2a_agents' => 'nullable|array',
            'a2a_agents.*.name' => 'required|string',
            'a2a_agents.*.url' => 'required|string',
            'a2a_agents.*.description' => 'nullable|string',
            'a2a_agents.*.skills' => 'nullable|array',
        ]);

        $config = OpenClawConfig::updateOrCreate(
            ['project_id' => $project->id],
            $validated,
        );

        return response()->json([
            'config' => [
                'soul_content' => $config->soul_content,
                'tools' => $config->tools ?? [],
                'a2a_agents' => $config->a2a_agents ?? [],
            ],
            'message' => 'OpenClaw config saved.',
        ]);
    }
}
