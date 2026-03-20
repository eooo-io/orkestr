# Core Concepts

## The `.agentis/` Directory

Every project managed by Orkestr has a `.agentis/` directory at its root. This directory is the **single source of truth** for all AI skills in that project.

```
my-project/
  .agentis/
    skills/
      code-review.md
      testing-strategy.md
      api-design.md
```

Skills are stored as plain Markdown files with YAML frontmatter. They are human-readable, version-controllable, and portable across machines.

Skills are the building blocks that get composed into agent instructions via the [Agent Compose](./agent-compose) system.

## Skills

A skill is a reusable AI prompt with structured metadata. It consists of two parts:

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

See [Creating Skills](./skills) and the [Skill File Format](/reference/skill-format) for the full specification.

## Agents

Agents are pre-defined roles that combine a base persona with custom per-project instructions and assigned skills. Orkestr ships with 9 agents:

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

## Versions

Every time you save a skill, Orkestr creates a version snapshot. You can browse the full history, compare any two versions side-by-side with a diff viewer, and restore a previous version with one click.

See [Version History](./versions).

## Projects

A project maps to a directory on your filesystem. It holds:

- A collection of skills (stored in `.agentis/skills/`)
- Agent configuration (which agents are active, custom instructions, skill assignments)
- MCP server and A2A agent connections
- Optional settings like `git_auto_commit`

Projects are created and configured in the Filament Admin panel. Day-to-day skill editing and agent execution happens in the React SPA.

## How the Pieces Fit Together

```
You create skills in the Monaco editor
    |
    v
Skills are saved with version snapshots
    |
    v
You assign skills to agents and configure agent roles
    |
    v
Agent Compose merges base + custom instructions + skills
    |
    v
You execute agents in the Playground or via workflows
    |
    v
Execution engine runs the Perceive → Reason → Act → Observe loop
    |
    v
Full traces recorded: inputs, outputs, tool calls, costs
```
