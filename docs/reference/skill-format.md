# Skill File Format

Skills are stored as Markdown files with YAML frontmatter in `.agentis/skills/`. This page is the complete specification.

## File Location

```
project-root/
  .agentis/
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

## Frontmatter Fields

### Required Fields

| Field | Type | Description |
|---|---|---|
| `id` | `string` | Unique identifier (usually matches the slug) |
| `name` | `string` | Human-readable display name |

### Optional Fields

| Field | Type | Default | Description |
|---|---|---|---|
| `description` | `string` | `null` | Short summary of the skill's purpose |
| `tags` | `string[]` | `[]` | Tags for categorization and filtering |
| `model` | `string` | `null` | Target model (e.g., `claude-sonnet-4-6`). Falls back to default model in settings. |
| `max_tokens` | `integer` | `null` | Maximum output tokens for test/playground. Falls back to system default. |
| `tools` | `object[]` | `[]` | Tool/function definitions in JSON Schema format |
| `includes` | `string[]` | `[]` | Slugs of other skills in the same project to prepend. See [Includes](../guide/includes). |
| `template_variables` | `object[]` | `[]` | Template variable definitions. See [Templates](../guide/templates). |
| `created_at` | `string` (ISO 8601) | Auto-set | Creation timestamp |
| `updated_at` | `string` (ISO 8601) | Auto-set | Last modification timestamp |

### Template Variable Object

Each entry in `template_variables`:

| Field | Type | Required | Description |
|---|---|---|---|
| `name` | `string` | Yes | Variable name (alphanumeric + underscores) |
| `description` | `string` | No | What this variable represents |
| `default` | `string` | No | Default value if no per-project value is set |

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

## Body

Everything after the closing `---` is the skill body. It is plain Markdown that becomes the system prompt or instruction content.

The body supports:

- Standard Markdown (headings, lists, bold, italic, code blocks, links)
- `{{variable}}` template placeholders
- Any text content -- the body is passed verbatim to the model (after template resolution)

## Parsing

Agentis Studio uses `SkillFileParser` (built on `symfony/yaml`) to parse skill files. The parser:

1. Extracts the YAML frontmatter between the `---` delimiters
2. Validates that required fields (`id`, `name`) are present
3. Parses the remaining content as the body
4. Returns a structured array with both sections

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
