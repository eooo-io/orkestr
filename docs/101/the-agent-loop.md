# The Agent Loop

## The One-Sentence Answer

The agent loop is the cycle of **Goal → Perceive → Reason → Act → Observe** that repeats until the agent's objective is met.

## The Analogy: A Chef Making Dinner

A chef doesn't follow a recipe blindly from start to finish. They:

1. **Know the goal** — "Make a perfect risotto for 4 people"
2. **Perceive** — Check what ingredients are available, taste the broth
3. **Reason** — "The rice needs more liquid. I should add broth slowly."
4. **Act** — Add a ladle of broth, stir continuously
5. **Observe** — Taste it. Is it done? Too thick? Not enough seasoning?
6. **Loop** — Not done yet? Go back to step 2.

This is exactly what an AI agent does. It doesn't just generate text in one shot — it works iteratively, checking its progress, using tools, and adjusting its approach.

## The Five Stages

### Stage 1: Goal

Every agent execution starts with a goal — a clear objective that defines "done."

```
Goal: "Review PR #42 for security vulnerabilities and post findings as comments"

Success Criteria:
- Every changed file has been reviewed
- All findings include severity, description, and remediation
- Results are posted as PR comments

Termination:
- Max iterations: 20
- Timeout: 5 minutes
- Or: goal met (all files reviewed)
```

The goal is defined in the agent's configuration and can include dynamic inputs — a specific PR number, a file path, a user question.

### Stage 2: Perceive

The agent gathers context. This isn't just "read the input" — it's an active process:

```
Perception Sources:
├── Direct input (the PR diff, the user's question)
├── Memory recall (past reviews of this repo, known patterns)
├── Tool discovery (what MCP tools are available?)
└── Context enrichment (pull in related files, check recent commits)
```

**What makes this different from a system prompt?** A system prompt is static — it's the same every time. Perception is dynamic — the agent actively retrieves relevant context based on the current task.

### Stage 3: Reason

The agent thinks. It uses its AI model, guided by its assigned skills, to:

- Analyze the perceived context
- Plan its next action
- Decide which tool to use (or whether to delegate to another agent)

```
Reasoning:
├── Model: claude-sonnet-4-6
├── Skills applied:
│   ├── security-checklist (OWASP Top 10 review process)
│   └── coding-standards (team conventions)
├── Planning mode: structured
│   └── Sub-tasks identified:
│       1. Review authentication changes
│       2. Check input validation
│       3. Scan for hardcoded secrets
└── Decision: Start with the auth changes in auth.ts
```

The **planning mode** determines how the agent approaches its task:

| Mode | Behavior |
|---|---|
| **Structured** | Breaks the goal into sub-tasks, works through them systematically |
| **Reactive** | Responds to each input/observation immediately without upfront planning |
| **Hybrid** | Plans initially, but adapts the plan based on observations |

### Stage 4: Act

The agent does something. Actions fall into three categories:

#### Tool Calls (MCP)

The agent calls a tool provided by an MCP server:

```
Action: filesystem.readFile
Input: { path: "src/auth/login.ts" }
Result: [file contents returned]
```

```
Action: github.createComment
Input: { pr: 42, line: 15, body: "SQL injection risk: use parameterized query" }
Result: { comment_id: 12345 }
```

#### Delegation (A2A)

The agent sends a task to another agent:

```
Action: delegate to Code Review Agent
Input: { task: "Review the non-security aspects of PR #42" }
Result: [Code Review Agent's findings]
```

#### Output

The agent produces its final answer or intermediate result:

```
Action: output
Result: {
  findings: [
    { severity: "high", file: "auth.ts", line: 15, cwe: "CWE-89", ... },
    { severity: "medium", file: "api.ts", line: 42, cwe: "CWE-79", ... }
  ],
  summary: "2 findings: 1 high, 1 medium"
}
```

### Stage 5: Observe

The agent evaluates the result of its action:

```
Observation:
├── Action result: Successfully read auth.ts (450 lines)
├── Progress check: 1 of 5 changed files reviewed
├── Evaluation: Found 1 high-severity issue, need to continue
└── Decision: Loop back → next file is api.ts
```

The observation stage is where the agent decides: **Am I done, or do I need to keep going?**

## A Complete Loop Example

Here's a concrete walkthrough of a Security Agent reviewing a PR:

```
ITERATION 1:
  Perceive → Read PR diff (3 files changed: auth.ts, api.ts, utils.ts)
  Reason   → Plan: review each file for OWASP Top 10 issues
  Act      → MCP: filesystem.readFile("src/auth/login.ts")
  Observe  → Got file contents. Found SQL concatenation on line 15.
             1 of 3 files done.

ITERATION 2:
  Perceive → Current findings: 1 issue. Next file: api.ts
  Reason   → Line 15 is high-severity SQL injection. Log finding, move to next file.
  Act      → MCP: filesystem.readFile("src/api/users.ts")
  Observe  → Got file contents. Found unescaped output on line 42.
             2 of 3 files done.

ITERATION 3:
  Perceive → Current findings: 2 issues. Last file: utils.ts
  Reason   → Need to check utils.ts, then compile final report.
  Act      → MCP: filesystem.readFile("src/utils/helpers.ts")
  Observe  → Got file contents. No issues found. 3 of 3 files done.

ITERATION 4:
  Perceive → All files reviewed. 2 findings to report.
  Reason   → Goal met. Compile and output findings.
  Act      → Output: { findings: [...], summary: "2 findings: 1 high, 1 medium" }
  Observe  → Goal met. Terminate.

DONE — 4 iterations, 2 findings, 3 tool calls
```

## Loop Controls

Agents don't run forever. Orkestr enforces limits:

| Control | What It Does |
|---|---|
| **Max iterations** | Hard limit on loop cycles (e.g., 20) |
| **Timeout** | Wall-clock time limit (e.g., 5 minutes) |
| **Budget** | Token/cost limit per run (e.g., $0.50) |
| **Approval gates** | Pause for human approval on expensive/risky actions |
| **Goal completion** | Agent determines its objective is met |

If any limit is hit, the agent stops and reports its current state — it doesn't just vanish.

## Observability

Every iteration of the loop is traced:

```
Execution Trace:
├── Iteration 1
│   ├── Input tokens: 1,200
│   ├── Output tokens: 350
│   ├── Tool call: filesystem.readFile (45ms)
│   ├── Cost: $0.004
│   └── Duration: 1.2s
├── Iteration 2
│   ├── ...
└── Summary
    ├── Total iterations: 4
    ├── Total tokens: 8,500
    ├── Total cost: $0.018
    ├── Total duration: 6.3s
    └── Outcome: goal_met
```

You can view this trace in real-time during execution, replay it afterward, and compare traces between runs.

## Key Takeaway

The agent loop is what makes agents *agents* — not just prompts. The cycle of perceive-reason-act-observe, repeating until a goal is met, is the fundamental pattern that enables autonomous behavior. Orkestr provides the runtime, the controls, and the observability to make this loop safe and manageable.

---

**Next:** [What are Tools & MCP?](./what-are-tools) →
