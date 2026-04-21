# Runtime Safety Guardrails

Agents will find creative ways to burn your budget. Runtime guardrails catch the three most common patterns — infinite loops, runaway iteration counts, and unbounded cost — and halt the run before the damage compounds.

This is distinct from the [content guardrails](./guardrails) (PII, tool allowlists, output scanning). Runtime guardrails protect the machine; content guardrails protect the output.

## The halt mechanism

When any guardrail fires, the run transitions to `status=halted_guardrail` with:

- `halt_reason` — one of `loop_detected`, `turn_cap_exceeded`, `budget_token_exceeded`, `budget_cost_exceeded`
- `halt_step_id` — the step that triggered it (when applicable)
- Owner notification with `type=agent.halt` in the `notifications` table

The `ExecutionPlayground` surfaces a red banner above the run header with the human-readable reason.

## Loop detection

The "ant death spiral" case: an agent repeats the same tool call with the same input over and over, waiting for the outcome to change. It never does.

`ExecutionGuardrailService::detectLoop` hashes every tool-call signature as `xxh128((agent_id, tool_name, normalized_input))` and counts how many times the signature appears in the last **6 act steps**. If the count exceeds **3**, the run halts with `loop_detected`.

Tight enough to catch genuine loops, loose enough that legitimate retries (which typically differ in input) pass cleanly.

```php
if ($reason = $guardrails->detectLoop($run, $agent->id, $toolName, $toolInput)) {
    $guardrails->halt($run, $reason, $actStep);
    return;
}
```

## Turn caps

Per-agent `max_iterations` already existed, but it's easy to set it high on an individual agent. The turn cap is a **org-level ceiling** that no single run can exceed:

```php
// organizations.max_agent_turns_per_run, default 40
```

Enforced at the top of each iteration:

```php
if ($reason = $guardrails->checkTurnCap($run, $iteration + 1)) {
    $guardrails->halt($run, $reason);
    return;
}
```

## Per-run token + cost budgets

Budgets apply to a single run's accumulated `total_tokens` and `total_cost_microcents`. Precedence:

1. **Per-run override** — passed in the execute request body, written to `execution_runs.token_budget` / `cost_budget_microcents`
2. **Per-agent** — `agents.run_token_budget` / `run_cost_budget_usd`
3. **Org default** — `organizations.default_run_token_budget` / `default_run_cost_budget_usd`

A `null` at a layer falls through to the next. If nothing is set at any layer, no budget applies.

### Setting a per-run budget

```
POST /api/projects/{project}/agents/{agent}/execute
{
  "input": { "message": "..." },
  "token_budget": 50000,
  "cost_budget_usd": 2.50
}
```

Costs are tracked in microcents on the run row: `1 USD = 1,000,000 microcents`.

### The per-run cumulative check

After every LLM call, `checkBudget` runs:

```php
if ($reason = $guardrails->checkBudget($freshRun)) {
    $guardrails->halt($freshRun, $reason);
    return;
}
```

`$reason` is `budget_token_exceeded` when `total_tokens >= token_budget`, and `budget_cost_exceeded` when `total_cost_microcents >= cost_budget_microcents`.

## This is not the per-agent cumulative budget

Orkestr has two separate budget concepts:

| Scope | Column | Enforced by |
|---|---|---|
| Per-run ceiling | `execution_runs.token_budget` / `cost_budget_microcents` (plus precedence fallbacks) | `ExecutionGuardrailService::checkBudget` |
| Per-agent cumulative (daily/monthly) | `agents.budget_limit_usd` / `daily_budget_limit_usd` | `BudgetEnforcer` / `BudgetGuard` |

The new runtime guardrails **add** to the cumulative budgets — they don't replace them. A run can be halted by either system.

## Live counters

`ExecutionRunResource` exposes budget state:

```json
{
  "total_tokens": 12500,
  "total_cost_microcents": 75000,
  "token_budget": 50000,
  "cost_budget_microcents": 2500000,
  "halt_reason": null,
  "halt_step_id": null
}
```

The playground header shows `12,500 / 50,000 tokens` when a budget is configured.

## Wiring

Guardrails hook into both execution paths:

- **`RunAgentJob`** — the queued path used for real runs
- **`AgentExecutionService`** — the synchronous path used by `ExecutionController::execute`

Both call `checkTurnCap` + `checkBudget` at the top of each iteration, and `detectLoop` before dispatching tool calls within the act phase.

## Notifications

When a run is halted, the owner receives a notification:

```json
{
  "type": "agent.halt",
  "title": "Agent run halted: loop detected",
  "body": "Run #142 was halted (loop_detected). Review the execution trace.",
  "data": {
    "run_id": 142,
    "halt_reason": "loop_detected",
    "total_tokens": 18300,
    "total_cost_microcents": 89200
  }
}
```

Surfaced in the bell icon + the notifications list at `/notifications`.

## Defaults recap

| Setting | Default | Where |
|---|---|---|
| Turn cap | 40 | `organizations.max_agent_turns_per_run` |
| Loop window | 6 act steps | `ExecutionGuardrailService::LOOP_WINDOW_STEPS` |
| Loop repeat threshold | 3 | `ExecutionGuardrailService::LOOP_REPEAT_THRESHOLD` |
| Token / cost budgets | unset | all three precedence levels |
