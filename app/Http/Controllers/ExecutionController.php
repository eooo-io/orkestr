<?php

namespace App\Http\Controllers;

use App\Http\Resources\ExecutionRunResource;
use App\Models\Agent;
use App\Models\ExecutionRun;
use App\Models\Project;
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
        ]);

        $run = $this->executionService->execute(
            project: $project,
            agent: $agent,
            input: $validated['input'] ?? [],
            config: $validated['config'] ?? [],
            createdBy: $request->user()?->id,
        );

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
