# 06 — Execution Engine

## Agent Execution

### TC-06-001: Execute agent — basic run
**Priority:** P0
**Preconditions:** Agent configured with model and skills, API key set, on `/projects/:id/execute`
**Steps:**
1. Select agent from dropdown
2. Enter input: "Review this function for bugs: function add(a, b) { return a - b; }"
3. Click "Execute"
**Expected:** Execution starts. SSE stream delivers response incrementally. Execution trace shows Perceive → Reason → Act → Observe phases. Run completes with result.

### TC-06-002: Execute agent — streaming output
**Priority:** P0
**Preconditions:** Agent execution in progress
**Steps:**
1. Start execution
2. Observe output area
**Expected:** Tokens appear one by one (or in chunks) via SSE. Not a single block after completion.

### TC-06-003: Execute agent — with tool calls
**Priority:** P0
**Preconditions:** Agent has MCP tools bound (e.g., filesystem tool)
**Steps:**
1. Execute agent with prompt that requires tool use
**Expected:** Trace shows tool call with name, arguments, and response. Agent uses tool output to form final response.

### TC-06-004: Execute agent — cancel mid-execution
**Priority:** P1
**Preconditions:** Long-running execution in progress
**Steps:**
1. Start execution
2. Click "Cancel" while agent is running
**Expected:** Execution stops. Status: "cancelled." Partial trace preserved. No orphaned processes.

### TC-06-005: Execute agent — max iterations reached
**Priority:** P1
**Preconditions:** Agent with max_iterations: 3, task that requires more iterations
**Steps:**
1. Execute agent with complex task
**Expected:** Agent stops after 3 iterations. Status: "max_iterations_reached." Partial result returned. User notified.

### TC-06-006: Execute agent — timeout
**Priority:** P1
**Preconditions:** Agent with timeout: 10 (seconds), task that takes longer
**Steps:**
1. Execute agent
2. Wait for timeout
**Expected:** Execution terminates after timeout. Status: "timed_out." Partial result preserved.

### TC-06-007: Execute agent — invalid API key
**Priority:** P0
**Preconditions:** API key for agent's model is invalid/expired
**Steps:**
1. Execute agent
**Expected:** Clear error: "Authentication failed for [provider]. Check your API key in Settings." Not a raw 401 JSON dump.

### TC-06-008: Execute agent — model not available
**Priority:** P1
**Preconditions:** Agent configured with a model that's down or doesn't exist
**Steps:**
1. Execute agent
**Expected:** Clear error message. Suggest checking model name or trying a different model.

### TC-06-009: Execute agent — empty input
**Priority:** P2
**Preconditions:** Agent selected
**Steps:**
1. Leave input empty
2. Click Execute
**Expected:** Validation error: input required. Or agent executes with its system prompt only (if that's by design).

### TC-06-010: Execute agent — multi-turn conversation
**Priority:** P1
**Preconditions:** Agent execution playground
**Steps:**
1. Execute agent with first message
2. Wait for response
3. Send follow-up message referencing the first response
**Expected:** Agent maintains context from previous turns. Response is contextually aware.

## Execution Trace

### TC-06-011: Trace — phase display
**Priority:** P0
**Preconditions:** Execution completed
**Steps:**
1. View execution trace
**Expected:** Each phase shown with icon: Perceive, Reason, Act, Observe. Expandable to see details.

### TC-06-012: Trace — tool calls detail
**Priority:** P1
**Preconditions:** Execution with tool calls completed
**Steps:**
1. Expand Act phase in trace
**Expected:** Shows tool name, input arguments (formatted JSON), response, duration.

### TC-06-013: Trace — token usage per step
**Priority:** P1
**Preconditions:** Execution completed
**Steps:**
1. View trace
**Expected:** Each step shows input/output token count. Total at bottom.

### TC-06-014: Trace — cost tracking
**Priority:** P0
**Preconditions:** Execution completed
**Steps:**
1. View execution detail
**Expected:** Cost calculated based on model pricing × tokens used. Displayed in USD.

### TC-06-015: Trace — duration per step
**Priority:** P2
**Preconditions:** Execution completed
**Steps:**
1. View trace
**Expected:** Each step shows elapsed time. Total execution duration at top.

## Budget & Guardrails

### TC-06-016: Budget limit — stops execution
**Priority:** P0
**Preconditions:** Agent has budget limit set (e.g., $0.50)
**Steps:**
1. Execute agent on task that would exceed budget
**Expected:** Execution stops when budget reached. Status: "budget_exceeded." Cost shown. User notified.

### TC-06-017: Tool allowlist — blocks unauthorized tools
**Priority:** P0
**Preconditions:** Agent has tool allowlist (e.g., only "read_file" allowed)
**Steps:**
1. Execute agent where model tries to call "delete_file"
**Expected:** Tool call blocked. Trace shows "Tool 'delete_file' not in allowlist." Agent continues with available tools or terminates gracefully.

### TC-06-018: PII detection — content filtering
**Priority:** P1
**Preconditions:** Output content filtering enabled
**Steps:**
1. Execute agent that produces output containing SSN, credit card patterns
**Expected:** PII detected and flagged. Either redacted or execution flagged for review.

### TC-06-019: Approval gate — pauses for human
**Priority:** P1
**Preconditions:** Agent configured with approval gate for certain tool calls
**Steps:**
1. Execute agent
2. Agent requests a tool call that requires approval
**Expected:** Execution pauses. UI shows approval prompt with tool call details. User can approve or deny.

### TC-06-020: Approval gate — approve and continue
**Priority:** P1
**Preconditions:** Execution paused at approval gate
**Steps:**
1. Click "Approve"
**Expected:** Execution resumes. Tool call executed. Trace records the approval.

### TC-06-021: Approval gate — deny and terminate
**Priority:** P1
**Preconditions:** Execution paused at approval gate
**Steps:**
1. Click "Deny"
**Expected:** Tool call skipped or execution terminated. Trace records the denial.

## Workflow Execution

### TC-06-022: Execute workflow — linear pipeline
**Priority:** P0
**Preconditions:** Workflow: Start → Agent A → Agent B → End. All agents configured.
**Steps:**
1. Navigate to workflow or execution playground
2. Execute workflow with input
**Expected:** Agent A runs first, output passed to Agent B. Both traces visible. Workflow completes.

### TC-06-023: Execute workflow — conditional branching
**Priority:** P1
**Preconditions:** Workflow with condition node: if approved → Agent A, else → Agent B
**Steps:**
1. Execute workflow with input that triggers the condition
**Expected:** Correct branch taken based on condition evaluation. Only one branch executed.

### TC-06-024: Execute workflow — parallel split/join
**Priority:** P1
**Preconditions:** Workflow with parallel split: Agent A and Agent B run concurrently, then join
**Steps:**
1. Execute workflow
**Expected:** Both agents execute in parallel. Join waits for both. Combined output passed downstream.

### TC-06-025: Execute workflow — checkpoint pause
**Priority:** P1
**Preconditions:** Workflow with checkpoint node between two agents
**Steps:**
1. Execute workflow
2. Execution reaches checkpoint
**Expected:** Workflow pauses. UI shows checkpoint with context. "Approve" / "Reject" buttons.

### TC-06-026: Execute workflow — checkpoint approve
**Priority:** P1
**Preconditions:** Workflow paused at checkpoint
**Steps:**
1. Click "Approve"
**Expected:** Workflow resumes from checkpoint. Next step executes.

### TC-06-027: Execute workflow — checkpoint reject
**Priority:** P1
**Preconditions:** Workflow paused at checkpoint
**Steps:**
1. Click "Reject"
**Expected:** Workflow terminates or routes to rejection branch. Status: "rejected."

### TC-06-028: Execute workflow — live DAG visualization
**Priority:** P2
**Preconditions:** Workflow executing
**Steps:**
1. Watch workflow visualization during execution
**Expected:** Active step highlighted. Completed steps show green. Pending steps gray. Real-time updates.

## Execution Dashboard

### TC-06-029: Dashboard — runs overview
**Priority:** P1
**Preconditions:** Multiple executions completed (mix of success, failed, cancelled)
**Steps:**
1. Navigate to `/projects/:id/runs`
**Expected:** List of all runs with agent name, status, duration, cost, timestamp.

### TC-06-030: Dashboard — filter and stats
**Priority:** P2
**Preconditions:** Multiple runs exist
**Steps:**
1. Filter by agent
2. Filter by status (success/failed)
3. View aggregate stats
**Expected:** Filters work. Stats show total runs, success rate, total tokens, total cost.
