# Execution & Replay

Orkestr records every detail of an agent run -- tool calls, LLM responses, decisions, token usage, and cost -- giving you full observability into what your agents do and why.

## Running Agents

Launch an agent execution from the **Execution Playground** in the React SPA. Select a project, choose an agent, provide input, and click **Run**.

Execution flows through four phases per iteration:

| Phase | What Happens |
|---|---|
| **Perceive** | Agent receives input and context |
| **Reason** | LLM generates a plan or next action |
| **Act** | Tool calls execute via MCP |
| **Observe** | Agent evaluates results and decides whether to continue |

The agent loops through these phases until a termination condition is met: goal achieved, max iterations reached, timeout, manual stop, or **[runtime guardrail halt](./runtime-safety)** (loop detection, turn cap, or token/cost budget exhaustion).

Halted runs transition to `status=halted_guardrail` with a human-readable `halt_reason` on the run record. The playground surfaces a red banner above the trace when this happens.

## Real-Time SSE Streaming

Execution output streams to the browser in real time via Server-Sent Events (SSE). As the agent works, you see:

- LLM text output appearing token by token
- Tool call invocations with parameters
- Tool results as they return
- Phase transitions (perceive, reason, act, observe)
- Status changes (running, awaiting approval, completed, failed)

::: tip
SSE streaming works over the standard HTTP connection -- no WebSocket configuration required. The endpoint is `POST /api/skills/{id}/test` for skill testing and `POST /api/playground` for the playground.
:::

## Execution Traces

Every execution run is recorded as an `ExecutionRun` with a series of `ExecutionStep` records. Each step captures:

- **Step number** and **phase** (perceive, reason, act, observe)
- **Input** sent to the LLM
- **Output** returned by the LLM
- **Tool calls** -- the full request and response for every tool invocation
- **Token usage** -- input tokens, output tokens per step
- **Duration** -- wall-clock time in milliseconds
- **Model used** -- the actual model that processed the step
- **Approval status** -- whether human approval was required and who approved

```
GET /api/projects/{id}/executions/{runId}
```

The response includes the run summary plus all steps in order, giving you a complete audit trail.

## Cost Tracking

Orkestr tracks cost at the step and run level:

- **Tokens** -- input + output tokens accumulated per step, summed on the run
- **Cost in microcents** -- `total_cost_microcents` on each run (1 microcent = $0.000001)
- **Duration** -- `total_duration_ms` for the entire run
- **Model used** -- which model was actually invoked (may differ from requested if fallback occurred)

::: warning
Cost calculation uses approximate per-token pricing for each model. For precise billing, cross-reference with your provider's usage dashboard.
:::

The execution detail view shows a cost breakdown table:

| Metric | Value |
|---|---|
| Total tokens | 12,847 |
| Input tokens | 9,231 |
| Output tokens | 3,616 |
| Estimated cost | $0.0384 |
| Duration | 8,420 ms |

### Per-run budgets

You can set a hard token or cost ceiling on individual runs via the execute endpoint:

```http
POST /api/projects/{project}/agents/{agent}/execute
{
  "input": { "message": "…" },
  "token_budget": 50000,
  "cost_budget_usd": 2.50
}
```

Precedence: per-run override → per-agent (`run_token_budget` / `run_cost_budget_usd`) → org default (`default_run_token_budget` / `default_run_cost_budget_usd`). When a live counter crosses the resolved budget, the run halts with `halt_reason=budget_token_exceeded` or `budget_cost_exceeded` and the owner is notified. See [Runtime Safety Guardrails](./runtime-safety).

### Run visibility and forking

Each run has a `visibility` field (`private` default, `team`, `org`). Team- and org-visible runs show up in the [Work Feed](./social-layer#work-feed-fork) where teammates can fork them — copy `input` + `config` into a new draft run — to remix a successful prompt without starting from scratch.

## Execution Replay

The replay UI lets you step through a completed execution after the fact. Open any past run and enter replay mode.

### Timeline Scrubber

A horizontal timeline at the top of the replay view shows every step as a node. Drag the scrubber to jump to any point in the execution. Each node is color-coded by phase:

- Blue -- perceive
- Yellow -- reason
- Green -- act
- Purple -- observe

### Step-Through Navigation

Use the **Previous** and **Next** buttons (or arrow keys) to move one step at a time. Each step shows the full context: what the agent saw, what it decided, what tools it called, and what came back.

### Auto-Advance Playback

Click **Play** to auto-advance through steps at a configurable speed. The playback speed control lets you choose between 1x, 2x, and 4x speed. Click **Pause** to stop at any point and inspect the current step in detail.

::: tip
Replay is particularly useful for debugging multi-step agent runs. When an agent makes a wrong decision, scrub to that step and examine the exact input and reasoning that led to the error.
:::

## Execution Diff

Compare two execution runs side by side to understand how changes to an agent, skill, or model affect behavior.

### Opening a Diff

From the execution history list, select two runs and click **Compare**. The diff view shows:

- **Left panel** -- Run A with its steps
- **Right panel** -- Run B with its steps
- **Summary stats** at the top comparing both runs

### Summary Statistics

| Metric | Run A | Run B | Delta |
|---|---|---|---|
| Total steps | 12 | 9 | -3 |
| Total tokens | 14,200 | 11,800 | -2,400 |
| Cost | $0.042 | $0.035 | -$0.007 |
| Duration | 12.4s | 9.1s | -3.3s |

### Step Alignment

The diff view aligns steps by phase and sequence, highlighting where the two runs diverge. Added steps are shown in green, removed steps in red, and changed steps with an inline diff of their output.

::: tip
Execution diff is ideal for A/B testing. Run the same agent with two different models or skill configurations, then compare to see which produces better results at lower cost.
:::

## Human-in-the-Loop Approval

When an agent's autonomy level requires approval for certain actions, the execution pauses at the relevant step with status `awaiting_approval`. The step detail shows the pending tool call and its parameters. An authorized user can **approve** or **reject** the action, optionally adding a note explaining the decision.

## Statuses

An execution run progresses through these statuses:

| Status | Meaning |
|---|---|
| `pending` | Created but not yet started |
| `running` | Actively executing |
| `awaiting_approval` | Paused for human approval |
| `completed` | Finished successfully |
| `failed` | Terminated with an error |
| `cancelled` | Manually cancelled by user |
