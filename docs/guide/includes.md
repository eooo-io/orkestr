# Includes and Composition

Skills can include other skills to build composable prompt chains. This lets you define shared foundations (coding standards, project context, output formats) once and reuse them across multiple skills.

## How Includes Work

Add an `includes` array to your skill's frontmatter listing the slugs of skills in the same project:

```yaml
---
name: Review Pull Request
includes: [coding-standards, security-checklist]
---

Review the following pull request for correctness and quality...
```

When this skill is used in a test, sync, or agent compose, the included skills' bodies are **prepended** to this skill's body in the order listed. The result is called the **resolved body**.

The resolved output for the example above would be:

```
[body of coding-standards]

[body of security-checklist]

Review the following pull request for correctness and quality...
```

## Adding Includes in the Editor

In the Skill Editor, the frontmatter sidebar shows an includes picker that lists all other skills in the same project. Toggle skills on or off to add or remove them from the includes list.

When includes are active, the token estimate in the sidebar shows "(resolved)" to indicate it accounts for the full composed body, not just the current skill.

## Recursive Resolution

Includes resolve recursively. If skill A includes skill B, and skill B includes skill C, then skill A's resolved body contains C, then B, then A.

```
A includes [B]
B includes [C]

Resolved body of A = C body + B body + A body
```

## Circular Dependency Detection

The resolver detects circular dependencies and breaks the cycle with an HTML comment:

```
<!-- Circular include skipped: skill-slug -->
```

If skill A includes B and B includes A, the second inclusion is skipped to prevent infinite recursion.

## Depth Limit

The maximum include depth is **5 levels**. If the chain goes deeper than 5, resolution stops and inserts:

```
<!-- Include depth limit exceeded -->
```

This prevents runaway chains while allowing reasonable composition depth.

## Missing Includes

If an included slug does not exist in the project, the resolver inserts:

```
<!-- Include not found: missing-slug -->
```

The rest of the resolution continues normally.

## Where Resolved Bodies Are Used

The resolved body (with all includes prepended) is used in:

- **Provider sync** -- Each skill's resolved body is written to provider config files
- **Test runner** -- The Test tab sends the resolved body as the system prompt
- **Agent compose** -- When a skill is assigned to an agent, its resolved body is used
- **Playground** -- When selecting a skill as system prompt source

The raw (unresolved) body is what you see in the Monaco editor and what gets saved to `.agentis/skills/{slug}.md`.

## Example: Layered Prompts

A common pattern is to define a base context skill and include it in multiple specialized skills:

**`project-context`** (shared base):
```markdown
---
name: Project Context
---

This project is a Laravel 12 + React SPA. The backend uses PHP 8.4 with
Pest for testing. The frontend uses TypeScript, Tailwind CSS v4, and shadcn/ui.
Database is MariaDB 11.
```

**`write-tests`** (includes base):
```markdown
---
name: Write Tests
includes: [project-context]
---

Write Pest PHP tests for the given code. Use RefreshDatabase, factories,
and assertJsonStructure for API tests.
```

**`write-migration`** (includes base):
```markdown
---
name: Write Migration
includes: [project-context]
---

Create a Laravel migration for the described schema change. Include
both up() and down() methods.
```

Both specialized skills automatically inherit the project context without duplicating it.
