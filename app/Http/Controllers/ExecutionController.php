<?php

namespace App\Http\Controllers;

use App\Http\Resources\ExecutionRunResource;
use App\Models\Agent;
use App\Models\ExecutionRun;
use App\Models\ExecutionStep;
use App\Models\Project;
use App\Services\AuditLogger;
use App\Services\Execution\AgentExecutionService;
use App\Services\Execution\CostCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExecutionController extends Controller
{
    public function __construct(
        private AgentExecutionService $executionService,
        private CostCalculator $costCalculator,
    ) {}

    /**
     * POST /api/projects/{project}/agents/{agent}/execute
     *
     * Start an agent execution run.
     */
    public function execute(Request $request, Project $project, Agent $agent)
    {
        $validated = $request->validate([
            'input' => 'nullable|array',
            'input.message' => 'nullable|string',
            'input.goal' => 'nullable|string',
            'config' => 'nullable|array',
            'config.max_tokens' => 'nullable|integer|min:1|max:32000',
            'token_budget' => 'nullable|integer|min:1',
            'cost_budget_usd' => 'nullable|numeric|min:0',
        ]);

        $run = $this->executionService->execute(
            project: $project,
            agent: $agent,
            input: $validated['input'] ?? [],
            config: $validated['config'] ?? [],
            createdBy: $request->user()?->id,
        );

        if (isset($validated['token_budget']) || isset($validated['cost_budget_usd'])) {
            $run->update([
                'token_budget' => $validated['token_budget'] ?? null,
                'cost_budget_microcents' => isset($validated['cost_budget_usd'])
                    ? (int) round(((float) $validated['cost_budget_usd']) * 1_000_000)
                    : null,
            ]);
        }

        $run->load('steps');

        return (new ExecutionRunResource($run))->response()->setStatusCode(201);
    }

    /**
     * GET /api/projects/{project}/runs
     *
     * List execution runs for a project.
     */
    public function index(Request $request, Project $project)
    {
        $query = $project->executionRuns()
            ->with('agent')
            ->withCount('steps');

        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->has('agent_id')) {
            $query->where('agent_id', $request->query('agent_id'));
        }

        $runs = $query->orderByDesc('created_at')->limit(50)->get();

        return ExecutionRunResource::collection($runs)->response();
    }

    /**
     * GET /api/runs/{run}
     *
     * Show a single execution run with its steps.
     */
    public function show(ExecutionRun $run)
    {
        $run->load(['steps', 'agent']);

        return (new ExecutionRunResource($run))->response();
    }

    /**
     * POST /api/runs/{run}/cancel
     *
     * Cancel a running execution.
     */
    public function cancel(ExecutionRun $run): JsonResponse
    {
        if ($run->isFinished()) {
            return response()->json(['error' => 'Run is already finished.'], 422);
        }

        $run->markCancelled();

        return response()->json(['status' => 'cancelled']);
    }

    /**
     * POST /api/runs/{run}/steps/{step}/approve
     *
     * Approve a pending step.
     */
    public function approveStep(Request $request, ExecutionRun $run, ExecutionStep $step): JsonResponse
    {
        if (! $step->isPendingApproval()) {
            return response()->json(['error' => 'Step is not pending approval.'], 422);
        }

        if ((int) $step->execution_run_id !== (int) $run->id) {
            return response()->json(['error' => 'Step does not belong to this run.'], 404);
        }

        $validated = $request->validate([
            'note' => 'nullable|string|max:1000',
        ]);

        $userId = $request->user()->id;
        $step->approve($userId, $validated['note'] ?? null);

        AuditLogger::log('tool.approved', "Step #{$step->step_number} approved for run #{$run->id}", [
            'run_id' => $run->id,
            'step_id' => $step->id,
            'tool_calls' => $step->tool_calls,
        ], $run->agent_id, $run->project_id);

        return response()->json([
            'message' => 'Step approved.',
            'step_id' => $step->id,
            'status' => 'approved',
        ]);
    }

    /**
     * POST /api/runs/{run}/steps/{step}/reject
     *
     * Reject a pending step.
     */
    public function rejectStep(Request $request, ExecutionRun $run, ExecutionStep $step): JsonResponse
    {
        if (! $step->isPendingApproval()) {
            return response()->json(['error' => 'Step is not pending approval.'], 422);
        }

        if ((int) $step->execution_run_id !== (int) $run->id) {
            return response()->json(['error' => 'Step does not belong to this run.'], 404);
        }

        $validated = $request->validate([
            'note' => 'nullable|string|max:1000',
        ]);

        $userId = $request->user()->id;
        $step->reject($userId, $validated['note'] ?? null);

        // Mark the run as failed since the step was rejected
        $run->markFailed('Tool call rejected by user');

        AuditLogger::log('tool.rejected', "Step #{$step->step_number} rejected for run #{$run->id}", [
            'run_id' => $run->id,
            'step_id' => $step->id,
            'tool_calls' => $step->tool_calls,
            'note' => $validated['note'] ?? null,
        ], $run->agent_id, $run->project_id);

        return response()->json([
            'message' => 'Step rejected. Run has been marked as failed.',
            'step_id' => $step->id,
            'status' => 'rejected',
        ]);
    }

    /**
     * POST /api/runs/{run}/resume
     *
     * Resume execution after step approval.
     */
    public function resume(ExecutionRun $run): JsonResponse
    {
        if (! $run->isAwaitingApproval()) {
            return response()->json(['error' => 'Run is not awaiting approval.'], 422);
        }

        // Verify that the pending step has been approved
        $pendingStep = $run->steps()
            ->where('requires_approval', true)
            ->where('phase', 'act')
            ->orderByDesc('step_number')
            ->first();

        if ($pendingStep && $pendingStep->status !== 'approved') {
            return response()->json(['error' => 'Pending step has not been approved yet.'], 422);
        }

        $run = $this->executionService->resumeExecution($run);

        return (new ExecutionRunResource($run))->response();
    }

    /**
     * GET /api/projects/{project}/runs/stats
     *
     * Get aggregate execution statistics for a project.
     */
    public function stats(Project $project): JsonResponse
    {
        $runs = $project->executionRuns()->with('agent')->get();

        $stats = $this->costCalculator->aggregateStats($runs);

        // Add success rate
        $completed = $runs->where('status', 'completed')->count();
        $failed = $runs->where('status', 'failed')->count();
        $stats['success_rate'] = $runs->count() > 0
            ? round($completed / $runs->count() * 100, 1)
            : 0;
        $stats['completed_count'] = $completed;
        $stats['failed_count'] = $failed;

        return response()->json($stats);
    }
}
