# Provider Sync

Provider sync is a [Component Layer](/guide/core-concepts#component-layer) feature that takes your skills and composed agent instructions and writes them into the native config format for each AI coding assistant. This is how Orkestr bridges the gap between your portable `.orkestr/` skills and the provider-specific files your tools actually read — one source of truth, seven output formats.

## Supported Providers

| Provider | Output Path | Format | Notes |
|---|---|---|---|
| Claude | `.claude/CLAUDE.md` | Single file, skills under H2 headings | Used by Claude Code |
| Cursor | `.cursor/rules/{slug}.mdc` | One MDC file per skill | Cursor Rules format |
| GitHub Copilot | `.github/copilot-instructions.md` | Single file, all skills concatenated | Copilot custom instructions |
| Windsurf | `.windsurf/rules/{slug}.md` | One Markdown file per skill | Windsurf Rules |
| Cline | `.clinerules` | Single flat file | Cline system prompt |
| OpenAI | `.openai/instructions.md` | Single file, all skills concatenated | OpenAI custom instructions |

## Configuring Providers

Providers are configured per project in the React SPA Settings (http://localhost:5173). Edit a project and check the providers you want to enable.

Each provider is stored as a row in the `project_providers` table with the provider key (e.g., `claude`, `cursor`) and an enabled flag.

## Running a Sync

From the project detail page in the React SPA, click **Sync**. This:

1. Reads all skills for the project
2. Resolves [includes](./includes) and [template variables](./templates) for each skill
3. Composes all enabled [agents](./agent-compose)
4. Calls each enabled provider's sync driver
5. Writes the output files to the project directory

::: warning
Sync overwrites the provider config files completely. Any manual edits to files like `.claude/CLAUDE.md` or `.cursor/rules/*.mdc` will be lost. The `.orkestr/` directory is the source of truth -- always edit there.
:::

## What Gets Synced

The sync output includes:

- **Individual skills** -- Each skill's resolved body (with includes and template variables expanded)
- **Composed agents** -- Each enabled agent's composed output (base + custom + assigned skills)

Skills and agents are ordered by their sort order / name within each provider's output format.

## Provider-Specific Details

### Claude

All skills are written to a single `.claude/CLAUDE.md` file. Each skill becomes an H2 section:

```markdown
## Code Review Standards

[resolved body of code-review skill]

## Testing Strategy

[resolved body of testing-strategy skill]
```

### Cursor

Each skill gets its own `.mdc` file in `.cursor/rules/`. The file uses Cursor's MDC frontmatter format:

```markdown
---
description: Code Review Standards
---

[resolved body]
```

### GitHub Copilot

All skills are concatenated into a single `.github/copilot-instructions.md` file, separated by H2 headings:

```markdown
## Code Review Standards

[resolved body of code-review skill]

## Testing Strategy

[resolved body of testing-strategy skill]
```

This file is read by GitHub Copilot as custom instructions for the repository.

### Windsurf

Each skill gets its own `.md` file in `.windsurf/rules/`:

```
.windsurf/
  rules/
    code-review.md
    testing-strategy.md
```

Each file contains the skill's resolved body as plain Markdown, without any frontmatter. Windsurf reads all files in the `rules/` directory.

### Cline

Everything is written to a single `.clinerules` file as plain text. Skills are separated by headings:

```
## Code Review Standards

[resolved body]

## Testing Strategy

[resolved body]
```

Cline reads this file as additional system prompt context.

### OpenAI

All skills are concatenated into `.openai/instructions.md` with H2 headings separating each skill, following the same format as Copilot. This is used by OpenAI's custom instructions feature in ChatGPT and the API.

## Preview Before Syncing

Use the [Diff Preview](./diff-preview) feature to see exactly what will change before writing files. Click **Preview Sync** instead of **Sync** to open the diff modal.
