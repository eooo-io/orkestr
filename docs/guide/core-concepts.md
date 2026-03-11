# Core Concepts

## The `.agentis/` Directory

Every project managed by Agentis Studio has a `.agentis/` directory at its root. This directory is the **single source of truth** for all AI skills in that project.

```
my-project/
  .agentis/
    skills/
      code-review.md
      testing-strategy.md
      api-design.md
```

Skills are stored as plain Markdown files with YAML frontmatter. They are human-readable, version-controllable, and portable across machines.

All provider-specific config files (`.claude/CLAUDE.md`, `.cursor/rules/*.mdc`, etc.) are **derived outputs**. You never edit them directly -- Agentis Studio generates them from `.agentis/skills/` when you run a sync.

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

Agents are pre-defined roles that combine a base persona with custom per-project instructions and assigned skills. Agentis Studio ships with 9 agents:

- **Orchestrator** -- Coordinates multi-agent workflows
- **PM Agent** -- Requirements, user stories, planning
- **Architect Agent** -- System design, API contracts
- **QA Agent** -- Testing, edge cases, code review
- **Design Agent** -- UI/UX, accessibility, design systems
- **Code Review Agent** -- Code quality, security, performance
- **Infrastructure Agent** -- Docker, Kubernetes, DevOps
- **CI/CD Agent** -- GitHub Actions, GitLab CI pipelines
- **Security Agent** -- OWASP Top 10, vulnerability auditing

You enable agents per project, add custom instructions, and assign skills. The [Agent Compose](./agent-compose) system merges everything into a single output that gets included in provider sync.

## Provider Sync

Provider sync takes your skills and composed agents and writes them into the native config format that each AI coding assistant expects.

| Provider | Output Path | Format |
|---|---|---|
| Claude | `.claude/CLAUDE.md` | All skills under H2 headings |
| Cursor | `.cursor/rules/{slug}.mdc` | One MDC file per skill |
| GitHub Copilot | `.github/copilot-instructions.md` | All skills concatenated |
| Windsurf | `.windsurf/rules/{slug}.md` | One file per skill |
| Cline | `.clinerules` | Single flat file |
| OpenAI | `.openai/instructions.md` | All skills concatenated |

Sync is always explicit -- it never runs automatically when you save a skill. This gives you full control over when changes propagate to provider configs.

See [Provider Sync](./provider-sync) for details on configuring and running syncs.

## Versions

Every time you save a skill, Agentis Studio creates a version snapshot. You can browse the full history, compare any two versions side-by-side with a diff viewer, and restore a previous version with one click.

See [Version History](./versions).

## Projects

A project maps to a directory on your filesystem. It holds:

- A collection of skills (stored in `.agentis/skills/`)
- Provider configuration (which providers are enabled)
- Agent configuration (which agents are active, custom instructions, skill assignments)
- Optional settings like `git_auto_commit`

Projects are created and configured in the Filament Admin panel. Day-to-day skill editing happens in the React SPA.

## How the Pieces Fit Together

```
You edit skills in the React SPA (Monaco editor)
    |
    v
Skills are saved to the database AND written to .agentis/skills/*.md
    |
    v
You click "Sync" (or "Preview Sync" first)
    |
    v
ProviderSyncService reads all skills + composes all enabled agents
    |
    v
Each enabled provider driver writes its native config format
    |
    v
Your AI coding assistant picks up the updated config
```
