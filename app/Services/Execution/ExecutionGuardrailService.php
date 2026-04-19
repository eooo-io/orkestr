<?php

namespace App\Services\Execution;

use App\Models\Agent;
use App\Models\ExecutionRun;
use App\Models\ExecutionStep;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\Project;

/**
 * Runtime guardrails for agent execution: catches infinite loops, enforces
 * per-run turn caps, and halts runs when token/cost budgets are exhausted.
 *
 * Returns halt reasons (strings) from the `check*` methods so the caller can
 * decide when to break out of the loop — the actual halt state + notification
 * is centralized in `halt()`.
 */
class ExecutionGuardrailService
{
    public const REASON_LOOP_DETECTED = 'loop_detected';
    public const REASON_TURN_CAP_EXCEEDED = 'turn_cap_exceeded';
    public const REASON_BUDGET_TOKEN_EXCEEDED = 'budget_token_exceeded';
    public const REASON_BUDGET_COST_EXCEEDED = 'budget_cost_exceeded';

    /**
     * Default window: consider the last N "act" steps; a repeat count above
     * `$threshold` within that window fires the loop detector. Tight enough
     * to catch death-spirals, loose enough that legitimate retries pass.
     */
    public const LOOP_WINDOW_STEPS = 6;
    public const LOOP_REPEAT_THRESHOLD = 3;

    /**
     * Hash a single tool-call signature for repetition counting. We hash the
     * input too — repeating the same action with different inputs is fine.
     */
    public function signatureFor(int $agentId, string $tool, array $input): string
    {
        $normalized = json_encode($input, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        return hash('xxh128', "{$agentId}|{$tool}|{$normalized}");
    }

    /**
     * Detect a loop by looking at the last N act-phase steps and counting how
     * many contain this exact (agent, tool, input) tool-call signature. The
     * step's `input` column holds `{tool_calls: [{name, input}, …]}` so we
     * flatten across calls. Returns the halt reason if a loop is detected.
     */
    public function detectLoop(ExecutionRun $run, int $agentId, string $tool, array $input): ?string
    {
        $signature = $this->signatureFor($agentId, $tool, $input);

        $recentActSteps = $run->steps()
            ->where('phase', 'act')
            ->orderByDesc('step_number')
            ->limit(self::LOOP_WINDOW_STEPS)
            ->get();

        $repeats = 1; // the call we're about to make
        foreach ($recentActSteps as $step) {
            $stepInput = is_array($step->input) ? $step->input : [];
            $toolCalls = $stepInput['tool_calls'] ?? [];
            if (! is_array($toolCalls)) continue;

            foreach ($toolCalls as $tc) {
                if (! is_array($tc)) continue;
                $stepTool = $tc['name'] ?? null;
                $stepArgs = $tc['input'] ?? [];
                if ($stepTool === null) continue;

                if ($this->signatureFor($agentId, $stepTool, $stepArgs) === $signature) {
                    $repeats++;
                }
            }
        }

        return $repeats > self::LOOP_REPEAT_THRESHOLD ? self::REASON_LOOP_DETECTED : null;
    }

    /**
     * Enforce the org-level turn cap. Counts against `$iteration` (1-indexed).
     */
    public function checkTurnCap(ExecutionRun $run, int $iteration): ?string
    {
        $cap = $this->resolveTurnCap($run);

        return $iteration > $cap ? self::REASON_TURN_CAP_EXCEEDED : null;
    }

    /**
     * Check token + cost budget against the run's accumulated usage.
     * Called after each cost-accumulating step.
     */
    public function checkBudget(ExecutionRun $run): ?string
    {
        $tokenBudget = $this->resolveTokenBudget($run);
        $costBudgetMicrocents = $this->resolveCostBudgetMicrocents($run);

        if ($tokenBudget !== null && $run->total_tokens >= $tokenBudget) {
            return self::REASON_BUDGET_TOKEN_EXCEEDED;
        }

        if ($costBudgetMicrocents !== null && $run->total_cost_microcents >= $costBudgetMicrocents) {
            return self::REASON_BUDGET_COST_EXCEEDED;
        }

        return null;
    }

    /**
     * Transition a run to halted state, optionally tagged with the offending step,
     * and notify the owner.
     */
    public function halt(ExecutionRun $run, string $reason, ?ExecutionStep $step = null): void
    {
        $run->update([
            'status' => 'halted_guardrail',
            'halt_reason' => $reason,
            'halt_step_id' => $step?->id,
            'completed_at' => now(),
            'total_duration_ms' => $run->started_at
                ? (int) now()->diffInMilliseconds($run->started_at)
                : 0,
        ]);

        $this->notifyOwner($run, $reason);
    }

    /**
     * Resolve turn cap from org config. Falls back to a sensible default if
     * no org is linked.
     */
    public function resolveTurnCap(ExecutionRun $run): int
    {
        $project = $run->project ?? Project::find($run->project_id);
        $org = $project?->organization_id ? Organization::find($project->organization_id) : null;

        return (int) ($org?->max_agent_turns_per_run ?? 40);
    }

    /**
     * Resolve the applicable token budget for this run. Precedence:
     * per-run override (on ExecutionRun) → agent → org default.
     */
    public function resolveTokenBudget(ExecutionRun $run): ?int
    {
        if ($run->token_budget !== null) {
            return (int) $run->token_budget;
        }

        $agent = $run->agent ?? Agent::find($run->agent_id);
        if ($agent?->run_token_budget !== null) {
            return (int) $agent->run_token_budget;
        }

        $project = $run->project ?? Project::find($run->project_id);
        $org = $project?->organization_id ? Organization::find($project->organization_id) : null;

        return $org?->default_run_token_budget ? (int) $org->default_run_token_budget : null;
    }

    /**
     * Cost budget converted to microcents (storage unit on ExecutionRun). Precedence
     * matches `resolveTokenBudget`.
     */
    public function resolveCostBudgetMicrocents(ExecutionRun $run): ?int
    {
        if ($run->cost_budget_microcents !== null) {
            return (int) $run->cost_budget_microcents;
        }

        $agent = $run->agent ?? Agent::find($run->agent_id);
        if ($agent?->run_cost_budget_usd !== null) {
            return (int) round(((float) $agent->run_cost_budget_usd) * 1_000_000);
        }

        $project = $run->project ?? Project::find($run->project_id);
        $org = $project?->organization_id ? Organization::find($project->organization_id) : null;

        if ($org?->default_run_cost_budget_usd) {
            return (int) round(((float) $org->default_run_cost_budget_usd) * 1_000_000);
        }

        return null;
    }

    protected function notifyOwner(ExecutionRun $run, string $reason): void
    {
        if (! $run->created_by) {
            return;
        }

        $title = match ($reason) {
            self::REASON_LOOP_DETECTED => 'Agent run halted: loop detected',
            self::REASON_TURN_CAP_EXCEEDED => 'Agent run halted: turn cap reached',
            self::REASON_BUDGET_TOKEN_EXCEEDED => 'Agent run halted: token budget exhausted',
            self::REASON_BUDGET_COST_EXCEEDED => 'Agent run halted: cost budget exhausted',
            default => 'Agent run halted by guardrail',
        };

        $project = $run->project ?? Project::find($run->project_id);

        Notification::create([
            'user_id' => $run->created_by,
            'organization_id' => $project?->organization_id,
            'type' => 'agent.halt',
            'title' => $title,
            'body' => "Run #{$run->id} was halted ({$reason}). Review the execution trace.",
            'data' => [
                'run_id' => $run->id,
                'halt_reason' => $reason,
                'total_tokens' => $run->total_tokens,
                'total_cost_microcents' => $run->total_cost_microcents,
            ],
            'created_at' => now(),
        ]);
    }
}
