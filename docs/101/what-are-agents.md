# What are Agents?

## The One-Sentence Answer

An agent is an autonomous AI entity with a goal, the ability to reason, tools to act, and a loop that keeps going until the job is done.

## The Analogy: An Employee with a Job Description

Imagine hiring a new team member. You'd give them:

- A **role** — "You're our security auditor"
- A **goal** — "Review every PR for OWASP Top 10 vulnerabilities"
- **Training materials** — Your security checklist, coding standards, past audit reports
- **Tools** — Access to the codebase, the SAST scanner, the issue tracker
- **Authority** — What they can decide on their own vs. what needs approval
- **Judgment** — "If you're unsure, escalate to the security lead"

An Orkestr agent is exactly this — but it's an AI. It has all these pieces defined in a structured format, and Orkestr's execution engine runs it.

## Agents vs. Prompts

Here's the critical distinction:

| Concept | What It Is | Example |
|---|---|---|
| **Prompt** | A static instruction sent to an AI model | "Review this code for bugs" |
| **Skill** | A reusable prompt with metadata | A Markdown file with YAML frontmatter |
| **Agent** | An autonomous entity that loops, uses tools, and pursues a goal | A security auditor that reads files, runs scans, files issues, and keeps going until done |

A prompt is a single shot. An agent is a **loop**.

## The Agent Loop

Every agent follows this cycle:

```
┌──────────────────────────────────────────┐
│                                          │
│   GOAL: "Review PR #42 for security"     │
│                                          │
│   ┌────────────┐                         │
│   │  Perceive  │ ← Read the PR diff      │
│   └─────┬──────┘   Recall past reviews   │
│         │          Check security alerts  │
│         ▼                                │
│   ┌────────────┐                         │
│   │   Reason   │ ← Analyze patterns      │
│   └─────┬──────┘   Apply skill knowledge │
│         │          Plan next action       │
│         ▼                                │
│   ┌────────────┐                         │
│   │    Act     │ ← Read a file (MCP)     │
│   └─────┬──────┘   Run SAST scan (MCP)   │
│         │          Comment on PR (MCP)    │
│         ▼                                │
│   ┌────────────┐                         │
│   │  Observe   │ ← Did I cover all files?│
│   └─────┬──────┘   Any new findings?     │
│         │          Goal met?             │
│         │                                │
│         ▼                                │
│   ┌────────────┐                         │
│   │ Done? ───No──► Back to Perceive      │
│   │      └──Yes──► Return results        │
│   └────────────┘                         │
│                                          │
└──────────────────────────────────────────┘
```

This loop is what makes agents fundamentally different from prompts. An agent doesn't just respond — it **pursues a goal**, using tools and reasoning iteratively until the goal is met or a termination condition is hit.

## The 7 Sections of an Agent Definition

Orkestr defines agents with seven structured sections:

### 1. Identity

Who is this agent? Its name, role, icon, and persona.

```
Name: Security Auditor
Role: security
Persona: Methodical, thorough, security-focused.
         Cites specific CWE IDs and OWASP categories.
         Never dismisses a potential vulnerability.
```

### 2. Goal

What is it trying to accomplish?

```
Objective: Review code changes for security vulnerabilities
Success Criteria: All files reviewed, all findings documented
Max Iterations: 20
Timeout: 5 minutes
```

### 3. Perception

What inputs does it receive? What context does it pull in?

```
Input Schema: PR diff, file list, commit messages
Memory Sources: Past security reviews, known vulnerability patterns
Context Strategy: Start with the diff, then pull in full files as needed
```

### 4. Reasoning

How does it think?

```
Model: claude-sonnet-4-6
Skills: [security-checklist, owasp-top-10, secure-coding-patterns]
Temperature: 0.2 (low = more focused and deterministic)
Planning Mode: structured (breaks the goal into sub-tasks first)
```

### 5. Actions

What can it do?

```
MCP Tools: filesystem (read files), github (comment on PRs), sast-scanner (run analysis)
A2A Delegation: Can delegate to Code Review Agent for non-security issues
Custom Tools: None
```

### 6. Observation

How does it evaluate its own output?

```
Eval Criteria: Every changed file reviewed, findings include remediation
Output Schema: JSON with findings array, severity levels, CWE IDs
Loop Condition: Continue until all files processed
```

### 7. Orchestration

How does it relate to other agents?

```
Parent Agent: Orchestrator
Can Delegate: Yes (to Code Review Agent)
Autonomy Level: supervised (tool calls need approval above $0.10)
```

## The 9 Pre-Built Agents

Orkestr ships with 9 agents out of the box. Think of them as starter templates:

| Agent | Role | What It Does |
|---|---|---|
| **Orchestrator** | `orchestrator` | Coordinates multi-agent workflows, delegates tasks, synthesizes results |
| **PM Agent** | `project-manager` | Requirements gathering, user stories, sprint planning |
| **Architect Agent** | `architect` | System design, API contracts, technology selection |
| **QA Agent** | `qa` | Test writing, edge case analysis, regression prevention |
| **Design Agent** | `designer` | UI/UX, component specs, accessibility |
| **Code Review Agent** | `code-reviewer` | Code quality, consistency, performance |
| **Infrastructure Agent** | `infrastructure` | Docker, Kubernetes, networking, security hardening |
| **CI/CD Agent** | `cicd` | Pipeline design, deployment strategies |
| **Security Agent** | `security` | OWASP Top 10, vulnerability auditing, secure coding |

You can customize these (add project-specific instructions, assign skills, change models) or create entirely new agents from scratch.

## Agents Are Per-Project

Each project in Orkestr has its own agent configuration:

- **Enable/disable** agents per project (the QA Agent might be enabled for your backend but not your design system)
- **Custom instructions** per project (your QA Agent knows to use Pest PHP in your Laravel project and Jest in your React project)
- **Skill assignments** per project (assign the "TypeScript Standards" skill to the Code Review Agent only in TypeScript projects)

## Autonomy Levels

Not all agents should have the same freedom. Orkestr defines three autonomy levels:

| Level | What It Means | Use Case |
|---|---|---|
| **Autonomous** | Agent acts freely, no approval needed | Low-risk tasks like code formatting |
| **Supervised** | Agent can act, but expensive/risky tool calls need human approval | Code review, testing |
| **Manual** | Every action needs human approval before executing | Production deployments, data migrations |

## Agent Export Formats

Designed an agent in Orkestr? You can export it to external frameworks:

| Format | What It Generates |
|---|---|
| **Claude Agent SDK** | Python code using Anthropic's agent SDK |
| **LangGraph** | Python graph definition for LangChain |
| **CrewAI** | Python agent and task definitions |
| **JSON** | Generic JSON representation |

This means Orkestr is both a runtime *and* a design tool. Build your agent in the visual interface, then export it to your framework of choice — or run it directly in Orkestr.

## Key Takeaway

An agent is not a prompt. It's a complete autonomous entity with a goal, reasoning, tools, and a loop. Orkestr lets you design agents visually, execute them with real tools, and manage them with enterprise controls.

---

**Next:** [The Agent Loop](./the-agent-loop) →
