# Skills.sh Import

Orkestr can discover and import skills directly from GitHub repositories that follow the [skills.sh](https://skills.sh) convention — Markdown files with YAML frontmatter stored in well-known directories.

## How It Works

Skills.sh uses the GitHub API to browse repositories for skill files without cloning the repo. The workflow has three steps:

1. **Discover** — Scan a GitHub repo for skill files (fast, single API call)
2. **Preview** — Fetch content for selected skills to review before importing
3. **Import** — Import into your library or directly into a project

## Discovering Skills

Provide a GitHub repository in `owner/repo` format:

```
POST /api/skills-sh/discover
```

```json
{
  "repo": "eooo-io/skills-collection"
}
```

Returns a list of discovered skill files with their paths:

```json
{
  "data": [
    { "path": ".curated/code-review.md", "name": "code-review.md" },
    { "path": ".curated/testing-strategy.md", "name": "testing-strategy.md" }
  ],
  "repo": "eooo-io/skills-collection",
  "count": 2
}
```

## Previewing Skills

Fetch the full content for a batch of discovered skills (up to 30 at a time):

```
POST /api/skills-sh/preview
```

```json
{
  "repo": "eooo-io/skills-collection",
  "paths": [".curated/code-review.md"]
}
```

Returns parsed skill data including frontmatter, description, and body.

## Importing Skills

Import a skill into your library or directly into a project:

```
POST /api/skills-sh/import
```

```json
{
  "repo": "eooo-io/skills-collection",
  "path": ".curated/code-review.md",
  "target": "project",
  "project_id": "uuid-of-target-project"
}
```

### Import Targets

| Target | Behavior |
|---|---|
| `library` | Creates a library skill entry. Duplicate slugs are rejected (409). |
| `project` | Creates a skill in the target project with file write, v1 version, and tag sync. Duplicate slugs get a numeric suffix. |

### Category Derivation

The skill's category is derived from its path in the repository:

| Path Contains | Category |
|---|---|
| `.curated/` | Curated |
| `.experimental/` | Experimental |
| `.system/` | System |
| Other | Community |

## Differences from Library Import

| Feature | Library Import | Skills.sh Import |
|---|---|---|
| Source | Local pre-seeded database | Any GitHub repository |
| Network required | No | Yes (GitHub API) |
| Preview before import | No (card shows all info) | Yes (separate preview step) |
| Batch discovery | N/A | Up to 30 skills per preview |
| Category | Manually set | Auto-derived from repo path |
