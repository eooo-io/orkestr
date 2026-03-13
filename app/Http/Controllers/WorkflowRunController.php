<?php

namespace App\Http\Controllers;

use App\Http\Resources\WorkflowRunResource;
use App\Models\Project;
use App\Models\Workflow;
use App\Models\WorkflowRun;
use App\Models\WorkflowRunStep;
use App\Services\Execution\WorkflowExecutionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkflowRunController extends Controller
{
    public function __construct(
        private WorkflowExecutionService $executor,
    ) {}

    /**
     * POST /api/projects/{project}/workflows/{workflow}/execute
     */
    public function execute(Request $request, Project $project, Workflow $workflow)
    {
        if ($workflow->project_id !== $project->id) {
            abort(404);
        }

        $validated = $request->validate([
            'input' => 'nullable|array',
        ]);

        $run = $this->executor->execute(
            workflow: $workflow,
            input: $validated['input'] ?? [],
            createdBy: $request->user()?->id,
        );

        $run->load('runSteps');

        return (new WorkflowRunResource($run))->response()->setStatusCode(201);
    }

    /**
     * GET /api/projects/{project}/workflow-runs
     */
    public function index(Project $project)
    {
        $runs = WorkflowRun::where('project_id', $project->id)
            ->withCount('runSteps')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return WorkflowRunResource::collection($runs)->response();
    }

    /**
     * GET /api/workflow-runs/{workflowRun}
     */
    public function show(WorkflowRun $workflowRun)
    {
        $workflowRun->load('runSteps');

        return (new WorkflowRunResource($workflowRun))->response();
    }

    /**
     * POST /api/workflow-runs/{workflowRun}/cancel
     */
    public function cancel(WorkflowRun $workflowRun): JsonResponse
    {
        if ($workflowRun->isFinished()) {
            return response()->json(['error' => 'Run is already finished.'], 422);
        }

        $workflowRun->markCancelled();

        return response()->json(['status' => 'cancelled']);
    }

    /**
     * POST /api/workflow-runs/{workflowRun}/steps/{workflowRunStep}/approve
     */
    public function approveCheckpoint(WorkflowRun $workflowRun, WorkflowRunStep $workflowRunStep)
    {
        if ($workflowRunStep->workflow_run_id !== $workflowRun->id) {
            abort(404);
        }

        $run = $this->executor->approveCheckpoint($workflowRun, $workflowRunStep);

        return (new WorkflowRunResource($run))->response();
    }

    /**
     * POST /api/workflow-runs/{workflowRun}/steps/{workflowRunStep}/reject
     */
    public function rejectCheckpoint(WorkflowRun $workflowRun, WorkflowRunStep $workflowRunStep)
    {
        if ($workflowRunStep->workflow_run_id !== $workflowRun->id) {
            abort(404);
        }

        $run = $this->executor->rejectCheckpoint($workflowRun, $workflowRunStep);

        return (new WorkflowRunResource($run))->response();
    }
}
