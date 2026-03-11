# Bundle Export and Import

Bundles let you export selected skills and agents from a project as a portable archive, then import them into another project or share them with teammates.

## Exporting a Bundle

On the project detail page, click **Export**. The export modal shows:

1. **Skill checkboxes** -- Select which skills to include (with a Select All toggle)
2. **Agent checkboxes** -- Select which agents to include
3. **Format toggle** -- Choose between ZIP and JSON

Click **Export** to download the bundle file.

### ZIP Bundle Structure

```
my-bundle.zip
  skills/
    code-review.md          # Full skill file (frontmatter + body)
    testing-strategy.md
  agents.yaml               # Agent configs (name, custom instructions, skill assignments)
  bundle.yaml               # Metadata (project name, export date, skill count)
```

### JSON Bundle Structure

A single `.json` file containing the same data as the ZIP but in a flat JSON structure:

```json
{
  "metadata": {
    "project": "my-project",
    "exported_at": "2026-03-10T12:00:00Z",
    "skill_count": 2,
    "agent_count": 1
  },
  "skills": [...],
  "agents": [...]
}
```

## Importing a Bundle

On the project detail page, click **Import**. The import modal has two steps:

### Step 1: Upload

Drag and drop a bundle file (ZIP or JSON) or click to browse. The file is uploaded and parsed to show a preview.

### Step 2: Preview and Confirm

The preview shows:

- List of skills in the bundle with conflict indicators
- List of agents in the bundle
- A **conflict resolution mode** selector

### Conflict Resolution Modes

When an imported skill's slug already exists in the target project:

| Mode | Behavior |
|---|---|
| **Skip** | Do not import skills that conflict. Existing skills are untouched. |
| **Overwrite** | Replace existing skills with the imported versions. |
| **Rename** | Import with a modified slug (appends `-imported` or a numeric suffix). |

Click **Import** to execute. A summary shows how many skills and agents were imported, skipped, or overwritten.

## API Endpoints

```
POST /api/projects/{id}/export
```

Request body specifies `skill_ids`, `agent_ids`, and `format` ("zip" or "json"). Returns the file as a download.

```
POST /api/projects/{id}/import-bundle
```

Accepts a multipart file upload with the bundle file and a `conflict_mode` parameter ("skip", "overwrite", or "rename").

## Use Cases

- **Team sharing** -- Export your project's skills and share the bundle file via Slack, email, or a shared drive
- **Project bootstrapping** -- Create a bundle of your standard skills and import it into every new project
- **Backup** -- Export everything before making major changes
- **Migration** -- Move skills between Agentis Studio instances
