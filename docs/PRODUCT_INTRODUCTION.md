# Orkestr — Product Introduction

> Self-hosted Agent OS — design, execute, and manage autonomous AI agents on your own infrastructure.

---

## What Orkestr Is

Orkestr is a self-hosted platform for building and operating AI agent systems. You design agents, give them tools and instructions, wire them into teams, and run them — all from your own infrastructure, with any combination of cloud and local models.

It is built in three layers, each one building on the layer below:

```
┌─────────────────────────────────────────────┐
│  Orchestration                              │
│  Workflows, agent teams, goal decomposition │
├─────────────────────────────────────────────┤
│  Agents                                     │
│  Autonomous loops, tools, memory, execution │
├─────────────────────────────────────────────┤
│  Components                                 │
│  Skills, models, templates, provider sync   │
└─────────────────────────────────────────────┘
```

You can use any single layer on its own. You can also use all three together. The platform scales with your ambition.

---

## Layer 1: Components — The Building Blocks

At the foundation, Orkestr gives you a structured way to manage the instructions and configuration that power AI systems.

### Skills

A **skill** is a reusable AI instruction — a Markdown file with YAML metadata. Skills are the atomic unit of knowledge in Orkestr.

```markdown
---
name: API Response Format
description: Standardize JSON API responses
tags: [api, standards]
model: claude-sonnet-4-6
---

All API endpoints must return responses in this structure:
- Success: { "data": ..., "meta": { "timestamp": "...", "request_id": "..." } }
- Error: { "error": { "code": "...", "message": "...", "details": [...] } }
```

Skills are stored in `.agentis/skills/` — plain files you can version-control, share, and compose. They support includes (one skill embedding another), template variables (`{{language}}`), and dependency resolution.

### Multi-Model Access

Orkestr routes to any model from any provider — Anthropic (Claude), OpenAI, Google Gemini, Grok (xAI), 200+ models via OpenRouter, and local models through Ollama, vLLM, or TGI. No vendor lock-in. Swap models by changing a config line.

### Provider Sync

Skills can be delivered to AI coding tools (Claude, Cursor, Copilot, Windsurf, Cline, OpenAI) in each tool's native format. Write once in `.agentis/`, sync to seven providers. This is how Orkestr bridges the gap between your agent definitions and your everyday development workflow — one source of truth for all AI instructions.

### Skill Tooling

Skills are authored in a Monaco editor with syntax highlighting, live token counting, and keyboard shortcuts. Every save creates a version snapshot with full diff history. A built-in linter catches quality issues. A test runner lets you try skills against real models before deploying them.

---

## Layer 2: Agents — Autonomous Entities

Skills tell an AI *what to do*. Agents tell an AI *how to operate*. An agent is a complete autonomous entity with a goal, tools, memory, and a decision loop.

### The Agent Loop

Every agent in Orkestr follows the same cycle:

**Goal → Perceive → Reason → Act → Observe → (repeat or stop)**

The agent reads its context, thinks about what to do, takes an action (calling a tool, generating output, delegating to another agent), evaluates the result, and decides whether to continue or stop. This isn't a single prompt-response — it's an autonomous loop that runs until the job is done.

### Agent Definition

Each agent has a structured definition covering six dimensions:

| Dimension | What It Defines | Example |
|---|---|---|
| **Identity** | Name, role, persona | "Security Auditor — methodical, thorough" |
| **Goal** | Objective, success criteria, max iterations | "Find all OWASP Top 10 vulnerabilities" |
| **Perception** | Input schema, memory sources, context | "Read PR diff + project security history" |
| **Reasoning** | Model, skills, temperature, planning mode | "Use Claude Opus, apply security-checklist skill" |
| **Actions** | MCP tools, A2A delegation, custom tools | "Can read files, query SAST database, delegate to QA" |
| **Observation** | Eval criteria, output schema, loop condition | "Stop when all files reviewed and findings documented" |

This is where skills become part of something larger. Skills are the instructions an agent carries; the agent definition determines *how* and *when* those instructions are applied.

### Tools and Connections

Agents interact with the real world through two protocols:

- **MCP (Model Context Protocol)** — Connects agents to tools: file systems, databases, APIs, shell commands, knowledge bases. An agent without tools is just a text generator.
- **A2A (Agent-to-Agent)** — Lets agents delegate tasks to other agents and receive results. This is how agent teams coordinate.

### Memory

Agents maintain persistent memory across sessions:

- **Conversation memory** — What happened in the current interaction
- **Working memory** — Task-relevant context that persists across turns
- **Long-term memory** — Knowledge accumulated over time, available across sessions

### Execution Engine

Orkestr has a built-in runtime. Agents execute inside the platform with real tool calls, real model invocations, and full observability. Every step is traced: inputs, outputs, tool calls, token usage, cost breakdown, and elapsed time.

---

## Layer 3: Orchestration — Agents Working Together

Real-world problems rarely need a single agent. A code change might need an architect to plan, a developer to implement, a reviewer to check quality, and a security auditor to scan for vulnerabilities.

### Workflows

The workflow builder lets you arrange agents into multi-step pipelines:

- **Agent steps** — Run a specific agent with a specific goal
- **Conditional routing** — Send work down different paths based on results
- **Parallel execution** — Run independent agents simultaneously
- **Checkpoints** — Pause for human review before proceeding
- **Shared context** — A context bus that passes data between agents

### Visual Canvas

The canvas is a WYSIWYG composition surface where you drag agents, skills, MCP servers, and A2A connections onto a shared workspace. Draw delegation edges between agents. Edit configuration inline. Run workflows directly from the canvas.

### Schedules and Triggers

Agents and workflows can run on cron schedules, webhook triggers, or event-driven rules. Set up a security scanner that runs nightly, a code reviewer that triggers on every PR, or a compliance agent that monitors changes continuously.

Workflows can be exported to popular orchestration formats (LangGraph, CrewAI) or run directly inside Orkestr.

---

## Safety and Governance

Agent systems need guardrails. Orkestr provides them at every layer:

- **Prompt linting** — Catches quality issues in skills: vague instructions, contradictory directives, missing output format specifications
- **Execution guardrails** — Budget limits (per-agent, per-run, daily), tool allowlists, output content scanning, approval gates
- **Organization policies** — Cascading safety policies from organization → project → agent. What's blocked at the org level is blocked everywhere beneath it.
- **Content review** — AI-powered content analysis for skills, with transparency reports

The philosophy: **warn loudly, block rarely, log everything.** Full freedom with full visibility.

---

## Platform Capabilities

These features support all three layers:

### Organization and Discovery

- **Tags** — Color-coded labels for skills, filterable across projects
- **Command Palette** — `Ctrl+K` to search skills, projects, agents, and pages instantly
- **Cross-Project Search** — Full-text search across every skill in every project
- **Visualization** — Interactive graphs showing how skills, agents, and providers connect
- **Bulk Operations** — Multi-select skills for tagging, assignment, move, or deletion

### Sharing and Reuse

- **Skill Library** — 25 pre-built skills across Laravel, PHP, TypeScript, FinTech, DevOps, and Technical Writing
- **Skills.sh Import** — Discover and import skills from GitHub repositories following the skills.sh convention
- **Bundles** — Export curated sets of skills and agents as portable archives (ZIP or JSON)
- **Repository Connections** — Sync skills with GitHub or GitLab repositories

### Infrastructure

- **Self-Hosted** — Runs entirely on your infrastructure. Your data, your keys, your control.
- **Air-Gap Mode** — Run 100% offline with local models. Zero external network calls.
- **Deployment Elasticity** — Same codebase runs cloud-only, hybrid, or fully air-gapped. Switching is a config change, not an architecture change.

---

## How the Layers Relate

The three layers are not separate products — they are a hierarchy where each layer builds on the one below:

```
Skills feed into Agents.
   Skills are the instructions an agent carries. When you assign skills
   to an agent, they become part of its reasoning capability.

Agents feed into Orchestration.
   Agents are the workers in a workflow. Each workflow step runs an agent
   with a specific goal. The orchestration layer coordinates them.

Provider Sync bridges Components to external tools.
   Skills can also be synced to AI coding assistants (Claude, Cursor, etc.)
   independent of agents — this is how the Component Layer serves developers
   who just want consistent AI instructions across their team's tools.
```

You can start at any layer:

- **Just want consistent AI instructions?** Use the Component Layer. Create skills, sync to providers. Done.
- **Want autonomous agents?** Use Components + Agents. Define agents with goals, tools, and memory. Run them.
- **Want coordinated agent teams?** Use all three layers. Build workflows that chain agents with conditions, checkpoints, and shared context.

---

## The CLI

For developers who prefer the terminal:

- `agentis:list` — See all projects and their skills at a glance
- `agentis:scan` — Scan a project directory for skill files
- `agentis:sync` — Sync skills to provider configs without opening the UI
- `agentis:import` — Import existing provider config files into Orkestr format (reverse-sync)

The import command is particularly useful for onboarding: point it at a project that already has `.claude/CLAUDE.md` or `.cursor/rules/`, and it will parse those files back into portable skills. No manual migration needed.

---

## Summary

| Layer | What It Provides | Key Capabilities |
|---|---|---|
| **Components** | Building blocks | Skills, multi-model access, provider sync, templates, includes |
| **Agents** | Autonomous entities | Agent loop, tool use (MCP), delegation (A2A), memory, execution |
| **Orchestration** | Multi-agent coordination | Workflows, canvas, schedules, parallel execution, checkpoints |
| **Platform** | Supporting infrastructure | Guardrails, library, bundles, search, air-gap mode |

Orkestr is an agent operating system. Skills are its instruction set. Agents are its processes. Workflows are its job scheduler. Guardrails are its security model. And it all runs on your hardware, with your models, under your control.
