# Agentis Studio — Product Introduction

> The control center for AI-assisted development.

---

## The Problem

AI coding assistants are everywhere. Claude, Cursor, GitHub Copilot, Windsurf, Cline, OpenAI — each one powerful on its own, each one with its own configuration format, its own rules directory, its own way of understanding what you want.

This creates three problems that get worse the more you use AI in your workflow:

### 1. Fragmentation

Every AI assistant stores its instructions differently. Claude reads from `.claude/CLAUDE.md`. Cursor reads `.cursor/rules/`. Copilot reads `.github/copilot-instructions.md`. If you use more than one — or if different members of your team prefer different tools — you're maintaining the same instructions in multiple places, in multiple formats, with no guarantee they stay in sync.

When instructions drift apart, the AI assistants start behaving inconsistently. The Claude user gets one style of code review. The Cursor user gets another. Nobody knows which version is current.

### 2. No Reuse Across Projects

You've written a great set of instructions for how your team handles error logging, or how API responses should be structured, or what your code review standards are. Now you start a new project. You copy-paste those instructions, maybe tweak them, and six months later you have fifteen slightly different versions of the same guidance scattered across your repositories.

There's no library. No versioning. No way to update a skill once and have it propagate everywhere it's used.

### 3. AI Configuration Is Invisible

Most teams have no visibility into what instructions their AI assistants are actually following. There's no diff preview before syncing changes. No version history to roll back to. No way to search across projects for a specific instruction. No linting to catch vague or contradictory prompts. The configuration that shapes every line of AI-generated code lives in flat files that nobody reviews.

---

## What Agentis Studio Does

Agentis Studio gives you a single place to write, organize, test, and distribute the instructions that guide your AI coding assistants.

### Write Once, Sync Everywhere

You define your AI instructions — we call them **skills** — in a simple, portable format: a Markdown file with a YAML header. One file per skill, stored in a `.agentis/` directory in your project.

```markdown
---
name: API Response Format
description: Standardize JSON API responses across the project
tags: [api, standards]
model: claude-sonnet-4-6
---

All API endpoints must return responses in this structure:

- Success: { "data": ..., "meta": { "timestamp": "...", "request_id": "..." } }
- Error: { "error": { "code": "...", "message": "...", "details": [...] } }

Never return raw strings or unstructured objects from API routes.
```

When you sync, Agentis Studio translates that single skill into the native format of every AI assistant your team uses — Claude's Markdown, Cursor's MDC files, Copilot's instructions file, and so on. Seven providers supported, all from one source of truth.

### A Real Editor, Not a Text File

Skills are edited in a full-featured code editor (Monaco, the same engine behind VS Code) with syntax highlighting, live token counting, and keyboard shortcuts. You get an editing experience that matches the importance of what you're writing — these instructions shape every piece of code your AI assistants produce.

### Version Everything

Every time you save a skill, Agentis Studio takes a snapshot. You can view the full history of any skill, compare any two versions side by side in a diff viewer, and restore a previous version with one click. Optionally, skill changes can auto-commit to your project's git repository.

You'll never lose a good prompt to an accidental edit again.

### Test Before You Ship

The built-in test runner lets you try a skill against a real AI model before syncing it to your project. Send a message, see the streamed response, check that the AI follows your instructions. Supports multiple providers — Anthropic (Claude), OpenAI (GPT-4o), Google Gemini, and local models via Ollama — so you can verify behavior across the models your team actually uses.

The Playground goes further: multi-turn conversations, custom system prompts, agent-composed instructions, per-message token counts, and the ability to stop generation mid-stream.

### See What Changes Before It Changes

Before writing a single file to your project, Agentis Studio shows you exactly what will change. The sync preview displays a side-by-side diff for every provider file that would be created, modified, or deleted. Review it, then confirm. No surprises.

---

## Beyond Skills: Agents and Orchestration

Skills are the building blocks. But modern AI development is moving beyond single instructions toward something more structured: **agents**.

### What Is an Agent?

An agent is an AI that doesn't just respond to a single prompt — it pursues a goal through a cycle of thinking and acting. It perceives its situation, reasons about what to do, takes an action (like calling a tool or writing code), observes the result, and repeats until the job is done.

Think of a skill as a single instruction card. An agent is a worker who carries a set of those cards, knows how to use specific tools, and can make decisions about what to do next.

### Designing Agents

Agentis Studio lets you design agents as complete definitions:

- **Identity** — Who is this agent? A code reviewer, a security auditor, a project manager?
- **Goal** — What is it trying to accomplish? What does success look like?
- **Perception** — What information does it have access to? What context does it consider?
- **Reasoning** — Which model does it use? What skills guide its thinking?
- **Actions** — What tools can it use? MCP servers for filesystem access, API calls, database queries. A2A protocol for talking to other agents.
- **Observation** — How does it evaluate its own output? When does it stop?

The Agent Builder provides a visual interface for configuring each of these dimensions, with dedicated editors for JSON schemas, prompt fields, and tool configurations.

### Orchestration: Agents Working Together

Real-world tasks often need more than one agent. A code change might need an architect to plan, a developer to implement, a reviewer to check quality, and a security auditor to scan for vulnerabilities.

Agentis Studio's **workflow builder** lets you arrange agents into multi-step pipelines using a visual canvas:

- **Drag and connect** agents into directed workflows
- **Conditional routing** — send work down different paths based on results
- **Parallel execution** — run independent agents simultaneously
- **Checkpoints** — pause the workflow for human review before proceeding
- **Shared context** — agents pass information forward through a shared context bus

Workflows can be exported to popular orchestration formats (LangGraph, CrewAI) or run directly inside Agentis Studio.

### Running Agents

Agentis Studio includes a lightweight execution engine that brings agent designs to life:

- **Agent execution** — Run an agent with real tool calls, watch the think-act-observe loop unfold in real time
- **Workflow execution** — Execute multi-agent workflows, with live status on each step of the pipeline
- **Tool integration** — Agents connect to external tools through MCP (Model Context Protocol) servers and communicate with other agents via the A2A (Agent-to-Agent) protocol
- **Memory** — Agents can store and retrieve information across conversations, building up working knowledge over time
- **Full observability** — Every execution is traced: see each step, every tool call, token usage, cost breakdown, and elapsed time

---

## Organization and Discovery

As your skill library grows, finding and managing instructions becomes its own challenge. Agentis Studio provides several tools to keep things organized:

- **Tags** — Categorize skills with color-coded labels and filter by them across projects
- **Command Palette** — Press `Ctrl+K` to instantly search across skills, projects, and pages
- **Cross-Project Search** — Full-text search across every skill in every project, with filters for tags, models, and projects
- **Bulk Operations** — Select multiple skills and apply tags, assign to agents, move between projects, or delete in batch
- **Skill Dependencies** — Skills can include other skills, building composable instruction sets. Agentis Studio resolves the full chain and detects circular references
- **Template Variables** — Use `{{variable}}` placeholders in skills with different values per project. Write a skill once, customize it for each codebase
- **Visualization** — Interactive graphs show how skills, agents, and providers connect across your projects

---

## Sharing and Collaboration

### Skill Library

Agentis Studio ships with 25 pre-built skills across categories like Laravel, PHP, TypeScript, FinTech, DevOps, and Technical Writing. Browse the library, preview any skill, and import it into your project with one click.

### Bundles

Export a curated set of skills and agents as a portable bundle (ZIP or JSON). Share it with teammates, import it into another Agentis Studio instance, and handle conflicts with clear options: skip, overwrite, or rename.

### Marketplace

A self-hosted marketplace where you can publish skills for others to discover, browse community-contributed skills with ratings and download counts, and install them directly into your projects.

### Repository Connections

Connect your Agentis Studio projects to GitHub or GitLab repositories. Pull existing AI configurations from your repos, push updated skills back, and keep everything synchronized — scoped to AI configuration files only, so Agentis Studio never touches your source code.

---

## Safety and Guardrails

AI instructions are powerful — and that power needs responsible handling, especially when skills are shared through a marketplace or used in automated agent workflows.

Agentis Studio takes a layered approach to safety:

- **Prompt linting** catches common quality issues: vague instructions, contradictory directives, missing output format specifications
- **Static pattern detection** flags potential security concerns like prompt injection attempts, credential harvesting patterns, or instructions to exfiltrate data
- **Execution guardrails** enforce budget limits, tool allowlists, and output content checks when agents run
- **Marketplace review** applies AI-powered content analysis before skills are published to the marketplace, with transparency reports showing exactly what each skill does

The philosophy is: **warn loudly, block rarely, log everything.** Legitimate users get full freedom with clear visibility. Distribution channels (the marketplace) have appropriate review gates.

---

## How It Fits Into Your Workflow

Agentis Studio is not a replacement for your AI coding assistant. It's the management layer that sits above all of them.

```
Your code editor (VS Code, JetBrains, terminal)
       │
       ▼
AI assistant (Claude, Cursor, Copilot, Windsurf, Cline, OpenAI)
       │
       ▼  reads config files
Agentis Studio output (.claude/, .cursor/, .github/, etc.)
       │
       ▼  synced from
Agentis Studio (.agentis/ — your single source of truth)
```

You keep using whatever AI assistant you prefer. Agentis Studio manages the instructions behind the scenes — making sure they're consistent, versioned, tested, and distributed correctly.

For teams, this means everyone gets the same AI behavior regardless of which editor or assistant they use. For individuals, it means your best prompts are organized, searchable, and portable.

---

## The CLI

For developers who prefer the terminal, Agentis Studio includes command-line tools:

- `agentis:list` — See all projects and their skills at a glance
- `agentis:scan` — Scan a project directory for skill files
- `agentis:sync` — Sync skills to provider configs without opening the UI
- `agentis:import` — Import existing provider config files into Agentis format (reverse-sync)

The import command is particularly useful for onboarding: point it at a project that already has `.claude/CLAUDE.md` or `.cursor/rules/`, and it will parse those files back into portable Agentis skills. No manual migration needed.

---

## Pricing

Agentis Studio is **free for open source projects**. If your project has an OSI-approved license, you get full access to build, test, and sync AI skills — no credit card, no trial expiry.

For commercial projects and teams, paid plans start at $12/month for individuals and $29/seat/month for teams, with unlimited skills, full version history, and marketplace access.

---

## Summary

| Problem | Solution |
|---|---|
| AI instructions fragmented across providers | Write once in `.agentis/`, sync to all 7 providers |
| No reuse across projects | Skill library, bundles, marketplace, template variables |
| No visibility into AI configuration | Version history, diff preview, search, visualization |
| No way to test instructions before deploying | Multi-model test runner and interactive playground |
| Instructions are just text files | Full editor, linting, token estimation, dependency resolution |
| AI agents are hard to design and run | Visual agent builder, workflow canvas, built-in execution engine |
| Team members use different AI assistants | Everyone syncs from the same source of truth |
| Sharing prompts is ad hoc | Marketplace, bundles, and repository connections |
| No safety checks on shared AI instructions | Layered guardrails: linting, pattern detection, execution limits, marketplace review |

Agentis Studio turns the scattered, invisible, unmanaged configuration files that control your AI assistants into a proper engineering discipline — versioned, tested, reviewed, and shared.
