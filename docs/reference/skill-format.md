# Skill File Format

Skills are stored as Markdown files with YAML frontmatter in `.orkestr/skills/`. This page is the complete specification.

## File Location

```
project-root/
  .orkestr/
    skills/
      my-skill.md
```

The filename is the skill's slug with a `.md` extension. Slugs are auto-generated from the skill name (lowercased, spaces replaced with hyphens, special characters removed).

## Structure

A skill file has two sections separated by the YAML frontmatter delimiters (`---`):

```markdown
---
id: summarize-doc
name: Summarize Document
description: Summarizes any document to key bullet points
tags: [summarization, documents]
model: claude-sonnet-4-6
max_tokens: 1000
tools: []
includes: []
template_variables: []
created_at: 2026-01-15T09:00:00Z
updated_at: 2026-03-09T14:22:00Z
---

You are a precise document summarizer. Given any document, extract
the key points and present them as a concise bulleted list.

## Rules

- Maximum 10 bullet points
- Each bullet should be one sentence
- Preserve the original document's terminology
- Order bullets by importance, not document order
```

## Frontmatter Field Reference

### Required Fields

| Field | Type | Validation | Description |
|---|---|---|---|
| `id` | `string` | Must be unique within the project. Alphanumeric, hyphens, underscores. | Unique identifier, usually matches the filename slug. |
| `name` | `string` | Non-empty, max 255 characters. | Human-readable display name shown in the UI. |

### Optional Fields

| Field | Type | Default | Validation | Description |
|---|---|---|---|---|
| `description` | `string` | `null` | Max 1000 characters. | Short summary of the skill's purpose. Shown on skill cards and in search results. |
| `tags` | `string[]` | `[]` | Each tag must be a non-empty string. | Tags for categorization and filtering. Tags are matched against the project's tag definitions. |
| `model` | `string` | `null` | Must be a recognized model identifier or `null`. | Target model (e.g., `claude-sonnet-4-6`, `gpt-5.4`). Falls back to the default model in Settings when `null`. |
| `max_tokens` | `integer` | `null` | Positive integer or `null`. | Maximum output tokens for test/playground. Falls back to system default (4096) when `null`. |
| `tools` | `object[]` | `[]` | Each object must have `name` and `description`. | Tool/function definitions in JSON Schema format. See [Tool Object](#tool-object) below. |
| `includes` | `string[]` | `[]` | Each value must be a valid skill slug in the same project. Max depth 5. | Slugs of other skills to prepend. Resolved recursively with circular dependency detection. See [Includes](../guide/includes). |
| `template_variables` | `object[]` | `[]` | Each object must have a `name` field. | Template variable definitions. See [Template Variable Object](#template-variable-object) below. |
| `created_at` | `string` | Auto-set on creation | ISO 8601 datetime. | Creation timestamp. Set automatically, not typically edited manually. |
| `updated_at` | `string` | Auto-set on save | ISO 8601 datetime. | Last modification timestamp. Updated automatically on every save. |

### Template Variable Object

Each entry in `template_variables`:

| Field | Type | Required | Description |
|---|---|---|---|
| `name` | `string` | Yes | Variable name. Must be alphanumeric with underscores (e.g., `output_language`). Used in `{{name}}` placeholders in the body. |
| `description` | `string` | No | Human-readable description shown in the variable editor UI. |
| `default` | `string` | No | Default value used when no per-project override is set. If omitted and no override exists, the placeholder is left unresolved. |

Example:

```yaml
template_variables:
  - name: language
    description: Output language for the response
    default: English
  - name: max_items
    description: Maximum number of items to return
    default: "10"
```

### Tool Object

Each entry in `tools` follows the standard function/tool definition schema:

```yaml
tools:
  - name: get_weather
    description: Get current weather for a location
    parameters:
      type: object
      properties:
        location:
          type: string
          description: City name or coordinates
      required: [location]
```

Tools are passed to the model during test and playground runs. They are informational during provider sync -- most providers do not support tool definitions in their config format.

## Body

Everything after the closing `---` is the skill body. It is plain Markdown that becomes the system prompt or instruction content.

### Supported Content

- Standard Markdown: headings, lists, bold, italic, code blocks, links, tables
- `{{variable}}` template placeholders -- resolved at compose/sync time, not edit time
- Fenced code blocks with language identifiers
- Any text content -- the body is passed verbatim to the model after template resolution

### Conventions

- Use H2 (`##`) headings to organize sections within a skill. H1 is reserved for the skill name in provider output.
- Keep skills focused on a single concern. Use [includes](../guide/includes) to compose larger prompts from smaller skills.
- Put examples in fenced code blocks with appropriate language tags.
- Use bold for key terms and emphasis for nuance.

## Parsing

Orkestr uses `SkillFileParser` (built on `symfony/yaml`) to parse skill files. The parser:

1. Extracts the YAML frontmatter between the `---` delimiters
2. Validates that required fields (`id`, `name`) are present
3. Parses the remaining content as the body
4. Returns a structured array with both sections

Invalid YAML or missing required fields produce a validation error visible in the Skill Editor sidebar.

## Complete Example

```markdown
---
id: api-endpoint-review
name: API Endpoint Review
description: Reviews REST API endpoints for correctness, security, and consistency
tags: [api, review, security]
model: claude-sonnet-4-6
max_tokens: 4096
tools: []
includes: [project-context, coding-standards]
template_variables:
  - name: framework
    description: Backend framework
    default: Laravel
  - name: auth_method
    description: Authentication method used
    default: Bearer token
created_at: 2026-03-01T10:00:00Z
updated_at: 2026-03-10T09:15:00Z
---

You are a senior API reviewer for a {{framework}} application that uses
{{auth_method}} authentication.

## Review Checklist

- Verify HTTP methods match the operation (GET for reads, POST for creates, etc.)
- Check that all endpoints validate input before processing
- Ensure error responses use appropriate HTTP status codes
- Verify authentication is enforced on protected endpoints
- Check for mass assignment vulnerabilities
- Review pagination for list endpoints

## Output Format

For each endpoint, provide:

1. **Status**: Pass / Fail / Warning
2. **Issue**: Description of the problem (if any)
3. **Fix**: Suggested remediation with code example
```

::: tip
Use the Prompt Linter (available in the Skill Editor's Lint tab) to check your skill against 8 quality rules covering structure, specificity, and completeness.
:::
