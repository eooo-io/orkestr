<?php

namespace App\Services;

use App\Models\Workflow;
use App\Models\WorkflowStep;

class DelegationChainResolver
{
    private const MAX_DEPTH = 10;

    public function __construct(
        private WorkflowConditionEvaluator $evaluator,
    ) {}

    public function resolve(Workflow $workflow, WorkflowStep $fromStep): array
    {
        $workflow->loadMissing(['steps', 'edges']);

        $chain = [];
        $visited = [];

        $this->buildChain($fromStep, $workflow, $chain, $visited, 0);

        return $chain;
    }

    private function buildChain(WorkflowStep $step, Workflow $workflow, array &$chain, array &$visited, int $depth): void
    {
        if ($depth >= self::MAX_DEPTH) {
            return;
        }

        if (in_array($step->id, $visited)) {
            return;
        }

        $visited[] = $step->id;

        $chain[] = [
            'step_id' => $step->id,
            'step_uuid' => $step->uuid,
            'step_name' => $step->name,
            'step_type' => $step->type,
            'agent_id' => $step->agent_id,
            'depth' => $depth,
        ];

        if ($step->isTerminal()) {
            return;
        }

        // Get outgoing edges
        $outgoing = $workflow->edges
            ->where('source_step_id', $step->id)
            ->sortByDesc('priority')
            ->values();

        if ($outgoing->isEmpty()) {
            return;
        }

        // For condition nodes, evaluate and follow the matching edge
        if ($step->isCondition()) {
            $selected = $this->evaluator->selectEdge($outgoing->all());
            if ($selected) {
                $nextStep = $workflow->steps->firstWhere('id', $selected->target_step_id);
                if ($nextStep) {
                    $this->buildChain($nextStep, $workflow, $chain, $visited, $depth + 1);
                }
            }

            return;
        }

        // For parallel splits, follow all branches
        if ($step->type === 'parallel_split') {
            foreach ($outgoing as $edge) {
                $nextStep = $workflow->steps->firstWhere('id', $edge->target_step_id);
                if ($nextStep) {
                    $this->buildChain($nextStep, $workflow, $chain, $visited, $depth + 1);
                }
            }

            return;
        }

        // Default: follow first matching edge
        foreach ($outgoing as $edge) {
            if ($this->evaluator->evaluate($edge)) {
                $nextStep = $workflow->steps->firstWhere('id', $edge->target_step_id);
                if ($nextStep) {
                    $this->buildChain($nextStep, $workflow, $chain, $visited, $depth + 1);
                }
                break;
            }
        }
    }

    public function getAgentChain(Workflow $workflow, WorkflowStep $fromStep): array
    {
        $fullChain = $this->resolve($workflow, $fromStep);

        return array_values(array_filter($fullChain, fn ($entry) => $entry['step_type'] === 'agent'));
    }

    public function getDelegationTree(Workflow $workflow): array
    {
        $workflow->loadMissing(['steps', 'edges']);

        $startStep = $workflow->steps->firstWhere('type', 'start');
        if (! $startStep) {
            return [];
        }

        return $this->resolve($workflow, $startStep);
    }
}
