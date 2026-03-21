# Orkestr — VS Code Extension

Manage AI skills, sync with your Orkestr server, and run skill tests directly from VS Code.

## Features

### Skill Browser

Browse all your Orkestr projects and skills in a dedicated sidebar tree view. Skills display their name, description, and tags. Use the filter command to narrow results by name, tag, or description.

### Skill Editor

Author skill files with YAML frontmatter and Markdown body. The extension provides:

- Syntax highlighting for `.orkestr` skill files
- Diagnostic reporting for missing or invalid frontmatter fields (`id`, `name` are required)
- Warnings for duplicate keys, invalid slug format, and invalid `max_tokens` values

### Sync Manager

Keep local `.orkestr/skills/` files in sync with your Orkestr server:

- **Push**: upload local skill changes to the server
- **Pull**: download skills from the server into your workspace
- Status bar indicator shows current sync state (synced / modified / error)
- Optional auto-sync on save

### Test Runner

Run skill tests against the Orkestr API without leaving your editor:

- CodeLens "Run Test" action at the top of skill files
- Output channel for detailed test results (status, model, tokens, output)
- Command to run all tests for the current project

## Configuration

Open **Settings** and search for `orkestr` to configure:

| Setting | Default | Description |
|---|---|---|
| `orkestr.serverUrl` | `http://localhost:8000` | Orkestr server URL |
| `orkestr.apiToken` | (empty) | API token for authentication |
| `orkestr.projectId` | (empty) | Default project ID |
| `orkestr.autoSync` | `false` | Auto-sync skills on save |

## Commands

All commands are available via the Command Palette (`Cmd+Shift+P` / `Ctrl+Shift+P`):

| Command | Description |
|---|---|
| `Orkestr: Refresh Skills` | Reload the skill browser tree |
| `Orkestr: Filter Skills` | Filter skills by name, tag, or description |
| `Orkestr: Open Skill` | Open a skill from the tree view |
| `Orkestr: Push Skills to Server` | Upload local changes to Orkestr |
| `Orkestr: Pull Skills from Server` | Download skills to `.orkestr/skills/` |
| `Orkestr: Run Skill Test` | Run test for the active skill file |
| `Orkestr: Run All Skill Tests` | Run tests for all skills in the project |
| `Orkestr: Create New Skill` | Scaffold a new skill file |

## Development

```bash
cd vscode-extension
npm install
npm run compile
```

To launch the extension in a development host, press `F5` in VS Code with this folder open.

## Packaging

```bash
npm run package
```

This produces a `.vsix` file you can install locally or publish to the VS Code Marketplace.

## Requirements

- VS Code 1.85.0 or later
- An Orkestr server instance (local or remote)
