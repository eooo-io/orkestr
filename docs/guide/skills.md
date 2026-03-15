# Creating Skills

Skills are the core unit in Agentis Studio. Each skill is a reusable AI prompt stored as a Markdown file with YAML frontmatter.

## The Skill Editor

Open any skill from the project detail page to launch the Skill Editor. The editor has three panels:

- **Left sidebar** -- Frontmatter form with all metadata fields
- **Center** -- Monaco code editor for the skill body (Markdown)
- **Right panel** -- Tabs for Test, Versions, and Lint

### Frontmatter Fields

| Field | Type | Required | Description |
|---|---|---|---|
| `name` | string | Yes | Display name of the skill |
| `description` | string | No | Short summary of what the skill does |
| `model` | string | No | Target model (e.g., `claude-sonnet-4-6`) |
| `max_tokens` | number | No | Max output tokens for test/playground |
| `tags` | string[] | No | Tags for categorization and filtering |
| `tools` | object[] | No | Tool/function definitions (JSON) |
| `includes` | string[] | No | Slugs of other skills to [include](./includes) |
| `template_variables` | object[] | No | [Template variable](./templates) definitions |

Fill in the fields in the sidebar form. The `name` field is the only required field -- everything else is optional.

### Writing the Body

The body is plain Markdown that becomes the system prompt or instruction content. Write it as you would any AI prompt:

```markdown
You are a senior TypeScript developer. When writing or reviewing code:

## Style Rules

- Use `const` by default, `let` only when reassignment is needed
- Prefer named exports over default exports
- Use explicit return types on all public functions

## Error Handling

- Never swallow errors silently
- Use custom error classes for domain-specific failures
- Always include context in error messages

## Example

Given a function that fetches user data:

```typescript
// Good
export const fetchUser = async (id: string): Promise<User> => {
  const response = await api.get(`/users/${id}`);
  if (!response.ok) {
    throw new UserNotFoundError(`User ${id} not found`);
  }
  return response.json();
};
```

Use Markdown headings, lists, code blocks, and emphasis to structure your prompt. The Monaco editor provides syntax highlighting for Markdown.

## Token Estimation

The editor displays a live token count in the frontmatter sidebar. The estimate updates as you type and uses a character-based approximation (roughly 1 token per 4 characters).

Token counts are color-coded against the model's context limit:

- **Green** -- Under 75% of context window
- **Yellow** -- 75-90% of context window
- **Red** -- Over 90% of context window

::: tip
If your skill is very long, consider splitting it into smaller skills and using the [includes system](./includes) to compose them.
:::

## Saving

Press `Ctrl+S` (or `Cmd+S` on macOS) to save. Every save:

1. Updates the skill in the database
2. Writes the `.agentis/skills/{slug}.md` file to disk
3. Creates a new [version snapshot](./versions)
4. Optionally [auto-commits to git](./git-integration) if enabled

An unsaved changes indicator appears when you have modifications. The editor also warns you if you try to navigate away with unsaved changes.

## Duplicating a Skill

Click **Duplicate** in the action bar to clone a skill. The duplicate gets a new slug with a `-copy` suffix. You can duplicate within the same project or across projects.

## Deleting a Skill

Click **Delete** in the action bar. This removes the skill from the database and deletes the `.agentis/skills/{slug}.md` file from disk. Deletion is permanent -- there is no trash or soft delete.

::: warning
Deleting a skill also removes all its version history. If you might want the skill back later, consider exporting it as a [bundle](./bundles) first.
:::

## Bulk Operations

From the project detail page, enter select mode to multi-select skills. The bulk action bar lets you:

- **Tag** -- Add or remove tags from selected skills
- **Assign** -- Assign selected skills to an agent
- **Move** -- Move selected skills to another project
- **Delete** -- Delete all selected skills

## Includes Composition

Skills can include other skills using the `includes` frontmatter field. When a skill is resolved (for sync, test, or compose), the bodies of included skills are prepended in order.

```yaml
includes: [coding-standards, project-context]
```

Includes are resolved recursively up to a maximum depth of 5 levels. Circular dependencies are detected and reported as an error. See [Includes & Composition](./includes) for the full guide.

## Template Variables

Define `{{variable}}` placeholders in the skill body and declare them in frontmatter:

```yaml
template_variables:
  - name: language
    description: Output language
    default: English
```

Variables are resolved at compose/sync time, not edit time. Each project can override default values at **Skills > Variables**. See [Template Variables](./templates) for details.

## Conditional Activation

Skills can be scoped to specific agents or contexts through assignment. A skill that is created but not assigned to any agent is still synced as an individual skill during provider sync. However, skills assigned to a disabled agent are excluded from the composed output.

To conditionally include a skill:

1. Assign it to a specific agent via the Agent Configuration modal
2. Toggle the agent on or off per project
3. Only enabled agents include their assigned skills in composed output

This lets you maintain a library of skills and activate them selectively by project and agent context.

## AI-Assisted Generation

Click **Generate** in the action bar or on the project detail page to describe what you want in plain language. Claude generates a complete skill with frontmatter and body that you can review and edit before saving.
