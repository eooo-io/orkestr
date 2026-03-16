<?php

namespace App\Http\Controllers;

use App\Models\DelegationConfig;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DelegationConfigController extends Controller
{
    /**
     * GET /api/projects/{project}/delegation-configs
     */
    public function index(Project $project): JsonResponse
    {
        $configs = DelegationConfig::where('project_id', $project->id)
            ->with(['sourceAgent', 'targetAgent'])
            ->get();

        return response()->json(['data' => $configs]);
    }

    /**
     * PUT /api/projects/{project}/delegation-configs
     *
     * Upsert a delegation config for a source→target edge.
     */
    public function upsert(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'source_agent_id' => 'required|integer|exists:agents,id',
            'target_agent_id' => 'nullable|integer|exists:agents,id',
            'target_a2a_agent_id' => 'nullable|integer',
            'trigger_condition' => 'nullable|string|max:2000',
            'pass_conversation_history' => 'nullable|boolean',
            'pass_agent_memory' => 'nullable|boolean',
            'pass_available_tools' => 'nullable|boolean',
            'custom_context' => 'nullable|array',
            'return_behavior' => 'nullable|string|in:report_back,fire_and_forget,chain_forward',
        ]);

        // Must have at least one target
        if (empty($validated['target_agent_id']) && empty($validated['target_a2a_agent_id'])) {
            return response()->json([
                'message' => 'Either target_agent_id or target_a2a_agent_id is required.',
            ], 422);
        }

        $uniqueKeys = ['project_id' => $project->id, 'source_agent_id' => $validated['source_agent_id']];

        if (! empty($validated['target_agent_id'])) {
            $uniqueKeys['target_agent_id'] = $validated['target_agent_id'];
        } else {
            $uniqueKeys['target_a2a_agent_id'] = $validated['target_a2a_agent_id'];
        }

        $config = DelegationConfig::updateOrCreate($uniqueKeys, $validated);
        $config->load(['sourceAgent', 'targetAgent']);

        return response()->json(['data' => $config]);
    }

    /**
     * DELETE /api/delegation-configs/{delegationConfig}
     */
    public function destroy(DelegationConfig $delegationConfig): JsonResponse
    {
        $delegationConfig->delete();

        return response()->json(['message' => 'Delegation config deleted']);
    }
}
