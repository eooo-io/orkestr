# What are Skills?

## The One-Sentence Answer

A skill is a reusable set of instructions — written in Markdown with structured metadata — that teaches an AI agent how to do something specific.

## The Analogy: Recipe Cards

A cookbook is a collection of recipe cards. Each card has:

- **Title:** Chocolate Chip Cookies
- **Prep time:** 15 minutes
- **Tags:** dessert, baking, quick
- **Instructions:** Preheat oven to 375°F. Mix flour, sugar...

A skill is the same structure, but instead of teaching a human to bake, you're teaching an AI agent how to behave. Skills are the **smallest unit of intelligence** in Orkestr — the atoms that everything else is built from.

## What a Skill Looks Like

```markdown
---
name: Security Audit Checklist
description: OWASP Top 10 review process for pull requests
tags: [security, review, owasp]
model: claude-sonnet-4-6
max_tokens: 4096
---

When auditing code for security vulnerabilities, systematically check:

## Injection Flaws
- SQL injection: Are all queries parameterized?
- Command injection: Is user input ever passed to shell commands?
- XSS: Is output properly escaped in HTML contexts?

## Authentication & Session
- Are passwords hashed with bcrypt/argon2?
- Are session tokens regenerated after login?
- Is there rate limiting on auth endpoints?

## Data Exposure
- Are API responses filtered to exclude sensitive fields?
- Is PII logged or exposed in error messages?
- Are secrets stored in environment variables, not code?

For each finding, provide:
1. The exact file and line number
2. The vulnerability category (CWE ID if applicable)
3. A specific remediation with code example
```

## The Two Parts of a Skill

### 1. Frontmatter — The Metadata

The section between `---` markers at the top. Written in YAML. This is *about* the skill — it doesn't change what the AI does, but it controls how Orkestr manages and routes the skill.

```yaml
---
name: Security Audit Checklist      # Display name (required)
description: OWASP Top 10 review    # Short summary
tags: [security, review]             # For organizing and filtering
model: claude-sonnet-4-6            # Which AI model to use
max_tokens: 4096                     # Max response length
tools: []                            # Functions the agent can call
includes: [base-instructions]        # Other skills to compose in
template_variables:                  # Fill-in-the-blank placeholders
  - name: language
    default: English
---
```

::: details Full frontmatter field reference
| Field | Type | Required | What It Does |
|---|---|---|---|
| `name` | string | **Yes** | Display name in the UI and provider sync |
| `description` | string | No | Shown in lists, search results, and library |
| `tags` | string[] | No | Categorization for filtering and organization |
| `model` | string | No | Which AI model to use when testing/running |
| `max_tokens` | number | No | Maximum response length |
| `tools` | object[] | No | Tool/function definitions (JSON Schema format) |
| `includes` | string[] | No | Slugs of other skills to compose in |
| `template_variables` | object[] | No | `{{variable}}` placeholder definitions |
:::

### 2. Body — The Instructions

Everything after the frontmatter. This is the actual text the AI agent reads and follows. Written in Markdown — headings, lists, code blocks, tables, emphasis — whatever structure helps communicate the instructions clearly.

The body is where the real intelligence lives. A well-written skill body turns a generic AI model into a domain expert that follows your team's exact conventions.

## Why Skills Matter in an Agent OS

Skills are the knowledge layer of the agent stack. Here's how they fit:

```
Agent Definition
├── Identity (who am I?)
├── Goal (what am I trying to do?)
├── Reasoning
│   ├── Model (which AI brain?)
│   └── Skills ◄── THIS IS WHERE SKILLS LIVE
│       ├── security-checklist.md
│       ├── coding-standards.md
│       └── api-design-guide.md
├── Actions (MCP tools, A2A delegation)
└── Observation (how do I evaluate my output?)
```

An agent without skills is like an employee with a job title but no training manual. Skills are the training manual.

## Skills Can Compose

Skills aren't isolated — they can build on each other:

### Includes

A skill can pull in other skills. Your "Full Code Review" skill might include:

```yaml
includes: [coding-standards, security-checklist, test-coverage-rules]
```

When Orkestr resolves this skill, it prepends the bodies of all included skills, in order. Change `coding-standards` once, and every skill that includes it gets the update.

Includes are recursive (up to 5 levels deep) with circular dependency detection.

### Template Variables

Skills can have `{{placeholders}}` that get filled in per-project:

```markdown
---
name: API Standards
template_variables:
  - name: framework
    description: The backend framework
    default: Laravel
  - name: auth_method
    description: Authentication approach
    default: session-based
---

When designing APIs for this {{framework}} project:
- Use {{auth_method}} authentication
- Follow RESTful naming conventions
```

Different projects fill in different values — same skill, adapted to each context.

## Real-World Skill Examples

| Skill Name | What It Teaches the Agent |
|---|---|
| TypeScript Standards | Your team's exact coding style rules |
| API Design Guide | How to design RESTful endpoints in your codebase |
| Database Migration Rules | How to write safe, reversible schema changes |
| Error Handling Patterns | Specific try/catch patterns, error classes, logging conventions |
| React Component Guide | How to structure components, hooks, and state management |
| Security Checklist | OWASP Top 10 review process |
| Git Commit Format | Conventional commits, PR description format |
| Accessibility Rules | WCAG compliance requirements |
| Incident Response | How to diagnose and respond to production alerts |
| Architecture Decision Records | How to document technical decisions |

## How Skills Are Stored

On disk, skills are plain `.md` files in the `.agentis/` directory:

```
my-project/
  .agentis/
    skills/
      security-checklist.md
      coding-standards.md
      api-design-guide.md
```

The filename is the "slug" — a URL-safe version of the name. `Security Audit Checklist` becomes `security-audit-checklist.md`.

These files are human-readable, version-controllable (they live in your git repo), and portable across machines.

## The Skill Lifecycle

```
Created (in editor or via AI generation)
    │
    ▼
Edited (refined with real-world feedback)
    │
    ▼
Tested (playground, multi-model comparison)
    │
    ├──► Assigned to agents (becomes part of agent reasoning)
    │
    ├──► Synced to providers (Claude, Cursor, Copilot, etc.)
    │
    └──► Exported as bundles (shared with teammates)
```

Every save creates a version snapshot. You can view diffs, compare versions, and restore any previous version with one click.

## Key Takeaway

Skills are the knowledge atoms of Orkestr. They encode your team's expertise into portable, composable, version-controlled instruction sets that power both AI agents and AI coding tools.

---

**Next:** [What are Agents?](./what-are-agents) →
