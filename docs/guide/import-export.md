# Import & Export

Orkestr supports multiple ways to move skills and configuration between projects, instances, and external sources.

## Bundle Export

Export a project's skills and agents as a portable bundle.

### Exporting

From the project detail page, click **Export** or use the API:

```
POST /api/projects/{id}/export
```

```json
{
  "skill_ids": ["uuid1", "uuid2"],
  "agent_ids": ["agent-uuid"],
  "format": "zip"
}
```

| Format | Contents |
|---|---|
| `zip` | A ZIP archive containing `.agentis/skills/*.md` files and a `manifest.json` |
| `json` | A single JSON file with all skill data, frontmatter, and agent configuration |

Omit `skill_ids` to export all skills. The manifest includes project metadata, tag definitions, and agent assignments.

### Importing

```
POST /api/projects/{id}/import-bundle
```

Upload the bundle as a multipart form with:

- `file` -- The ZIP or JSON bundle
- `conflict_mode` -- How to handle skills that already exist in the target project:

| Mode | Behavior |
|---|---|
| `skip` | Keep the existing skill, ignore the imported one |
| `overwrite` | Replace the existing skill with the imported version |
| `rename` | Import with a `-imported` suffix on the slug |

::: tip
Use bundles to migrate skills between Orkestr instances or to share curated skill sets with other teams.
:::

## GitHub Org Import

Import skills from repositories across a GitHub organization using the four-step wizard.

### Step 1: Enter Organization

Provide the GitHub org name. Orkestr uses the GitHub API to discover repositories.

### Step 2: Discover Repositories

```
POST /api/import/github/discover
```

```json
{
  "org": "my-github-org"
}
```

Orkestr scans each repository for `.agentis/skills/`, `.cursor/rules/`, `.claude/CLAUDE.md`, and other recognized skill file patterns.

### Step 3: Select Skills

Review the discovered skills and select which ones to import. The preview shows each skill's name, source repository, and file path.

### Step 4: Import

```
POST /api/import/github/import
```

```json
{
  "org": "my-github-org",
  "selections": [
    { "repo": "my-github-org/api-service", "path": ".agentis/skills/review.md" },
    { "repo": "my-github-org/frontend", "path": ".cursor/rules/components.mdc" }
  ],
  "project_id": "target-project-uuid"
}
```

Imported skills are converted to the `.agentis/` format regardless of their source format.

## Skills.sh Discovery

Import skills from any public GitHub repository using the skills.sh protocol.

### Discover

```
POST /api/skills-sh/discover
```

```json
{
  "repo": "owner/repo-name"
}
```

Returns a list of skill file paths found in the repository.

### Preview

Fetch and preview the content of discovered skills before importing:

```
POST /api/skills-sh/preview
```

```json
{
  "repo": "owner/repo-name",
  "paths": [".curated/code-review.md", ".curated/testing.md"]
}
```

### Import

```
POST /api/skills-sh/import
```

```json
{
  "repo": "owner/repo-name",
  "path": ".curated/code-review.md",
  "target": "project",
  "project_id": "target-project-uuid"
}
```

Set `target` to `"library"` to import into the global library instead of a specific project.

## Library Import

The global skill library ships with 25 pre-built skills. Browse and import them from **Library** in the sidebar:

```
GET  /api/library?category=Laravel&tags=testing&q=pest
POST /api/library/{id}/import
```

```json
{
  "project_id": "target-project-uuid"
}
```

## Reverse-Sync Import

If you already have provider-specific config files (`.claude/CLAUDE.md`, `.cursor/rules/*.mdc`, etc.), Orkestr can detect and import them:

```
POST /api/import/detect
```

This scans a project directory for recognized provider files. Then import them into the `.agentis/` format:

```
POST /api/projects/{id}/import
```

## Bulk Operations

From the project detail page, enter multi-select mode to perform bulk actions:

| Action | Description |
|---|---|
| **Tag** | Add or remove tags from selected skills |
| **Assign** | Assign selected skills to an agent |
| **Move** | Move selected skills to another project |
| **Delete** | Delete all selected skills |

::: warning
Bulk delete is permanent. There is no undo -- consider exporting a bundle first if you might need the skills later.
:::
