# Orkestr 101 — The Friendly Guide

Welcome to Orkestr 101. This section explains **every concept** in the platform using plain, jargon-free language. No assumptions. No prerequisites. If you've never built an AI agent before — or if you're not entirely sure what an "agent" even is — start here.

::: tip How to Read This Section
Each page answers one question. Read them in order for the full picture, or jump to whatever you're curious about. Every page follows the same pattern: a simple analogy, then a concrete example, then how it works in Orkestr.
:::

## The Big Picture

Orkestr is a **self-hosted Agent Operating System**. It's a platform you run on your own infrastructure that lets you:

1. **Design** AI agents — autonomous entities with goals, reasoning, and tools
2. **Compose** agent teams — wire agents together into orchestrated workflows
3. **Execute** agents — run them with real tool calls, memory, and observability
4. **Manage** everything — guardrails, budgets, schedules, approvals, audit trails

Think of it as the operating system *for* your AI agents — the same way macOS is the operating system for your apps. Orkestr provides the runtime, the security model, the scheduling, the networking (via MCP and A2A), and the management interface.

## The Three Layers

Everything in Orkestr is organized into three layers:

```
┌─────────────────────────────────────────────┐
│  Orchestration Layer                         │
│  Workflows, DAGs, delegation chains,         │
│  human-in-the-loop checkpoints               │
├─────────────────────────────────────────────┤
│  Agent Layer                                 │
│  Goal → Perceive → Reason → Act → Observe   │
│  Each agent is a complete autonomous loop    │
├─────────────────────────────────────────────┤
│  Component Layer                             │
│  Skills, Tools (MCP/A2A), Provider Sync,     │
│  Memory, Schemas                             │
└─────────────────────────────────────────────┘
```

- **Component Layer** — The building blocks. Skills (reusable instructions), tools (MCP servers), connections to other agents (A2A), and the ability to sync your work to AI coding tools.
- **Agent Layer** — Complete autonomous entities. Each agent has a goal, perceives its environment, reasons about what to do, acts using tools, and observes the results — in a loop until the goal is met.
- **Orchestration Layer** — Multi-agent coordination. Workflows chain agents together as directed graphs. One agent's output feeds into the next. Conditions route execution. Checkpoints pause for human approval.

## What You'll Learn

### Foundations

| Page | Question | Analogy |
|---|---|---|
| [What is Orkestr?](./what-is-orkestr) | What does this platform actually do? | An operating system for AI agents |
| [The Three Layers](./the-three-layers) | How is the platform organized? | A building with three floors |
| [What are Skills?](./what-are-skills) | What's the smallest building block? | Recipe cards for AI |
| [What are Agents?](./what-are-agents) | What are these autonomous entities? | Employees with job descriptions |
| [The Agent Loop](./the-agent-loop) | How does an agent actually think and act? | A chef following a recipe, tasting as they go |

### Tools & Communication

| Page | Question | Analogy |
|---|---|---|
| [What are Tools & MCP?](./what-are-tools) | How do agents interact with the world? | Hands and instruments |
| [What is A2A?](./what-is-a2a) | How do agents talk to each other? | Coworkers passing tasks around |
| [What are Workflows?](./what-are-workflows) | How do you chain agents together? | An assembly line |
| [What is the Canvas?](./what-is-the-canvas) | How do you design agent systems visually? | A whiteboard with sticky notes |

### Runtime & Safety

| Page | Question | Analogy |
|---|---|---|
| [What is Execution?](./what-is-execution) | What happens when an agent runs? | Pressing "play" on a machine |
| [What is Agent Memory?](./what-is-agent-memory) | How do agents remember things? | A notebook they carry between tasks |
| [What are Guardrails?](./what-are-guardrails) | How do you keep agents safe? | Guardrails on a highway |
| [What are Schedules?](./what-are-schedules) | How do agents run automatically? | An alarm clock for agents |

### Platform & Ecosystem

| Page | Question | Analogy |
|---|---|---|
| [What are Projects?](./what-are-projects) | How is work organized? | Folders on your desk |
| [What is Provider Sync?](./what-is-provider-sync) | How do skills reach AI coding tools? | Translating a cookbook into 6 languages |
| [What is Multi-Model?](./what-is-multi-model) | Can I use different AI brains? | A multilingual team |
| [What is Air-Gap Mode?](./what-is-air-gap) | Can I run fully offline? | A submarine — self-contained |

## Before You Start

You don't need to be a developer to read this section. But if you want to *use* Orkestr, you'll need:

- A computer with Docker installed (or PHP 8.4 if you prefer running things directly)
- At least one AI model — a cloud API key (Anthropic, OpenAI, etc.) or a local model via Ollama
- A web browser

That's it. Let's go! Start with **[What is Orkestr?](./what-is-orkestr)** →
