# Changelog

## v1.0.0

### Infrastructure

- Docker Compose environment with PHP 8.4, Nginx, MariaDB 11, and Node.js containers
- Makefile with targets for build, up, down, migrate, test, and shell access
- Environment configuration with `.env.example`

### Database and Models

- Migrations for projects, project_providers, skills, skill_versions, tags, skill_tag, library_skills, app_settings, agents, project_agent, agent_skill, marketplace_skills, webhooks, webhook_deliveries, and skill_variables
- Eloquent models with UUID primary keys, JSON casts, auto-generated slugs, and relationship methods

### File I/O and Manifest Engine

- `SkillFileParser` for reading/writing YAML frontmatter + Markdown body files
- `AgentisManifestService` for scanning, scaffolding, and managing `.agentis/` directories
- `ProjectScanJob` for upserting skills from disk into the database

### Provider Sync

- 6 provider drivers: Claude, Cursor, GitHub Copilot, Windsurf, Cline, OpenAI
- `ProviderSyncService` orchestrating all enabled drivers per project
- Dry-run mode with `generate()` for diff preview
- Resolved includes and template variables in sync output
- Composed agent output included in provider files

### Filament Admin Panel

- `ProjectResource` with provider checkboxes and Scan/Sync actions
- `LibrarySkillResource` with category filter and tag management
- `TagResource` with color picker and skill count
- Settings page for API keys, default model, and provider reference
- Dashboard with stats overview

### Skills API

- Full CRUD with file I/O (create, read, update, delete, duplicate)
- Auto-versioning on every save
- Lint endpoint with 8 prompt quality rules
- AI-assisted skill generation via Claude
- Bulk operations: tag, assign, delete, move

### React SPA

- Project list with card grid and sync buttons
- Project detail with skill grid, scan/sync/add, grid/list toggle
- Skill Editor with Monaco editor, frontmatter form, action bar
- Unsaved changes guard with beforeunload and navigation prompt
- Loading skeletons and empty states throughout

### Skill Composition

- `includes` system for composable prompts with recursive resolution
- Circular dependency detection and max depth of 5
- `template_variables` with `{{variable}}` placeholders and per-project values
- Template resolution at compose/sync time

### Prompt Linting

- 8 lint rules: vague instructions, weak constraints, conflicting directives, missing output format, excessive length, role confusion, missing examples, redundancy
- Lint tab in Skill Editor with color-coded issue cards

### Version History

- Auto-versioning on every skill save
- Version list with timestamps
- Monaco Diff Editor for comparing any two versions
- One-click version restore

### Agent System

- 9 pre-built agents: Orchestrator, PM, Architect, QA, Design, Code Review, Infrastructure, CI/CD, Security
- Per-project enable/disable, custom instructions, and skill assignment
- Agent Compose merging base + custom + skill bodies
- Token budget preview with progress bar

### Testing

- Multi-model test runner with SSE streaming (Anthropic, OpenAI, Gemini, Ollama)
- Playground with multi-turn conversation, model selection, and system prompt picker
- Per-turn stats: elapsed time, input/output tokens
- Stop mid-stream and copy result

### Token Estimation

- Live token count in the frontmatter form
- Model-specific context limits with color-coded warnings (75%/90% thresholds)
- Token estimates on skill cards and in agent compose preview

### Organization and Discovery

- Command palette with fuzzy search across skills, projects, pages, and actions
- Cross-project FULLTEXT search with tag, project, and model filters
- Color-coded tags with global management

### Library and Marketplace

- 25 pre-seeded library skills across 6 categories
- Library import with slug collision handling
- Self-hosted marketplace with publish, install, vote, and download tracking
- Category browsing and full-text search

### Sharing

- Bundle export as ZIP or JSON (skills + agents + metadata)
- Bundle import with conflict resolution: skip, overwrite, rename

### Integrations

- Outbound webhooks for skill.created, skill.updated, skill.deleted, project.synced
- HMAC-SHA256 payload signing
- Delivery logs and test endpoint
- GitHub inbound webhook for push-triggered project scans

### Git Integration

- Per-project git_auto_commit setting
- Auto-commit skill files after save
- Git log and diff API endpoints
