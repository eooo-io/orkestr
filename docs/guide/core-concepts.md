# Core Concepts

Orkestr is built in three layers: **Components**, **Agents**, and **Orchestration**. Each layer builds on the one below. This page introduces the key concepts at each layer.

## The Three Layers

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

You can use any layer independently. Start with components and grow into agents and orchestration as your needs evolve.

## Component Layer

### The `.orkestr/` Directory

Every project managed by Orkestr has a `.orkestr/` directory at its root. This directory is the **single source of truth** for all AI skills in that project.

```
my-project/
  .orkestr/
    skills/
      code-review.md
      testing-strategy.md
      api-design.md
```

Skills are stored as plain Markdown files with YAML frontmatter. They are human-readable, version-controllable, and portable across machines.

### Skills

A skill is a reusable AI instruction with structured metadata. It consists of two parts:

1. **Frontmatter** -- YAML metadata (name, description, model, tags, tools, etc.)
2. **Body** -- Markdown text that becomes the system prompt or instruction set

```markdown
---
id: code-review
name: Code Review Standards
description: Enforces team code review conventions
tags: [quality, review]
model: claude-sonnet-4-6
max_tokens: 4096
---

You are a code reviewer. When reviewing pull requests, follow these standards:

- Check for proper error handling
- Verify test coverage for new code paths
- Flag security concerns as high priority
```

Skills are scoped to a project. Slugs (auto-generated from the name) must be unique within a project but can repeat across projects.

Skills can include other skills, use template variables, and be composed into agent instructions. See [Creating Skills](./skills) and the [Skill File Format](/reference/skill-format) for the full specification.

### Provider Sync

Skills can be delivered to AI coding tools in each tool's native format. This is the bridge between Orkestr's component layer and your everyday development workflow -- one source of truth, seven output formats.

### Versions

Every time you save a skill, Orkestr creates a version snapshot. You can browse the full history, compare any two versions side-by-side with a diff viewer, and restore a previous version with one click. See [Version History](./versions).

## Agent Layer

### Agents

Agents are autonomous entities that use skills, tools, and memory to pursue goals. An agent is not just a prompt -- it's a loop: **Goal → Perceive → Reason → Act → Observe → (repeat or stop).**

Orkestr ships with 9 pre-built agents:

- **Orchestrator** -- Coordinates multi-agent workflows
- **PM Agent** -- Requirements, user stories, planning
- **Architect Agent** -- System design, API contracts
- **QA Agent** -- Testing, edge cases, code review
- **Design Agent** -- UI/UX, accessibility, design systems
- **Code Review Agent** -- Code quality, security, performance
- **Infrastructure Agent** -- Docker, Kubernetes, DevOps
- **CI/CD Agent** -- GitHub Actions, GitLab CI pipelines
- **Security Agent** -- OWASP Top 10, vulnerability auditing

You enable agents per project, add custom instructions, and assign skills. The [Agent Compose](./agent-compose) system merges everything into a single deployable prompt for the execution engine.

### How Skills Feed Into Agents

Skills are the instructions an agent carries. When you assign skills to an agent, they become part of its reasoning capability. The same skill can be used by multiple agents, and a single agent can carry multiple skills. This is the primary way the Component Layer feeds the Agent Layer.

### Tools and Connections

- **MCP (Model Context Protocol)** -- Gives agents access to external tools: file systems, databases, APIs, shell commands
- **A2A (Agent-to-Agent)** -- Lets agents delegate tasks to other agents and receive results

## Orchestration Layer

### Workflows

Workflows coordinate multiple agents working together. Each workflow is a directed graph where nodes are agents or decision points.

- **Agent steps** -- Run a specific agent
- **Conditional routing** -- Route based on previous results
- **Parallel execution** -- Run independent agents simultaneously
- **Checkpoints** -- Pause for human review
- **Shared context** -- A context bus that passes data between steps

### Canvas

The visual canvas provides a WYSIWYG surface for composing agent teams. Drag agents, skills, MCP servers, and A2A connections. Draw delegation edges. Run workflows directly from the canvas.

## Projects

A project maps to a directory on your filesystem. It holds:

- A collection of skills (stored in `.orkestr/skills/`)
- Agent configuration (which agents are active, custom instructions, skill assignments)
- MCP server and A2A agent connections
- Workflow definitions
- Optional settings like `git_auto_commit`

Projects are created and configured in the Filament Admin panel. Day-to-day skill editing, agent configuration, and workflow execution happens in the React SPA.

## How the Pieces Fit Together

```
You create skills in the Monaco editor (Component Layer)
    │
    ▼
Skills are saved with version snapshots
    │
    ▼
You assign skills to agents and configure agent roles (Agent Layer)
    │
    ▼
Agent Compose merges base + custom instructions + skills
    │
    ▼
You wire agents into workflows on the Canvas (Orchestration Layer)
    │
    ▼
Execution engine runs agents through the Perceive → Reason → Act → Observe loop
    │
    ▼
Full traces recorded: inputs, outputs, tool calls, costs
```

Separately, skills can also be synced directly to AI coding tools via Provider Sync -- this is a Component Layer feature that works independently of agents and orchestration.
