<?php

namespace App\Services;

use App\Models\Workflow;
use App\Models\WorkflowStep;

class WorkflowContextService
{
    private array $contextBus = [];

    public function initialize(Workflow $workflow, array $initialContext = []): void
    {
        $this->contextBus = array_merge([
            '_workflow_id' => $workflow->id,
            '_workflow_name' => $workflow->name,
            '_started_at' => now()->toIso8601String(),
        ], $initialContext);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->contextBus[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->contextBus[$key] = $value;
    }

    public function merge(array $data): void
    {
        $this->contextBus = array_merge($this->contextBus, $data);
    }

    public function all(): array
    {
        return $this->contextBus;
    }

    public function clear(): void
    {
        $this->contextBus = [];
    }

    public function setStepOutput(WorkflowStep $step, mixed $output): void
    {
        $this->contextBus["_step_{$step->id}_output"] = $output;
        $this->contextBus["_step_{$step->uuid}_output"] = $output;

        // Also set by step name for easy access
        $key = str_replace([' ', '-'], '_', strtolower($step->name));
        $this->contextBus["output_{$key}"] = $output;
    }

    public function getStepOutput(WorkflowStep $step): mixed
    {
        return $this->contextBus["_step_{$step->id}_output"] ?? null;
    }

    public function resolveMapping(array $mapping): array
    {
        $resolved = [];

        foreach ($mapping as $targetKey => $sourceExpression) {
            $resolved[$targetKey] = $this->resolveExpression($sourceExpression);
        }

        return $resolved;
    }

    public function resolveExpression(string $expression): mixed
    {
        // Simple dot-notation access: "key" or "step_name.field"
        if (str_contains($expression, '.')) {
            $parts = explode('.', $expression, 2);
            $root = $this->contextBus[$parts[0]] ?? null;

            if (is_array($root) && isset($root[$parts[1]])) {
                return $root[$parts[1]];
            }

            return null;
        }

        return $this->contextBus[$expression] ?? null;
    }

    public function validateAgainstSchema(array $schema): array
    {
        $errors = [];

        foreach ($schema as $field => $rules) {
            $required = $rules['required'] ?? false;
            $type = $rules['type'] ?? null;

            if ($required && ! array_key_exists($field, $this->contextBus)) {
                $errors[] = "Required context field \"{$field}\" is missing.";
                continue;
            }

            if ($type && array_key_exists($field, $this->contextBus)) {
                $value = $this->contextBus[$field];
                $actualType = gettype($value);

                $typeMap = [
                    'string' => 'string',
                    'integer' => 'integer',
                    'number' => ['integer', 'double'],
                    'boolean' => 'boolean',
                    'array' => 'array',
                    'object' => 'array',
                ];

                $expected = $typeMap[$type] ?? $type;
                $validTypes = is_array($expected) ? $expected : [$expected];

                if (! in_array($actualType, $validTypes)) {
                    $errors[] = "Context field \"{$field}\" must be {$type}, got {$actualType}.";
                }
            }
        }

        return $errors;
    }
}
