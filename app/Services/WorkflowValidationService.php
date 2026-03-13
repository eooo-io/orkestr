<?php

namespace App\Services;

use App\Models\Workflow;

class WorkflowValidationService
{
    public function validate(Workflow $workflow): array
    {
        $workflow->loadMissing(['steps', 'edges']);

        $errors = [];
        $warnings = [];

        $steps = $workflow->steps;
        $edges = $workflow->edges;

        // Must have at least one step
        if ($steps->isEmpty()) {
            $errors[] = 'Workflow must have at least one step.';

            return ['valid' => false, 'errors' => $errors, 'warnings' => $warnings];
        }

        // Check for start node
        $startSteps = $steps->where('type', 'start');
        if ($startSteps->isEmpty()) {
            $errors[] = 'Workflow must have a start node.';
        } elseif ($startSteps->count() > 1) {
            $errors[] = 'Workflow must have exactly one start node.';
        }

        // Check for end node
        $endSteps = $steps->where('type', 'end');
        if ($endSteps->isEmpty()) {
            $errors[] = 'Workflow must have at least one end node.';
        }

        // Agent steps must have an agent_id
        foreach ($steps->where('type', 'agent') as $step) {
            if (empty($step->agent_id)) {
                $errors[] = "Agent step \"{$step->name}\" must have an agent assigned.";
            }
        }

        // Check for cycles (topological sort)
        $cycleCheck = $this->detectCycles($steps, $edges);
        if ($cycleCheck) {
            $errors[] = 'Workflow contains a cycle: ' . $cycleCheck;
        }

        // Check for unreachable nodes (not reachable from start)
        if ($startSteps->isNotEmpty()) {
            $reachable = $this->findReachable($startSteps->first()->id, $edges);
            foreach ($steps as $step) {
                if ($step->type !== 'start' && ! in_array($step->id, $reachable)) {
                    $warnings[] = "Step \"{$step->name}\" is not reachable from the start node.";
                }
            }
        }

        // Check for dead-end nodes (non-end nodes with no outgoing edges)
        $stepIds = $steps->pluck('id')->toArray();
        $sourceIds = $edges->pluck('source_step_id')->unique()->toArray();
        foreach ($steps as $step) {
            if ($step->type !== 'end' && ! in_array($step->id, $sourceIds)) {
                $warnings[] = "Step \"{$step->name}\" has no outgoing edges (dead end).";
            }
        }

        // Parallel split must have multiple outgoing edges
        foreach ($steps->where('type', 'parallel_split') as $step) {
            $outgoing = $edges->where('source_step_id', $step->id)->count();
            if ($outgoing < 2) {
                $warnings[] = "Parallel split \"{$step->name}\" should have at least 2 outgoing edges.";
            }
        }

        // Parallel join must have multiple incoming edges
        foreach ($steps->where('type', 'parallel_join') as $step) {
            $incoming = $edges->where('target_step_id', $step->id)->count();
            if ($incoming < 2) {
                $warnings[] = "Parallel join \"{$step->name}\" should have at least 2 incoming edges.";
            }
        }

        // Check entry_step_id is valid
        if ($workflow->entry_step_id && ! $steps->contains('id', $workflow->entry_step_id)) {
            $errors[] = 'Entry step references a non-existent step.';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    private function detectCycles($steps, $edges): ?string
    {
        $adjacency = [];
        $stepNames = [];

        foreach ($steps as $step) {
            $adjacency[$step->id] = [];
            $stepNames[$step->id] = $step->name;
        }

        foreach ($edges as $edge) {
            if (isset($adjacency[$edge->source_step_id])) {
                $adjacency[$edge->source_step_id][] = $edge->target_step_id;
            }
        }

        $visited = [];
        $inStack = [];

        foreach (array_keys($adjacency) as $nodeId) {
            if ($this->hasCycleDfs($nodeId, $adjacency, $visited, $inStack, $stepNames, $cyclePath)) {
                return $cyclePath;
            }
        }

        return null;
    }

    private function hasCycleDfs(int $node, array &$adjacency, array &$visited, array &$inStack, array &$names, ?string &$cyclePath): bool
    {
        if (isset($inStack[$node])) {
            $cyclePath = $names[$node] . ' → ... → ' . $names[$node];

            return true;
        }
        if (isset($visited[$node])) {
            return false;
        }

        $visited[$node] = true;
        $inStack[$node] = true;

        foreach ($adjacency[$node] ?? [] as $neighbor) {
            if ($this->hasCycleDfs($neighbor, $adjacency, $visited, $inStack, $names, $cyclePath)) {
                return true;
            }
        }

        unset($inStack[$node]);

        return false;
    }

    private function findReachable(int $startId, $edges): array
    {
        $adjacency = [];
        foreach ($edges as $edge) {
            $adjacency[$edge->source_step_id][] = $edge->target_step_id;
        }

        $visited = [];
        $queue = [$startId];

        while (! empty($queue)) {
            $current = array_shift($queue);
            if (isset($visited[$current])) {
                continue;
            }
            $visited[$current] = true;

            foreach ($adjacency[$current] ?? [] as $neighbor) {
                if (! isset($visited[$neighbor])) {
                    $queue[] = $neighbor;
                }
            }
        }

        return array_keys($visited);
    }
}
