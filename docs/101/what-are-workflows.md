# What are Workflows?

## The One-Sentence Answer

A workflow is a multi-agent pipeline — a directed graph where each step runs an agent, and conditions control the flow between steps.

## The Analogy: An Assembly Line

Think of a car factory assembly line:

1. Station 1: Weld the frame
2. Station 2: Install the engine
3. Station 3: Paint the body
4. Quality check: Passes inspection?
   - Yes → Station 4: Final assembly
   - No → Back to painting

Each station does one thing well, and the car moves through stations in order. Some stations can run in parallel (interior and exterior work). Quality checkpoints decide what happens next.

A workflow is this assembly line, but for AI agent work.

## Workflow Structure: The DAG

Workflows are defined as **DAGs** — Directed Acyclic Graphs. That sounds technical, but it just means:

- **Directed** — Work flows in one direction (forward)
- **Acyclic** — No infinite loops (you can't send work backward in a circle)
- **Graph** — A set of nodes connected by edges

```
┌─────────┐     ┌──────────┐     ┌──────────┐
│  Start   │────►│ Agent A   │────►│ Agent B   │
└─────────┘     └──────────┘     └────┬─────┘
                                      │
                            ┌─────────▼──────────┐
                            │     Condition       │
                            │  "Quality passed?"  │
                            └──┬──────────────┬──┘
                               │ Yes          │ No
                         ┌─────▼─────┐  ┌─────▼─────┐
                         │  Agent C   │  │  Agent D   │
                         └─────┬─────┘  └─────┬─────┘
                               │              │
                         ┌─────▼──────────────▼─────┐
                         │          End              │
                         └───────────────────────────┘
```

## Node Types

Workflows support seven types of nodes (steps):

### Agent Step

Runs a specific agent. The agent executes its full loop (Goal → Perceive → Reason → Act → Observe) and passes its output to the next step.

### Condition Step

Evaluates an expression and routes the flow. Like an if/else:

```
Condition: "findings.severity == 'critical'"
  ├── True  → Escalation Agent
  └── False → Summary Agent
```

### Checkpoint Step (Human-in-the-Loop)

Pauses the workflow and waits for human approval. This is critical for high-stakes workflows:

```
┌──────────────┐     ┌────────────────┐     ┌──────────────┐
│ Architect     │────►│  CHECKPOINT    │────►│  Deploy       │
│ designs       │     │  "Approve      │     │  Agent        │
│ migration     │     │   schema       │     │  executes     │
└──────────────┘     │   changes?"    │     └──────────────┘
                      └────────────────┘
                      ↑ Human reviews
                      ↑ and clicks Approve
```

The workflow stays paused until someone approves, rejects, or provides feedback.

### Parallel Split

Forks the workflow into parallel branches:

```
                    ┌──────────────┐
               ┌───►│ Security     │───┐
               │    │ Agent        │   │
┌─────────┐    │    └──────────────┘   │    ┌──────────┐
│ Split    │───┤                       ├───►│  Join    │
└─────────┘    │    ┌──────────────┐   │    └──────────┘
               └───►│ Performance  │───┘
                    │ Agent        │
                    └──────────────┘
```

### Parallel Join

Waits for all parallel branches to complete before continuing. Collects outputs from all branches.

### Start / End

Marker nodes for the beginning and end of the workflow. Every workflow has exactly one start and one or more end nodes.

## Shared Context

Workflows have a **context bus** — a shared data space that passes information between steps:

```
Step 1 (Architect):
  Output → { schema: "users table with 5 columns", migration: "SQL code..." }
  → Written to context as: context.architecture.schema

Step 2 (QA Agent):
  Input ← context.architecture.schema
  "Review this schema for edge cases and data integrity issues"

Step 3 (Security Agent):
  Input ← context.architecture.schema
  "Review this schema for data exposure and access control issues"
```

Each step can read from and write to the context. This is how information flows through the workflow without agents needing to know about each other directly.

## Triggers

Workflows can be started in several ways:

| Trigger | How It Works |
|---|---|
| **Manual** | Click "Run" in the UI |
| **Scheduled** | Cron expression (e.g., "every Monday at 9am") |
| **Webhook** | External system sends an HTTP request (e.g., GitHub push) |
| **A2A** | Another agent delegates to this workflow |

## Workflow Execution

When a workflow runs, Orkestr's execution engine:

1. Starts at the **Start** node
2. Follows edges to the next step(s)
3. Executes each agent step (full agent loop)
4. Evaluates conditions to choose the next path
5. Handles parallel splits and joins
6. Pauses at checkpoints for human approval
7. Records every step in the execution trace
8. Continues until reaching an **End** node

The entire execution is visible in real-time on the workflow canvas — each node lights up as it runs, with status indicators (pending, running, completed, failed).

## Real-World Workflow Examples

### PR Review Pipeline

```
Start → Read PR → Security Agent → Code Review Agent →
  Checkpoint("Approve?") →
    Yes → Merge Agent → End
    No → Feedback Agent → End
```

### Incident Response

```
Start → Alert Agent (perceive) → Triage Agent (classify severity) →
  Condition:
    Critical → Page On-Call + Incident Commander Agent
    High     → Diagnostic Agent → Remediation Agent → Checkpoint
    Low      → Log and monitor → End
```

### Content Pipeline

```
Start → Research Agent → Outline Agent →
  Checkpoint("Approve outline?") →
    Approved → Writer Agent → Editor Agent →
      Checkpoint("Publish?") →
        Yes → Publisher Agent → End
        No  → Writer Agent (revise) → Editor Agent
```

## Workflow Versions

Like skills, workflows have version history. Every save creates a snapshot. You can:

- Compare versions side-by-side
- Restore a previous version
- See who changed what and when

## Workflow Export

Designed a workflow in Orkestr? Export it to external frameworks:

| Format | What It Generates |
|---|---|
| **Generic JSON** | Complete workflow definition with all steps and edges |
| **LangGraph YAML** | Compatible with LangChain's LangGraph framework |
| **CrewAI Config** | Compatible with the CrewAI framework |

## Key Takeaway

Workflows are how you choreograph multi-agent systems. They provide the structure — the "who does what, when, and under what conditions" — for agent teams to work together reliably, with human oversight at critical points.

---

**Next:** [What is the Canvas?](./what-is-the-canvas) →
