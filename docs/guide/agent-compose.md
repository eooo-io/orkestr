# Agent Compose

Agent compose merges an agent's base instructions, custom per-project instructions, and assigned skill bodies into a single output ready for provider sync.

## How Compose Works

For each enabled agent, the compose process produces a single Markdown document by concatenating three layers:

1. **Base instructions** -- The built-in persona and guidelines for the agent role
2. **Custom instructions** -- Project-specific additions you write in the agent config modal
3. **Assigned skill bodies** -- The resolved body of each skill assigned to the agent

The output follows this structure:

```markdown
# [Agent Name]

[Base instructions]

## Project-Specific Instructions

[Custom instructions, if any]

## Included Skills

### [Skill 1 Name]

[Resolved body of skill 1]

### [Skill 2 Name]

[Resolved body of skill 2]
```

::: info
Skill bodies used in compose are the **resolved** versions -- any [includes](./includes) and [template variables](./templates) are expanded before being included in the composed output.
:::

## Token Budget Preview

Click the **Compose** button on an agent card in the Agents tab to open the compose preview modal. This shows:

- The full composed Markdown output
- A token estimate with a progress bar against the model's context limit
- Color-coded status: green (safe), yellow (>75% of context), red (>90% of context)

Use this to check whether your composed agent output fits within the model's context window before syncing.

## Copying Composed Output

The compose preview modal has a **Copy** button that copies the full composed output to your clipboard. This is useful for testing the output in an external tool or pasting it into a conversation.

## Compose and Provider Sync

When you trigger a provider sync, `ProviderSyncService` calls `composeAll()` to build the composed output for every enabled agent in the project. These composed outputs are then passed to each provider driver alongside individual skill bodies.

How composed agents appear in provider output depends on the provider:

- **Claude** (`.claude/CLAUDE.md`) -- Each agent appears under its own H2 heading after the individual skills
- **Cursor** (`.cursor/rules/`) -- Each agent gets its own `.mdc` file named after the agent slug
- **Copilot** (`.github/copilot-instructions.md`) -- Agent outputs are concatenated after skills
- **Windsurf** (`.windsurf/rules/`) -- Each agent gets its own `.md` file
- **Cline** (`.clinerules`) -- Agent outputs are appended to the flat file
- **OpenAI** (`.openai/instructions.md`) -- Agent outputs are concatenated after skills

## API Endpoints

```
GET /api/projects/{id}/agents/{agentId}/compose   # Compose a single agent
GET /api/projects/{id}/agents/compose              # Compose all enabled agents
```

The compose endpoint returns the Markdown output and a token estimate.
