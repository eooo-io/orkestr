# Workflow DAG Engine

This deep dive covers the workflow execution engine — the system that orchestrates multi-agent pipelines as directed acyclic graphs.

## Architecture

```
┌──────────────────────────────────────────────────┐
│               WorkflowExecutionRunner              │
│                                                   │
│  ┌─────────────┐  ┌───────────────┐              │
│  │ DAG Resolver │  │ Step Executor │              │
│  └──────┬──────┘  └───────┬───────┘              │
│         │                  │                      │
│         │  ┌───────────────▼───────────────┐      │
│         │  │  WorkflowContextService       │      │
│         │  │  (shared context bus)          │      │
│         │  └───────────────────────────────┘      │
│         │                                         │
│         │  ┌───────────────────────────────┐      │
│         └─►│  WorkflowConditionEvaluator   │      │
│            │  (edge condition routing)      │      │
│            └───────────────────────────────┘      │
│                                                   │
└──────────────────────────────────────────────────┘
```

## Data Model

### Workflows

```
workflows
├── id (UUID)
├── project_id → projects
├── name: "PR Review Pipeline"
├── slug: "pr-review-pipeline"
├── trigger_type: "manual" | "cron" | "webhook" | "a2a"
├── trigger_config (JSON) — cron expression, webhook URL, etc.
├── entry_step_id → workflow_steps
├── status: "draft" | "active" | "archived"
├── context_schema (JSON) — schema for the shared context bus
├── termination_policy (JSON) — max duration, max cost, etc.
└── config (JSON) — additional settings
```

### Steps (Nodes)

```
workflow_steps
├── id (UUID)
├── workflow_id → workflows
├── agent_id → agents (nullable — not all step types need an agent)
├── type: "agent" | "checkpoint" | "condition" | "parallel_split" |
│         "parallel_join" | "start" | "end"
├── name: "Security Review"
├── position_x, position_y — canvas coordinates
├── config (JSON):
│   ├── For agent: model override, input mapping, output key
│   ├── For condition: expression, variable bindings
│   ├── For checkpoint: approval message, required approvers
│   └── For parallel: branch labels
└── sort_order
```

### Edges (Connections)

```
workflow_edges
├── id (UUID)
├── workflow_id → workflows
├── source_step_id → workflow_steps
├── target_step_id → workflow_steps
├── condition_expression: "result.severity == 'critical'"
├── label: "Critical path"
└── priority: 1 (for ordering when multiple edges leave a node)
```

### Versions

```
workflow_versions
├── id (UUID)
├── workflow_id → workflows
├── version_number: 1, 2, 3, ...
├── snapshot (JSON) — complete workflow state (steps + edges)
└── note: "Added checkpoint before deploy"
```

## DAG Validation

Before a workflow can be activated or executed, it's validated:

```
Validation Rules:
├── Exactly one Start node
├── At least one End node
├── All nodes reachable from Start
├── All nodes can reach at least one End
├── No cycles (DAG property)
├── Every parallel_split has a matching parallel_join
├── Agent steps have valid agent bindings
├── Condition expressions are parseable
└── Edge conditions are mutually exhaustive (every path is covered)
```

The `DagValidationService` implements these checks using graph traversal algorithms (topological sort for cycle detection, BFS/DFS for reachability).

## Execution Flow

### 1. Initialization

```
1. Load workflow definition (steps, edges)
2. Create execution_run for the workflow itself
3. Initialize context bus (empty or from trigger input)
4. Resolve the entry step (Start node)
5. Begin traversal
```

### 2. Step Execution

For each step, the executor dispatches based on type:

#### Agent Step

```
1. Resolve the agent assigned to this step
2. Map input from context bus using step's input mapping:
   e.g., { "diff": "context.pr.diff", "files": "context.pr.files" }
3. Create a child execution_run for the agent
4. Run the full agent loop (Goal → Perceive → Reason → Act → Observe)
5. Write output to context bus using step's output key:
   e.g., context.security_review = agentOutput
6. Mark step as completed
```

#### Condition Step

```
1. Evaluate condition_expression against context bus
   e.g., context.security_review.severity == 'critical'
2. Follow the edge whose condition matches
3. If no condition matches, follow the default edge (no condition)
4. If no default edge, mark workflow as failed
```

#### Checkpoint Step

```
1. Pause workflow execution
2. Set status to "paused_at_checkpoint"
3. Send notification to approvers
4. Wait for human action:
   - Approve → continue to next step
   - Reject → follow rejection edge (if defined) or terminate
   - Provide feedback → attach to context, optionally re-route
```

#### Parallel Split

```
1. Identify all outgoing edges
2. Fork execution into N parallel branches
3. Run each branch concurrently (separate queue jobs)
4. Track branch IDs for the matching join
```

#### Parallel Join

```
1. Wait for all incoming branches to complete
2. Merge results from all branches into context:
   context.parallel_results = {
     branch_1: { ... },
     branch_2: { ... }
   }
3. Continue to next step
```

### 3. Context Bus

The `WorkflowContextService` manages a shared key-value store:

```
Context Bus Operations:
├── set(key, value) — write a value
├── get(key) — read a value
├── merge(key, value) — deep merge into existing value
├── has(key) — check if key exists
└── dump() — return entire context (for debugging)

Context is:
├── Initialized from trigger input
├── Written to after each agent step
├── Read from by condition evaluators
├── Passed to agent steps via input mapping
└── Persisted in execution_run.output on completion
```

### 4. Condition Evaluation

The `WorkflowConditionEvaluator` supports:

```
Expression syntax:
├── Comparison: context.severity == 'critical'
├── Boolean: context.approved && context.passing_tests
├── Numeric: context.score > 80
├── Existence: context.findings != null
├── Array: context.findings.length > 0
└── Nested: context.review.security.passed == true
```

Edges are evaluated in priority order. The first matching edge is followed.

## Live Visualization

During execution, the workflow canvas shows real-time status:

```
Status Colors:
├── Gray   — Not yet reached
├── Blue   — Currently executing
├── Green  — Completed successfully
├── Red    — Failed
├── Yellow — Paused at checkpoint
└── Purple — Running in parallel
```

Status updates are pushed via SSE to the frontend. Each node shows:
- Current status
- Duration so far
- Token count and cost (for agent steps)
- Output summary (collapsible)

## Workflow Triggers

### Manual

User clicks "Run" in the UI or calls the API:

```
POST /api/projects/{p}/workflows/{w}/execute
{ "input": { "pr_number": 42 } }
```

### Cron Schedule

Defined in `trigger_config`:

```json
{ "cron": "0 2 * * *", "input": { "scope": "last_24_hours" } }
```

The Laravel scheduler checks for due workflows every minute.

### Webhook

External systems send HTTP requests:

```
POST /api/webhooks/{workflowId}
{ "event": "pull_request", "action": "opened", ... }
```

The webhook payload becomes the initial context.

### A2A

Another agent delegates to this workflow as a task.

## Export Formats

### Generic JSON

Complete workflow definition with all steps, edges, and configuration:

```json
{
  "name": "PR Review Pipeline",
  "steps": [ ... ],
  "edges": [ ... ],
  "context_schema": { ... },
  "termination_policy": { ... }
}
```

### LangGraph YAML

Translated into LangChain's LangGraph format:

```yaml
graph:
  nodes:
    security_review:
      agent: security-auditor
      model: claude-sonnet-4-6
    code_review:
      agent: code-reviewer
  edges:
    - from: START
      to: security_review
    - from: security_review
      to: code_review
      condition: "result.passed"
```

### CrewAI Config

Translated into CrewAI agent and task definitions.

## Error Handling

| Scenario | Behavior |
|---|---|
| Agent step fails | Mark step as failed, follow error edge if defined, otherwise mark workflow as failed |
| Condition has no matching edge | Mark workflow as failed with "routing error" |
| Checkpoint times out | Configurable: auto-reject or leave paused |
| Parallel branch fails | Configurable: fail workflow or continue with remaining branches |
| Budget exceeded | Stop current agent, mark workflow as budget_exceeded |
| Workflow timeout | Stop all running agents, mark as timed_out |
