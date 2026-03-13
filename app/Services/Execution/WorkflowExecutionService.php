<?php

namespace App\Services\Execution;

use App\Models\Project;
use App\Models\Workflow;
use App\Models\WorkflowEdge;
use App\Models\WorkflowRun;
use App\Models\WorkflowRunStep;
use App\Models\WorkflowStep;
use App\Services\WorkflowConditionEvaluator;
use App\Services\WorkflowContextService;
use Illuminate\Support\Facades\Log;

class WorkflowExecutionService
{
    public function __construct(
        private AgentExecutionService $agentExecutor,
        private WorkflowContextService $contextService,
        private WorkflowConditionEvaluator $conditionEvaluator,
    ) {}

    /**
     * Execute a workflow from its entry step.
     */
    public function execute(Workflow $workflow, array $input = [], ?int $createdBy = null): WorkflowRun
    {
        $workflow->load(['steps', 'edges']);

        $run = WorkflowRun::create([
            'workflow_id' => $workflow->id,
            'project_id' => $workflow->project_id,
            'input' => $input,
            'created_by' => $createdBy,
        ]);

        $run->markRunning();

        // Initialize context
        $this->contextService->initialize($workflow, $input);

        try {
            $entryStep = $this->findEntryStep($workflow);
            $this->executeStep($run, $workflow, $entryStep);
        } catch (\Throwable $e) {
            $run->markFailed($e->getMessage());
            Log::error("Workflow execution failed: {$e->getMessage()}", [
                'run_id' => $run->id,
                'workflow' => $workflow->slug,
            ]);
        }

        return $run->fresh(['runSteps']);
    }

    /**
     * Resume a workflow paused at a checkpoint after approval.
     */
    public function approveCheckpoint(WorkflowRun $run, WorkflowRunStep $runStep): WorkflowRun
    {
        if (! $run->isWaitingCheckpoint()) {
            throw new \RuntimeException('Workflow is not waiting at a checkpoint.');
        }

        $runStep->markCompleted(['approved' => true, 'approved_at' => now()->toIso8601String()]);

        // Resume from the next step
        $run->update(['status' => 'running']);

        $workflow = $run->workflow;
        $workflow->load(['steps', 'edges']);

        // Restore context
        $this->contextService->initialize($workflow, $run->context_snapshot ?? $run->input ?? []);

        try {
            $nextSteps = $this->getNextSteps($workflow, $runStep->workflowStep);
            foreach ($nextSteps as $nextStep) {
                $this->executeStep($run, $workflow, $nextStep);
            }
        } catch (\Throwable $e) {
            $run->markFailed($e->getMessage());
        }

        return $run->fresh(['runSteps']);
    }

    /**
     * Reject a checkpoint, halting the workflow.
     */
    public function rejectCheckpoint(WorkflowRun $run, WorkflowRunStep $runStep): WorkflowRun
    {
        $runStep->markFailed('Checkpoint rejected');
        $run->markFailed('Checkpoint rejected by user');

        return $run->fresh(['runSteps']);
    }

    private function executeStep(WorkflowRun $run, Workflow $workflow, WorkflowStep $step): void
    {
        // Check for cancellation
        if ($run->fresh()->isFinished()) {
            return;
        }

        // Create run step record
        $runStep = WorkflowRunStep::create([
            'workflow_run_id' => $run->id,
            'workflow_step_id' => $step->id,
            'input' => $this->contextService->all(),
        ]);

        $run->update(['current_step_id' => $step->id]);
        $runStep->markRunning();

        try {
            match ($step->type) {
                'start' => $this->handleStart($run, $workflow, $step, $runStep),
                'end' => $this->handleEnd($run, $step, $runStep),
                'agent' => $this->handleAgent($run, $workflow, $step, $runStep),
                'checkpoint' => $this->handleCheckpoint($run, $step, $runStep),
                'condition' => $this->handleCondition($run, $workflow, $step, $runStep),
                'parallel_split' => $this->handleParallelSplit($run, $workflow, $step, $runStep),
                'parallel_join' => $this->handleParallelJoin($run, $workflow, $step, $runStep),
                default => throw new \RuntimeException("Unknown step type: {$step->type}"),
            };
        } catch (\Throwable $e) {
            $runStep->markFailed($e->getMessage());
            throw $e;
        }
    }

    private function handleStart(WorkflowRun $run, Workflow $workflow, WorkflowStep $step, WorkflowRunStep $runStep): void
    {
        $runStep->markCompleted(['started' => true]);
        $this->advanceToNextSteps($run, $workflow, $step);
    }

    private function handleEnd(WorkflowRun $run, WorkflowStep $step, WorkflowRunStep $runStep): void
    {
        $runStep->markCompleted(['finished' => true]);
        $run->markCompleted($this->contextService->all());
    }

    private function handleAgent(WorkflowRun $run, Workflow $workflow, WorkflowStep $step, WorkflowRunStep $runStep): void
    {
        if (! $step->agent_id) {
            throw new \RuntimeException("Agent step '{$step->name}' has no agent assigned.");
        }

        $project = Project::find($run->project_id);
        $agent = $step->agent;

        // Execute agent with current context as input
        $executionRun = $this->agentExecutor->execute(
            project: $project,
            agent: $agent,
            input: array_merge(
                $this->contextService->all(),
                ['step_name' => $step->name],
            ),
            config: $step->config ?? [],
            createdBy: $run->created_by,
        );

        $runStep->update(['execution_run_id' => $executionRun->id]);

        if ($executionRun->isFailed()) {
            $runStep->markFailed($executionRun->error ?? 'Agent execution failed');
            throw new \RuntimeException("Agent step '{$step->name}' failed: {$executionRun->error}");
        }

        // Merge agent output into context
        if ($executionRun->output) {
            $this->contextService->set("steps.{$step->name}", $executionRun->output);
        }

        $runStep->markCompleted($executionRun->output);

        // Snapshot context for checkpoint resume
        $run->update(['context_snapshot' => $this->contextService->all()]);

        $this->advanceToNextSteps($run, $workflow, $step);
    }

    private function handleCheckpoint(WorkflowRun $run, WorkflowStep $step, WorkflowRunStep $runStep): void
    {
        $runStep->markWaitingApproval();
        $run->markWaitingCheckpoint($step->id);
        // Execution pauses here — resumed via approveCheckpoint()
    }

    private function handleCondition(WorkflowRun $run, Workflow $workflow, WorkflowStep $step, WorkflowRunStep $runStep): void
    {
        $context = $this->contextService->all();
        $edges = $workflow->edges->where('source_step_id', $step->id)->sortBy('priority');

        $selectedEdge = $this->conditionEvaluator->selectEdge($edges->toArray(), $context);

        if (! $selectedEdge) {
            $runStep->markFailed('No matching condition edge');
            throw new \RuntimeException("Condition step '{$step->name}' has no matching edge.");
        }

        $runStep->markCompleted(['selected_edge' => $selectedEdge['label'] ?? $selectedEdge['id'] ?? 'default']);

        $nextStep = $workflow->steps->firstWhere('id', $selectedEdge['target_step_id']);
        if ($nextStep) {
            $this->executeStep($run, $workflow, $nextStep);
        }
    }

    private function handleParallelSplit(WorkflowRun $run, Workflow $workflow, WorkflowStep $step, WorkflowRunStep $runStep): void
    {
        $runStep->markCompleted(['split' => true]);

        // Execute all outgoing edges in sequence (true parallelism would need queue/async)
        $nextSteps = $this->getNextSteps($workflow, $step);
        foreach ($nextSteps as $nextStep) {
            $this->executeStep($run, $workflow, $nextStep);
        }
    }

    private function handleParallelJoin(WorkflowRun $run, Workflow $workflow, WorkflowStep $step, WorkflowRunStep $runStep): void
    {
        // Check if all incoming steps are completed
        $incomingEdges = $workflow->edges->where('target_step_id', $step->id);
        $incomingStepIds = $incomingEdges->pluck('source_step_id');

        $completedCount = $run->runSteps()
            ->whereIn('workflow_step_id', $incomingStepIds)
            ->where('status', 'completed')
            ->count();

        if ($completedCount < $incomingStepIds->count()) {
            // Not all parallel branches complete — skip for now
            $runStep->markSkipped();

            return;
        }

        $runStep->markCompleted(['joined' => true]);
        $this->advanceToNextSteps($run, $workflow, $step);
    }

    private function advanceToNextSteps(WorkflowRun $run, Workflow $workflow, WorkflowStep $currentStep): void
    {
        $nextSteps = $this->getNextSteps($workflow, $currentStep);

        foreach ($nextSteps as $nextStep) {
            $this->executeStep($run, $workflow, $nextStep);
        }
    }

    /**
     * @return WorkflowStep[]
     */
    private function getNextSteps(Workflow $workflow, WorkflowStep $step): array
    {
        $edges = $workflow->edges->where('source_step_id', $step->id)->sortBy('priority');

        // For condition steps, edge selection is handled in handleCondition
        $nextSteps = [];
        foreach ($edges as $edge) {
            // If edge has a condition, evaluate it
            if ($edge->condition_expression) {
                $context = $this->contextService->all();
                if (! $this->conditionEvaluator->evaluate($edge->condition_expression, $context)) {
                    continue;
                }
            }

            $nextStep = $workflow->steps->firstWhere('id', $edge->target_step_id);
            if ($nextStep) {
                $nextSteps[] = $nextStep;
            }
        }

        return $nextSteps;
    }

    private function findEntryStep(Workflow $workflow): WorkflowStep
    {
        // Use configured entry step, or find the 'start' step
        if ($workflow->entry_step_id) {
            $step = $workflow->steps->firstWhere('id', $workflow->entry_step_id);
            if ($step) {
                return $step;
            }
        }

        $startStep = $workflow->steps->firstWhere('type', 'start');
        if ($startStep) {
            return $startStep;
        }

        throw new \RuntimeException('Workflow has no entry point (no start step found).');
    }
}
