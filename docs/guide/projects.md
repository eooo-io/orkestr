# Projects

A project in Orkestr maps to a codebase directory on your filesystem. It is the container for skills, agents, provider sync, workflows, and all configuration. Every project points to a path where the `.agentis/` directory lives.

## Creating a Project

Open the Filament Admin at `http://localhost:8000/admin` and create a new project. Provide:

- **Name** -- a display name for the project (e.g., "My SaaS App")
- **Description** -- optional summary of what the project does
- **Path** -- the absolute path to the project root on disk

::: tip
When using Docker, the path is relative to the `PROJECTS_HOST_PATH` mount defined in your `.env` file. For local installs, use the full filesystem path.
:::

After creating the project, open the React SPA at `http://localhost:5173` and navigate to it. You will land on the project detail page.

## Project Detail Tabs

The project detail page is organized into six tabs that follow the natural build flow:

| Tab | Purpose |
|---|---|
| **Canvas** | WYSIWYG visual builder showing agents, skills, MCP servers, and their connections |
| **Skills** | Grid or list view of all skills in the project |
| **Agents** | Enable, disable, and configure the 9 pre-built agents |
| **Team** | Dashboard view of enabled agents with run stats, autonomy levels, and cost data |
| **Connections** | Configure MCP servers and A2A agent integrations |
| **Schedules** | Set up cron-based and event-triggered agent execution |

The Canvas tab is the default view. It provides a high-level overview of how your agents, skills, and integrations relate to each other.

## Toolbar Actions

The toolbar at the top of the project detail page contains buttons for common operations:

| Button | Action |
|---|---|
| **Settings** | Open project settings (name, description, repository config, import, webhooks) |
| **Scan** | Read `.agentis/skills/*.md` files from disk, upsert into the database, and create version snapshots |
| **Preview** | Show a diff of what provider sync will write before committing |
| **Sync** | Write all skills and composed agents to provider config files on disk |
| **Library** | Open the skill library to import pre-built skills into this project |
| **Skills.sh** | Discover and import skills from GitHub repositories |
| **Generate** | Describe a skill in plain language and let an LLM generate it |
| **Export** | Export the project as a ZIP or JSON bundle |
| **Import** | Import a previously exported bundle with conflict resolution |
| **Add Skill** | Navigate to the skill editor to create a new skill |

## Project Settings

Click the **Settings** button (gear icon) to access project configuration. Settings are organized into sections:

### General

Edit the project name, description, and filesystem path.

### Repository

Connect a Git repository for push/pull operations. Configure the remote URL, branch, and authentication credentials. Orkestr can pull skill changes from a remote and push synced provider files back.

### Import

Reverse-sync detects existing provider config files (`.claude/CLAUDE.md`, `.cursor/rules/`, etc.) in the project directory and imports them as Orkestr skills. This is useful when adopting Orkestr on an existing codebase that already has provider-specific configs.

### Webhooks

Configure outbound webhooks that fire on project events (skill created, skill updated, sync completed). See [Webhooks](./webhooks) for details.

## Skills Tab

The Skills tab shows all skills in the project. You can switch between grid and list views using the toggle in the top-right corner.

### Select Mode

Click **Select** to enter multi-select mode. Select individual skills by clicking their checkboxes, or use **All** / **None** to bulk select. The bulk action bar appears at the bottom with options to tag, assign, move, or delete selected skills.

### Skill Cards

Each skill card shows the skill name, description, model, tags, and token estimate. Click a card to open it in the [Skill Editor](./skill-editor).

## Scanning

The **Scan** button reads the project's filesystem for `.agentis/skills/*.md` files. For each file found:

1. YAML frontmatter is parsed to extract metadata
2. The skill is upserted into the database (matched by slug)
3. A version snapshot is created

::: warning
Scanning is a queued job. After clicking Scan, the skills list refreshes after a short delay. If you have many skills, it may take a few seconds.
:::

Scanning is useful when skill files are edited outside of Orkestr (e.g., by a text editor or another developer committing changes).

## Syncing

The **Sync** button writes all skills and composed agents to provider-specific config files. Before syncing, click **Preview** to see a diff of what will change. See [Provider Sync](./provider-sync) for the full mapping of providers to output paths.

## Next Steps

- [Skill Editor](./skill-editor) -- deep dive into the 7-tab editor
- [Agent Teams](./agent-teams) -- configure and manage agent teams
- [Canvas](./canvas) -- the visual builder
- [Workflows](./workflows) -- multi-agent DAG orchestration
