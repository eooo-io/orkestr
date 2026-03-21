# Git Auto-Commit

Orkestr can automatically commit skill file changes to your project's git repository after each save. This creates a clean commit history of prompt changes over time.

## Enabling Git Auto-Commit

The `git_auto_commit` setting is a per-project boolean. Enable it in one of two ways:

1. **Filament Admin** -- Edit the project and toggle the "Git Auto-Commit" checkbox
2. **API** -- Include `"git_auto_commit": true` when creating or updating a project

::: info
The project directory must be an initialized git repository for auto-commit to work. Orkestr does not run `git init` for you.
:::

## What Gets Committed

When you save a skill with auto-commit enabled, Orkestr:

1. Writes the skill file to `.orkestr/skills/{slug}.md`
2. Stages that specific file with `git add`
3. Creates a commit with a descriptive message

Only the changed skill file is committed -- not the entire `.orkestr/` directory or any provider output files.

## Viewing Git History

Orkestr exposes git log and diff endpoints for projects:

```
GET /api/projects/{id}/git-log?file=.orkestr/skills/my-skill.md
GET /api/projects/{id}/git-diff?file=.orkestr/skills/my-skill.md&ref=abc1234
```

The `git-log` endpoint returns commit history for a specific file (or the whole `.orkestr/` directory if no file is specified). Each entry includes the commit hash, author, date, and message.

The `git-diff` endpoint shows the diff between the current file and a specific commit ref.

## Commit Scope

Auto-commits are scoped to individual skill saves. If you save three skills in quick succession, you get three separate commits. This makes it easy to trace which change affected which skill.

Provider sync output files (`.claude/CLAUDE.md`, `.cursor/rules/`, etc.) are **not** auto-committed. You can commit those separately with your own git workflow, or add them to `.gitignore` if you prefer to regenerate them on demand.

::: tip
Since provider output files are fully derived from `.orkestr/skills/`, many teams choose to gitignore them and regenerate via sync after checkout.
:::

## When Auto-Commit Is Off

With auto-commit disabled, Orkestr still writes skill files to disk -- it just does not run any git commands. You manage version control yourself using your normal git workflow.

The [Version History](./versions) feature works independently of git. Versions are stored in the database regardless of the auto-commit setting.
