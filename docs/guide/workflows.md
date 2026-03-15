# Workflows

Workflows are multi-agent orchestration pipelines defined as directed acyclic graphs (DAGs). Each workflow consists of steps connected by edges, where agents execute tasks, conditions route execution, and checkpoints pause for human approval.

## Creating a Workflow

Navigate to a project and open the workflow builder. You can create a new workflow or edit an existing one. The builder has three areas:

- **Toolbar** -- workflow metadata, step palette, and actions
- **Canvas** -- the React Flow DAG editor where you place and connect steps
- **Properties Panel** -- configuration for the selected step or edge

Give the workflow a name, select a trigger type, and set its status.

### Trigger Types

| Trigger | Description |
|---|---|
| **Manual** | Started by clicking a button or calling the API |
| **Webhook** | Triggered by an incoming webhook event |
| **Schedule** | Runs on a cron schedule |
| **Event** | Triggered by a system event (e.g., skill updated, sync completed) |

### Status

| Status | Description |
|---|---|
| **Draft** | Work in progress, not executable |
| **Active** | Ready to run |
| **Archived** | Retired, kept for reference |

## Step Types

Click **Add Step** in the toolbar to add a new step to the canvas. There are seven step types:

### Start

The entry point of the workflow. Every workflow needs exactly one start step. It has no incoming edges.

### End

The terminal step. Every workflow needs at least one end step. It has no outgoing edges.

### Agent

An agent step executes a specific agent. Select which agent to assign in the properties panel. You can optionally override the agent's default model for this step.

```
Start → [Code Review Agent] → [QA Agent] → End
```

### Checkpoint

A human-in-the-loop step that pauses execution and waits for manual approval before proceeding. Checkpoints are shown with an amber shield icon and a "requires approval" label.

::: tip
Use checkpoints before destructive or high-impact steps. For example, place a checkpoint between an agent that generates a migration script and one that executes it.
:::

### Condition

A branching step that routes execution based on the previous step's output. Add condition expressions on outgoing edges to control which path is taken. Condition edges are shown with purple animated lines.

### Parallel Split

Splits execution into multiple concurrent branches. All outgoing edges from a split step run in parallel. Use this when independent tasks can execute simultaneously.

### Parallel Join

Waits for all incoming parallel branches to complete before proceeding. Place a join step after a split to synchronize concurrent branches.

```
Start → [Split] → [Agent A] → [Join] → End
                → [Agent B] ↗
```

## Connecting Steps

To connect two steps, click the source handle (bottom of a node) and drag to the target handle (top of another node). An edge appears between them. The edge represents the flow of execution and context.

### Edge Properties

Click any edge to open the properties panel. Each edge has:

- **Label** -- optional display label on the edge
- **Condition Expression** -- for edges leaving a condition step, the expression that must be true for this path (e.g., `status == "approved"`)
- **Priority** -- when multiple outgoing edges match, higher priority edges are evaluated first

Condition expressions support comparison operators (`==`, `!=`, `>`, `<`, `>=`, `<=`) and the `exists` keyword. Expressions reference values from the workflow's shared context bus.

::: warning
Conditional edges are shown with animated purple lines to distinguish them from regular execution edges. If you add a condition expression to an edge, make sure the source step is a condition type.
:::

## Parallel Execution

To run agent steps in parallel:

1. Add a **Parallel Split** step
2. Connect it to two or more agent steps
3. Connect all parallel agent steps to a **Parallel Join** step
4. Continue the workflow after the join

All branches between the split and join execute concurrently. The join waits for every branch to complete before passing combined context to the next step.

## Human-in-the-Loop Checkpoints

Checkpoint steps pause the workflow and surface a notification for human review. The reviewer can:

- **Approve** -- resume execution from the checkpoint
- **Reject** -- terminate the workflow at that point

Checkpoints are essential for workflows that make real-world changes (deployments, database migrations, external API calls) where a human should verify the plan before execution.

## DAG Validation

Click **Validate** in the toolbar to check the workflow for structural issues. The validator detects:

- **Cycles** -- circular dependencies that would cause infinite loops
- **Missing start/end** -- every workflow needs exactly one start and at least one end
- **Disconnected nodes** -- steps that are not reachable from the start
- **Missing agents** -- agent steps without an assigned agent
- **Unbalanced splits** -- parallel splits without matching joins

Errors are shown in a red banner below the toolbar. Warnings appear in amber. Fix all errors before activating the workflow.

## Workflow Versioning

Workflows support version history. Click **Versions** in the toolbar to:

- **Save Snapshot** -- create a named version of the current workflow state
- **View versions** -- see all previous versions with timestamps
- **Restore** -- revert the workflow to a previous version

::: tip
Create a version snapshot before making significant structural changes. If something goes wrong, you can restore the previous version.
:::

## Export

Saved workflows can be exported in multiple formats:

| Format | Description |
|---|---|
| **JSON** | Native Orkestr workflow format |
| **LangGraph YAML** | Compatible with LangChain's LangGraph framework |
| **CrewAI Config** | Compatible with the CrewAI agent framework |

Click **Export** in the toolbar and select the desired format. The file downloads to your browser.

## Keyboard and Mouse Controls

The workflow canvas supports:

- **Scroll to zoom** in and out
- **Click and drag** on empty space to pan
- **Click a node** to select it and open properties
- **Click an edge** to select it and configure conditions
- **Click empty space** to deselect
- **Snap to grid** -- nodes snap to a 20px grid for clean alignment

The canvas includes a minimap in the corner for orientation on large workflows.

## Next Steps

- [Canvas](./canvas) -- the WYSIWYG builder for agent-skill relationships
- [Agent Teams](./agent-teams) -- configuring agents and autonomy levels
- [Agent Compose](./agent-compose) -- how agent output is assembled
