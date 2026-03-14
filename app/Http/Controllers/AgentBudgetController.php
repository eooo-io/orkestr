<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Services\AuditLogger;
use App\Services\Execution\Guards\BudgetGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentBudgetController extends Controller
{
    public function __construct(
        private BudgetGuard $budgetGuard,
    ) {}

    /**
     * GET /api/agents/{agent}/budget-status
     */
    public function status(Agent $agent): JsonResponse
    {
        $budgetStatus = $this->budgetGuard->getAgentBudgetStatus($agent);

        return response()->json([
            'data' => $budgetStatus,
        ]);
    }

    /**
     * PUT /api/agents/{agent}/budget
     */
    public function update(Request $request, Agent $agent): JsonResponse
    {
        $validated = $request->validate([
            'budget_limit_usd' => 'nullable|numeric|min:0|max:999999.9999',
            'daily_budget_limit_usd' => 'nullable|numeric|min:0|max:999999.9999',
        ]);

        $agent->update($validated);

        AuditLogger::log('agent.updated', "Agent '{$agent->name}' budget limits updated", [
            'agent_id' => $agent->id,
            'budget_limit_usd' => $validated['budget_limit_usd'] ?? null,
            'daily_budget_limit_usd' => $validated['daily_budget_limit_usd'] ?? null,
        ], $agent->id);

        return response()->json([
            'data' => [
                'budget_limit_usd' => $agent->budget_limit_usd,
                'daily_budget_limit_usd' => $agent->daily_budget_limit_usd,
                'status' => $this->budgetGuard->getAgentBudgetStatus($agent),
            ],
        ]);
    }
}
