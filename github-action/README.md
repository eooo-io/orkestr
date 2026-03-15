# Orkestr GitHub Action

Validate and sync your AI skill files directly from GitHub. This action ensures your `.agentis/skills/` directory contains well-formed skill definitions and can automatically push them to your Orkestr server on merge.

## Features

- **Validate** skill files in pull requests (YAML frontmatter syntax, required fields, duplicate IDs, broken includes)
- **Sync** skills to your Orkestr server on merge to main
- Summary table output for quick review
- Non-zero exit code on validation failure (blocks merge)

## Inputs

| Input | Required | Default | Description |
|---|---|---|---|
| `mode` | No | `validate` | Action mode: `validate` or `sync` |
| `skills-path` | No | `.agentis/skills` | Path to skills directory relative to repo root |
| `server-url` | No | — | Orkestr server URL (required for `sync` mode) |
| `api-token` | No | — | Orkestr API token (required for `sync` mode) |

## Outputs

| Output | Description |
|---|---|
| `total` | Total number of skill files found |
| `valid` | Number of valid skill files |
| `invalid` | Number of invalid skill files |
| `synced` | Number of skills synced (sync mode only) |

## Usage

### Validate on Pull Request

```yaml
name: Validate Skills
on:
  pull_request:
    paths:
      - '.agentis/skills/**'

jobs:
  validate:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Validate skill files
        uses: eooo-io/orkestr-action@v1
        with:
          skills-path: '.agentis/skills'
```

### Sync on Merge to Main

```yaml
name: Sync Skills
on:
  push:
    branches: [main]
    paths:
      - '.agentis/skills/**'

jobs:
  sync:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Sync skills to Orkestr
        uses: eooo-io/orkestr-action@v1
        with:
          mode: sync
          skills-path: '.agentis/skills'
          server-url: ${{ secrets.ORKESTR_SERVER_URL }}
          api-token: ${{ secrets.ORKESTR_API_TOKEN }}
```

### Full Workflow (Validate + Sync)

See [`examples/orkestr-sync.yml`](examples/orkestr-sync.yml) for a complete workflow that validates on PRs and syncs on merge.

## Validation Rules

The validator checks each `.md` file in the skills directory for:

1. **Frontmatter presence** — File must start with `---` and have a closing `---` delimiter
2. **Required fields** — `id` and `name` must be present in the YAML frontmatter
3. **Unique IDs** — No two skill files may share the same `id` value
4. **Include references** — Any skill referenced in the `includes` array must exist as another skill's `id`
5. **YAML syntax** — Basic structural checks on frontmatter lines

## Skill File Format

Skills follow the Orkestr canonical format: YAML frontmatter + Markdown body.

```markdown
---
id: my-skill
name: My Skill
description: What this skill does
tags: [tag1, tag2]
model: claude-sonnet-4-6
includes: [base-instructions]
---

Your skill prompt content here...
```

Required fields: `id`, `name`. All other fields are optional.

## Setting Up Secrets

For sync mode, add these secrets to your GitHub repository:

1. Go to **Settings > Secrets and variables > Actions**
2. Add `ORKESTR_SERVER_URL` — your Orkestr server URL (e.g., `https://app.orkestr.dev`)
3. Add `ORKESTR_API_TOKEN` — an API token from your Orkestr account settings

## License

MIT
