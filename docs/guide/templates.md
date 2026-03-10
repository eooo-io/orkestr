# Template Variables

Template variables let you write parameterized skills with `{{variable}}` placeholders that resolve to different values per project. This is useful when the same skill applies across projects with different tech stacks, naming conventions, or constraints.

## Defining Template Variables

Add a `template_variables` array to your skill's frontmatter:

```yaml
---
name: Code Style Guide
template_variables:
  - name: language
    description: Primary programming language
    default: TypeScript
  - name: framework
    description: Web framework in use
    default: React
  - name: test_framework
    description: Testing framework
    default: Vitest
---

You are an expert {{language}} developer working with {{framework}}.

When writing tests, use {{test_framework}} and follow these conventions...
```

Each variable has:

| Field | Required | Description |
|---|---|---|
| `name` | Yes | Variable name (used in `{{name}}` placeholders) |
| `description` | No | Explains what this variable represents |
| `default` | No | Fallback value if no project-specific value is set |

## Setting Values Per Project

In the Skill Editor, a **Template Variables** panel appears below the frontmatter form for skills that define template variables. Each variable shows an input field where you can enter the value for the current project.

Values are stored per project-skill combination. The same skill can have `{{language}}` set to "TypeScript" in one project and "Python" in another.

::: tip
If you leave a value blank and the variable has a `default`, the default is used. If there is no default either, the `{{variable}}` placeholder is left as-is in the output.
:::

## When Variables Are Resolved

Template variables are resolved **at compose/sync time**, not at edit time. This means:

- The Monaco editor always shows the raw `{{variable}}` placeholders
- The Test runner sends the resolved body with variables substituted
- Provider sync outputs contain resolved values
- Agent compose uses resolved values

This design keeps the skill files portable. The same `.agentis/skills/code-style.md` file works across projects without modification -- only the per-project variable values change.

## Using Variables in the Body

Place `{{variable_name}}` anywhere in the Markdown body. Variable names must be alphanumeric with underscores (matching `\w+`):

```markdown
## Naming Conventions

- Use {{naming_convention}} for file names
- Prefix interfaces with {{interface_prefix}}
- Database tables use {{table_naming}} convention
```

## Extracting Variables

The template resolver automatically extracts all `{{variable}}` references from the body. You don't need to manually declare every variable in `template_variables` -- but doing so gives you the description and default fields which improve the editing experience.

## Missing Variables

If a variable is referenced in the body but has no value set and no default, it passes through unchanged. This lets you partially resolve templates when not all values are available yet.

The API exposes a list of missing variables so the UI can highlight which values still need to be configured.
