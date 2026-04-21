# Skill Editor

The Skill Editor is where you author, test, lint, review, and manage individual skills. It opens when you click a skill card on the project detail page or navigate to `/skills/:id`.

## Layout

The editor is split into two panels:

- **Left panel** -- Frontmatter form, status banner stack, Monaco code editor
- **Right panel** -- Tabs for testing and management

The left panel is where you write. The right panel is where you validate.

## The banner stack

Between the frontmatter form and the Monaco editor, the editor mounts a stack of compact status strips. Each hides when it has nothing to say, so on a clean skill you see none of them; on a problematic one, several may appear.

| Banner | Source | When it shows |
|---|---|---|
| [`StalenessBanner`](./staleness) | `SkillStalenessService` | When the skill's `tuned_for_model` is missing, deprecated, or diverges from its last validated model |
| `RegressionGateBanner` | [`SkillEvalGateService`](./eval-gates) | When an eval gate is configured and runs are queued, running, or outside the fail-threshold delta |
| `InlineGotchaStrip` | `SkillGotcha` records | When the skill has open (unresolved) gotchas |

Follow-up banners tracked in [#561](https://github.com/eooo-io/orkestr/issues/561) (`ProposalBanner`) and [#562](https://github.com/eooo-io/orkestr/issues/562) (lineage strip).

## Frontmatter Form

The form at the top of the left panel contains all metadata fields:

| Field | Type | Required | Description |
|---|---|---|---|
| `name` | string | Yes | Display name of the skill |
| `description` | string | No | Short summary of what the skill does |
| `model` | string | No | Target LLM model (e.g., `claude-sonnet-4-6`, `gpt-5.4`) |
| `max_tokens` | number | No | Maximum output tokens for test and playground |
| `tags` | string[] | No | Tags for categorization and filtering |
| `tools` | object[] | No | Tool/function definitions (JSON) |
| `includes` | string[] | No | Slugs of other skills to compose via [includes](./includes) |
| `template_variables` | object[] | No | [Template variable](./templates) definitions with names and defaults |

Only `name` is required. The slug is auto-generated from the name and must be unique within the project.

### Example Frontmatter

When saved to disk as `.orkestr/skills/code-review.md`, the YAML frontmatter looks like:

```yaml
---
id: code-review
name: Code Review Standards
description: Enforces team coding standards during review
tags: [code-review, standards]
model: claude-sonnet-4-6
max_tokens: 2000
tools: []
includes: [base-instructions]
template_variables:
  - name: language
    description: Primary programming language
    default: TypeScript
created_at: 2026-01-15T09:00:00Z
updated_at: 2026-03-09T14:22:00Z
---
```

## Monaco Editor

The body is written in Markdown using the Monaco code editor (the same editor that powers VS Code). Features include:

- Syntax highlighting for Markdown
- Word wrap enabled by default
- Line numbers
- `Ctrl+S` / `Cmd+S` to save
- Unsaved changes indicator and navigation guard

Write the body as you would any AI prompt -- use headings, lists, code blocks, and emphasis to structure instructions clearly.

::: tip
A live token counter appears in the frontmatter form. It uses a character-based approximation (~1 token per 4 characters) and is color-coded green/yellow/red against the model's context limit.
:::

## Right Panel Tabs

The right panel contains seven tabs. These are only available after saving the skill for the first time.

### Test

The live test runner sends the skill's prompt to the configured LLM model and streams the response back in real time using Server-Sent Events (SSE). You can provide test input, select a model override, and see the streamed output as it arrives.

See [Test Runner](./test-runner) for details on multi-model testing.

### Versions

Every save creates an automatic version snapshot. The Versions tab shows a chronological list of all snapshots with timestamps. Click any version to see a side-by-side diff comparing it to the current state. Click **Restore** to revert the skill to that version.

See [Version History](./versions) for the full diff viewer.

### Lint

The prompt linter analyzes the skill body against 8 quality rules and surfaces actionable suggestions:

1. **Length check** -- flags prompts that are too short to be useful
2. **Specificity** -- detects vague or generic instructions
3. **Structure** -- encourages use of headings and sections
4. **Examples** -- suggests adding examples for clarity
5. **Ambiguity** -- flags words like "maybe", "perhaps", "try to"
6. **Conflicting instructions** -- detects contradictory directives
7. **Role clarity** -- checks for a clear persona or role definition
8. **Output format** -- suggests specifying expected output format

Each rule produces a severity (info, warning, error) and a plain-language suggestion for improvement. See [Prompt Linting](./linting) for rule details.

### Security

The security scanner analyzes the skill for potential risks:

- Prompt injection vulnerabilities
- Sensitive data exposure patterns
- Overly permissive tool access
- Unsafe system prompt patterns

Each finding includes a severity score (low, medium, high, critical) and a description of the risk. Results are displayed as a scrollable list with color-coded badges.

::: warning
The security scanner is a heuristic analysis tool. It flags potential risks for human review -- it does not guarantee the skill is safe or unsafe.
:::

### Review

The review workflow lets team members submit skills for approval before they are synced to providers. The Review tab shows:

- Current review status (pending, approved, rejected)
- Reviewer comments and feedback
- Submit for review / approve / reject actions

This is useful in team environments where skill changes should go through a review process before being deployed.

### Tests

The regression test tab lets you define test cases for a skill -- input/output pairs that verify the skill behaves as expected. For each test case you specify:

- **Name** -- descriptive label for the test
- **Input** -- the user message to send
- **Expected output** -- a substring or pattern the response should contain
- **Model** -- which model to use for the test run

Click **Run All** to execute all test cases and see pass/fail results. This is especially useful for catching regressions when editing a skill that is already in production.

### Inherit

The inheritance tab manages parent-child relationships between skills. A child skill inherits the body of its parent and can override or extend it. The tab shows:

- Current parent skill (if any)
- Child skills that inherit from this skill
- A resolved preview showing the final composed body after inheritance

::: tip
Inheritance is different from [includes](./includes). Includes compose multiple independent skills together. Inheritance creates a parent-child chain where the child extends the parent.
:::

## Saving

Press `Ctrl+S` (or `Cmd+S` on macOS) to save. Every save:

1. Updates the skill in the database
2. Writes the `.orkestr/skills/{slug}.md` file to disk
3. Creates a new version snapshot
4. Optionally auto-commits to git if enabled

## Action Bar

The action bar above the editor provides:

- **Save** -- save the current skill (also `Ctrl+S`)
- **Generate** -- open the AI generation modal to rewrite or augment the skill
- **Duplicate** -- clone the skill with a `-copy` suffix
- **Delete** -- permanently remove the skill and all its versions

## Next Steps

- [Test Runner](./test-runner) -- SSE streaming test details
- [Prompt Linting](./linting) -- the 8 quality rules
- [Version History](./versions) -- diff viewer and restore
- [Includes & Composition](./includes) -- recursive skill composition
- [Template Variables](./templates) -- `{{variable}}` substitution
