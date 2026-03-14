<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentToolScopeController extends Controller
{
    /**
     * GET /api/agents/{agent}/tool-scope
     */
    public function show(Agent $agent): JsonResponse
    {
        return response()->json([
            'data' => [
                'allowed_tools' => $agent->allowed_tools ?? [],
                'blocked_tools' => $agent->blocked_tools ?? [],
                'autonomy_level' => $agent->autonomy_level,
            ],
        ]);
    }

    /**
     * PUT /api/agents/{agent}/tool-scope
     */
    public function update(Request $request, Agent $agent): JsonResponse
    {
        $validated = $request->validate([
            'allowed_tools' => 'nullable|array',
            'allowed_tools.*' => 'string',
            'blocked_tools' => 'nullable|array',
            'blocked_tools.*' => 'string',
        ]);

        $agent->update([
            'allowed_tools' => $validated['allowed_tools'] ?? null,
            'blocked_tools' => $validated['blocked_tools'] ?? null,
        ]);

        AuditLogger::log('agent.updated', "Agent '{$agent->name}' tool scope updated", [
            'agent_id' => $agent->id,
            'allowed_tools' => $validated['allowed_tools'] ?? null,
            'blocked_tools' => $validated['blocked_tools'] ?? null,
        ], $agent->id);

        return response()->json([
            'data' => [
                'allowed_tools' => $agent->allowed_tools ?? [],
                'blocked_tools' => $agent->blocked_tools ?? [],
                'autonomy_level' => $agent->autonomy_level,
            ],
        ]);
    }
}
