<?php

namespace App\Services;

use App\Models\WorkflowEdge;

class WorkflowConditionEvaluator
{
    public function __construct(
        private WorkflowContextService $contextService,
    ) {}

    public function evaluate(WorkflowEdge $edge): bool
    {
        if (! $edge->hasCondition()) {
            return true;
        }

        return $this->evaluateExpression($edge->condition_expression);
    }

    public function evaluateExpression(string $expression): bool
    {
        $expression = trim($expression);

        // Boolean literals
        if (strtolower($expression) === 'true' || $expression === '1') {
            return true;
        }
        if (strtolower($expression) === 'false' || $expression === '0') {
            return false;
        }

        // Comparison operators
        foreach (['===', '!==', '==', '!=', '>=', '<=', '>', '<'] as $op) {
            if (str_contains($expression, $op)) {
                return $this->evaluateComparison($expression, $op);
            }
        }

        // Existence check: "key exists"
        if (str_ends_with($expression, ' exists')) {
            $key = trim(str_replace(' exists', '', $expression));

            return $this->contextService->get($key) !== null;
        }

        // Truthy check: just a context key
        $value = $this->contextService->resolveExpression($expression);

        return (bool) $value;
    }

    private function evaluateComparison(string $expression, string $operator): bool
    {
        $parts = array_map('trim', explode($operator, $expression, 2));

        if (count($parts) !== 2) {
            return false;
        }

        $left = $this->resolveValue($parts[0]);
        $right = $this->resolveValue($parts[1]);

        return match ($operator) {
            '===' => $left === $right,
            '!==' => $left !== $right,
            '==' => $left == $right,
            '!=' => $left != $right,
            '>' => $left > $right,
            '<' => $left < $right,
            '>=' => $left >= $right,
            '<=' => $left <= $right,
            default => false,
        };
    }

    private function resolveValue(string $raw): mixed
    {
        // Quoted string literal
        if (
            (str_starts_with($raw, '"') && str_ends_with($raw, '"'))
            || (str_starts_with($raw, "'") && str_ends_with($raw, "'"))
        ) {
            return substr($raw, 1, -1);
        }

        // Numeric literal
        if (is_numeric($raw)) {
            return str_contains($raw, '.') ? (float) $raw : (int) $raw;
        }

        // Boolean literal
        if (strtolower($raw) === 'true') {
            return true;
        }
        if (strtolower($raw) === 'false') {
            return false;
        }
        if (strtolower($raw) === 'null') {
            return null;
        }

        // Context reference
        return $this->contextService->resolveExpression($raw);
    }

    public function selectEdge(array $edges): ?WorkflowEdge
    {
        // Sort by priority (higher first), then evaluate in order
        usort($edges, fn ($a, $b) => $b->priority - $a->priority);

        foreach ($edges as $edge) {
            if ($this->evaluate($edge)) {
                return $edge;
            }
        }

        return null;
    }
}
