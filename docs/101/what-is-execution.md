# What is Execution?

## The One-Sentence Answer

Execution is what happens when you press "Run" — Orkestr's built-in runtime powers the agent through its loop, makes real tool calls, enforces guardrails, and records every step.

## The Analogy: Pressing Play

Think of a recording studio. You've written the song (skills), assembled the band (agents), and arranged the music (workflows). **Execution** is pressing the record button — the band plays, the engineer monitors the levels, and every take is captured on tape.

Orkestr is both the stage and the recording studio. It runs the performance and captures every detail.

## What Happens When You Execute an Agent

### 1. Initialization

The execution engine:
- Loads the agent definition (identity, goal, skills, tools)
- Resolves all skill includes and template variables
- Connects to MCP servers (starts stdio processes, opens SSE connections)
- Allocates budget from the agent's envelope
- Creates an execution run record

### 2. The Loop Runs

The agent enters its Goal → Perceive → Reason → Act → Observe loop:

```
Iteration 1:
  [Perceive] Read input, retrieve memory
  [Reason]   Model call → claude-sonnet-4-6 (1,200 input tokens)
  [Act]      Tool call → filesystem.readFile("src/auth.ts")
  [Observe]  File returned, 450 lines. Found issue on line 15.

Iteration 2:
  [Perceive] Previous findings + next file
  [Reason]   Model call → claude-sonnet-4-6 (2,100 input tokens)
  [Act]      Tool call → filesystem.readFile("src/api.ts")
  [Observe]  File returned. Found issue on line 42. 2 of 3 files done.

Iteration 3:
  [Perceive] Previous findings + last file
  [Reason]   Model call → claude-sonnet-4-6 (2,800 input tokens)
  [Act]      Tool call → filesystem.readFile("src/utils.ts")
  [Observe]  No issues. All files reviewed. Goal met.

Iteration 4:
  [Act]      Output → Final report with 2 findings
  DONE
```

### 3. Guardrails Check Every Step

At every point in the loop, guardrails are active:

- **Budget guard** — Is there enough budget for this model call/tool call?
- **Tool guard** — Is this tool allowed for this agent?
- **Approval guard** — Does this action need human approval?
- **Output guard** — Does the output contain PII or secrets?
- **Data access guard** — Is the agent staying within its allowed scope?

If any guard triggers, the execution pauses (for approval) or stops (for violations).

### 4. Everything is Traced

Every iteration, every model call, every tool call is recorded:

```
Execution Run #847
├── Status: completed
├── Agent: Security Auditor
├── Iterations: 4
├── Duration: 6.3 seconds
├── Total tokens: 8,500 (input: 6,100, output: 2,400)
├── Total cost: $0.018
├── Tool calls: 3
│   ├── filesystem.readFile (45ms) ✓
│   ├── filesystem.readFile (38ms) ✓
│   └── filesystem.readFile (41ms) ✓
├── Guardrail checks: 12 (all passed)
└── Output: { findings: [...], summary: "2 high, 0 medium" }
```

## The Execution Dashboard

Orkestr provides a dashboard for monitoring all executions:

### Run List

A chronological list of all execution runs with:
- Status (running, completed, failed, paused)
- Agent name
- Duration
- Token count and cost
- Trigger (manual, scheduled, webhook, A2A)

### Run Detail

Click a run to see the full trace:
- **Timeline** — Visual timeline of each iteration
- **Step inspector** — Click any step to see inputs, outputs, and tool calls
- **Cost breakdown** — Tokens and cost per iteration
- **Guardrail log** — Every guardrail check and its result

### Execution Replay

Orkestr records enough data to replay any execution step by step:
- Scrub through the timeline like a video
- See exactly what the agent "thought" at each step
- Compare two runs side-by-side
- Understand why the agent made a specific decision

## Workflow Execution

When you execute a workflow (not just a single agent), the engine:

1. Starts at the Start node
2. Runs each agent step through its full loop
3. Evaluates conditions to choose the next path
4. Handles parallel branches (runs agents simultaneously)
5. Pauses at checkpoints for human approval
6. Passes context between steps via the shared context bus
7. Continues until reaching an End node

The canvas shows live status:
- **Gray** — Not yet reached
- **Blue** — Currently running
- **Green** — Completed successfully
- **Red** — Failed
- **Yellow** — Paused at checkpoint

## Execution Triggers

| How | When |
|---|---|
| **Manual** | Click "Run" on the canvas, agent detail, or execution page |
| **Scheduled** | Cron expression — "Run the nightly security scan at 2am" |
| **Webhook** | External event — "Run when GitHub sends a push event" |
| **A2A** | Another agent delegates a task to this agent |

## Cost Tracking

Orkestr tracks costs across every dimension:

| Level | What It Tracks |
|---|---|
| Per-step | Tokens and cost for each iteration of the agent loop |
| Per-run | Total tokens and cost for the entire execution |
| Per-agent | Aggregate cost across all runs of this agent |
| Per-project | Total cost for all agents in a project |
| Per-organization | Total cost across all projects |
| Daily/weekly/monthly | Time-series cost trends |

This data feeds into the analytics dashboard and budget enforcement.

## Key Takeaway

Execution is where design meets reality. Orkestr's built-in runtime runs your agents with real tool calls, enforces safety guardrails at every step, and records comprehensive traces for debugging and optimization. You don't need an external framework — the execution engine is part of the platform.

---

**Next:** [What is Agent Memory?](./what-is-agent-memory) →
