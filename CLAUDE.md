# Agentis Studio

> Universal AI skill/agent configuration manager for multi-provider development workflows.

## What This Project Does

Agentis Studio lets a developer define, edit, and organize reusable AI skills (prompts + config) in a provider-agnostic format, then sync them outward to the native config format of any supported AI provider (Claude, Cursor, Copilot, Windsurf, Cline, OpenAI).

**Core philosophy:** `.agentis/` is the single source of truth. All provider-specific files are derived outputs — never edited directly.

## Tech Stack

| Layer | Technology | Version |
|---|---|---|
| Runtime | PHP | 8.4 |
| Framework | Laravel | 12.x |
| Admin UI | Filament | 3.x |
| Reactive | Livewire | 4.x |
| Frontend SPA | React + Vite + TypeScript | Latest |
| Styling | Tailwind CSS | v4 |
| Components | shadcn/ui | Latest |
| Code Editor | Monaco Editor | Latest |
| Database | MariaDB | 11.x |
| LLM Providers | Anthropic (mozex/anthropic-laravel), OpenAI, Gemini, Ollama | Latest |
| State Mgmt | Zustand | Latest |
| HTTP Client | Axios | Latest |
| Container | Docker + Docker Compose | Latest |

## Project Structure

The project is a Laravel 12 app at the repository root. A separate React SPA lives in `ui/`. Documentation site (VitePress) in `docs/`.

```
agentis-studio/
├── app/                    # Laravel application
│   ├── Filament/           # Filament resources & pages
│   │   ├── Resources/      # ProjectResource, LibrarySkillResource, TagResource
│   │   └── Pages/          # Dashboard, Settings
│   ├── Http/Controllers/   # API controllers consumed by React SPA
│   │   ├── AgentController.php
│   │   ├── BulkSkillController.php
│   │   ├── BundleController.php
│   │   ├── InboundWebhookController.php
│   │   ├── LibraryController.php
│   │   ├── MarketplaceController.php
│   │   ├── ModelController.php
│   │   ├── ProjectController.php
│   │   ├── SearchController.php
│   │   ├── SettingsController.php
│   │   ├── SkillController.php
│   │   ├── SkillGenerateController.php
│   │   ├── SkillTestController.php
│   │   ├── SkillVariableController.php
│   │   ├── SkillsShController.php
│   │   ├── TagController.php
│   │   ├── VersionController.php
│   │   └── WebhookController.php
│   ├── Http/Resources/     # API Resources (ProjectResource, SkillResource, VersionResource)
│   ├── Models/             # Eloquent models
│   ├── Services/           # Business logic
│   │   ├── AgentComposeService.php      # Merge agent base + custom + skills
│   │   ├── AgentisManifestService.php   # .agentis/ directory management
│   │   ├── BundleExportService.php      # ZIP/JSON bundle export
│   │   ├── BundleImportService.php      # Bundle import with conflict resolution
│   │   ├── GitService.php              # Git auto-commit, log, diff
│   │   ├── PromptLinter.php            # 8 prompt quality rules
│   │   ├── ProviderSyncService.php     # Orchestrates all provider drivers
│   │   ├── SkillCompositionService.php # Recursive includes resolution
│   │   ├── SkillFileParser.php         # YAML frontmatter + Markdown parser
│   │   ├── SkillsShService.php         # GitHub skills.sh discovery/import
│   │   ├── TemplateResolver.php        # {{variable}} substitution
│   │   ├── WebhookDispatcher.php       # Outbound webhook delivery
│   │   ├── LLM/                        # Multi-model provider layer
│   │   │   ├── LLMProviderFactory.php  # Routes models to providers
│   │   │   ├── LLMProviderInterface.php
│   │   │   ├── AnthropicProvider.php
│   │   │   ├── OpenAIProvider.php
│   │   │   ├── GeminiProvider.php
│   │   │   └── OllamaProvider.php
│   │   └── Providers/                  # 6 provider sync drivers
│   │       ├── ProviderDriverInterface.php
│   │       ├── ClaudeDriver.php
│   │       ├── CursorDriver.php
│   │       ├── CopilotDriver.php
│   │       ├── WindsurfDriver.php
│   │       ├── ClineDriver.php
│   │       └── OpenAIDriver.php
│   └── Jobs/               # ProjectScanJob
├── database/
│   ├── migrations/         # Schema migrations
│   └── seeders/            # AgentSeeder (9 agents), LibrarySkillSeeder (25 skills)
├── docs/                   # VitePress documentation site
├── routes/
│   ├── api.php             # REST API for React SPA
│   └── web.php             # Filament auto-registers here
├── ui/                     # React + Vite + TypeScript SPA
│   └── src/
│       ├── pages/          # Projects, ProjectDetail, SkillEditor, Playground, Library, Marketplace, Search, Settings
│       ├── components/     # layout/, skills/, library/, agents/, marketplace/
│       ├── store/          # Zustand (useAppStore.ts)
│       ├── api/            # Axios client (client.ts)
│       └── types/          # TypeScript types (index.ts)
├── docker-compose.yml      # php, mariadb
├── docker/                 # Dockerfile & php config
└── Makefile
```

## Architecture Split: Filament vs React SPA

| Concern | Handled By |
|---|---|
| Project registry (CRUD) | Filament |
| Provider config per project | Filament |
| Global library management | Filament |
| App settings (API keys, defaults) | Filament |
| Tag management | Filament |
| Skill CRUD + Monaco editor | React SPA |
| Live test runner (SSE streaming) | React SPA |
| Playground (multi-turn chat) | React SPA |
| Version history + diff viewer | React SPA |
| Agent configuration + compose preview | React SPA |
| Marketplace (publish/install/vote) | React SPA |
| Cross-project search | React SPA |
| Bundle export/import | React SPA |
| Webhook configuration | React SPA |
| Command palette | React SPA |

## Database Schema

Tables: `projects`, `project_providers`, `skills`, `skill_versions`, `tags`, `skill_tag` (pivot), `library_skills`, `app_settings`, `agents`, `project_agent` (pivot), `agent_skill` (pivot), `marketplace_skills`, `webhooks`, `webhook_deliveries`, `skill_variables`.

- `skills.tools` is a JSON column
- `skills.includes` is a JSON column (skill slug references)
- `skills.template_variables` is a JSON column
- `skill_versions.frontmatter` is a JSON column
- `library_skills.tags` and `library_skills.frontmatter` are JSON columns
- `app_settings.key` is unique — use static helpers `AppSetting::get()` / `AppSetting::set()`
- `project_agent` has `is_enabled`, `custom_instructions` columns
- `agent_skill` has `project_id` column for per-project skill assignment

## Skill File Format

Canonical format is YAML frontmatter + Markdown body, stored in `.agentis/skills/`:

```markdown
---
id: summarize-doc
name: Summarize Document
description: Summarizes any document to key bullet points
tags: [summarization, documents]
model: claude-sonnet-4-6
max_tokens: 1000
tools: []
includes: [base-instructions]
template_variables:
  - name: language
    description: Output language
    default: English
created_at: 2026-01-15T09:00:00Z
updated_at: 2026-03-09T14:22:00Z
---

You are a precise document summarizer. Write in {{language}}...
```

Required frontmatter fields: `id`, `name`. All others are optional.

## Provider Sync Outputs

| Provider | Output Path | Format |
|---|---|---|
| Claude | `.claude/CLAUDE.md` | All skills under H2 headings |
| Cursor | `.cursor/rules/{slug}.mdc` | One MDC file per skill |
| Copilot | `.github/copilot-instructions.md` | All skills concatenated |
| Windsurf | `.windsurf/rules/{slug}.md` | One file per skill |
| Cline | `.clinerules` | Single flat file |
| OpenAI | `.openai/instructions.md` | All skills concatenated |

## API Endpoints

All consumed by the React SPA. No auth middleware — single-user application.

```
# Projects
GET|POST       /api/projects
GET|PUT|DELETE  /api/projects/{id}
POST           /api/projects/{id}/scan
POST           /api/projects/{id}/sync
POST           /api/projects/{id}/sync/preview
GET            /api/projects/{id}/git-log
GET            /api/projects/{id}/git-diff

# Skills
GET|POST       /api/projects/{id}/skills
GET|PUT|DELETE  /api/skills/{id}
POST           /api/skills/{id}/duplicate
GET            /api/skills/{id}/lint
POST           /api/skills/generate

# Bulk Operations
POST           /api/skills/bulk-tag
POST           /api/skills/bulk-assign
POST           /api/skills/bulk-delete
POST           /api/skills/bulk-move

# Template Variables
GET|PUT        /api/projects/{id}/skills/{skillId}/variables

# Versions
GET            /api/skills/{id}/versions
GET            /api/skills/{id}/versions/{v}
POST           /api/skills/{id}/versions/{v}/restore

# Testing & Playground
POST           /api/skills/{id}/test          # SSE streaming
POST           /api/playground                 # SSE streaming

# Tags
GET|POST       /api/tags
DELETE         /api/tags/{id}

# Search
GET            /api/search?q=&tags=&project_id=&model=

# Library
GET            /api/library?category=&tags=&q=
POST           /api/library/{id}/import

# Skills.sh (GitHub import)
POST           /api/skills-sh/discover
POST           /api/skills-sh/preview
POST           /api/skills-sh/import

# Agents
GET            /api/agents
GET            /api/projects/{id}/agents
PUT            /api/projects/{id}/agents/{agentId}/toggle
PUT            /api/projects/{id}/agents/{agentId}/instructions
PUT            /api/projects/{id}/agents/{agentId}/skills
GET            /api/projects/{id}/agents/{agentId}/compose
GET            /api/projects/{id}/agents/compose

# Bundles
POST           /api/projects/{id}/export
POST           /api/projects/{id}/import-bundle

# Marketplace
GET            /api/marketplace
GET            /api/marketplace/{id}
POST           /api/marketplace/publish
POST           /api/marketplace/{id}/install
POST           /api/marketplace/{id}/vote

# Webhooks
GET            /api/projects/{id}/webhooks
POST           /api/projects/{id}/webhooks
PUT|DELETE     /api/webhooks/{id}
GET            /api/webhooks/{id}/deliveries
POST           /api/webhooks/{id}/test
POST           /api/webhooks/github/{projectId}    # Inbound GitHub push

# Models & Settings
GET            /api/models
GET|PUT        /api/settings
```

## Development Commands

```bash
# Docker
make up          # docker compose up -d
make down        # docker compose down
make build       # docker compose build --no-cache
make migrate     # php artisan migrate --seed
make fresh       # php artisan migrate:fresh --seed
make test        # php artisan test
make shell       # bash into php container
make tinker      # php artisan tinker
make logs        # docker compose logs -f

# Local dev (without Docker)
composer dev     # runs server, queue, pail, vite concurrently
composer test    # clears config + runs tests

# Type checking
cd ui && npx tsc --noEmit

# Documentation
cd docs && npm run dev    # VitePress dev server
cd docs && npm run build  # Build static site
```

## Key Conventions

- **No auth** — single-user app, no Sanctum or session auth on the API
- **Pest PHP** for testing
- **YAML parsing** uses `symfony/yaml`
- **Streaming** uses SSE (Server-Sent Events) for test runner and playground
- **File I/O** uses Laravel's local filesystem disk named "projects"
- **Slugs** are auto-generated from skill names, unique within a project
- **Version snapshots** are created on every skill save
- **Provider sync** is triggered explicitly (not automatic on save)
- **Multi-model** — LLMProviderFactory routes by model prefix: `claude-` → Anthropic, `gpt-`/`o` → OpenAI, `gemini-` → Gemini, default → Ollama
- **Token estimation** — ~1 token per 4 characters (character-based approximation)
- **Include resolution** — recursive, max depth 5, with circular dependency detection
- **Template resolution** — `{{variable}}` placeholders resolved at compose/sync time, not edit time

## Access Points

| Interface | URL |
|---|---|
| React SPA | http://localhost:5173 (run `cd ui && npm run dev` locally) |
| Filament Admin | http://localhost:8000/admin |
| Laravel API | http://localhost:8000/api |
| Documentation | http://localhost:5174 (run `cd docs && npm run dev`) |
