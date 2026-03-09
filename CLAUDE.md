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
| LLM Runtime | Anthropic PHP SDK (`anthropic-php/client`) | Latest |
| State Mgmt | Zustand | Latest |
| HTTP Client | Axios | Latest |
| Container | Docker + Docker Compose | Latest |

## Project Structure

The project is a Laravel 12 app at the repository root. A separate React SPA lives in `ui/`.

```
agentis-studio/
├── app/                    # Laravel application
│   ├── Filament/           # Filament resources & pages
│   │   ├── Resources/      # ProjectResource, LibrarySkillResource, TagResource
│   │   └── Pages/          # Dashboard, Settings
│   ├── Http/Controllers/   # API controllers consumed by React SPA
│   ├── Http/Resources/     # API Resources (ProjectResource, SkillResource, VersionResource)
│   ├── Models/             # Eloquent models
│   ├── Services/           # Business logic
│   │   ├── AgentisManifestService.php
│   │   ├── SkillFileParser.php
│   │   ├── ProviderSyncService.php
│   │   └── Providers/      # 6 provider sync drivers
│   └── Jobs/               # ProjectScanJob
├── database/migrations/    # Schema migrations
├── routes/
│   ├── api.php             # REST API for React SPA
│   └── web.php             # Filament auto-registers here
├── ui/                     # React + Vite + TypeScript SPA
│   ├── src/
│   │   ├── pages/          # Projects, ProjectDetail, SkillEditor, Library, Search
│   │   ├── components/     # layout/, skills/, library/
│   │   ├── store/          # Zustand (useAppStore.ts)
│   │   ├── api/            # Axios client (client.ts)
│   │   └── types/          # TypeScript types (index.ts)
│   └── vite.config.ts
├── docker-compose.yml      # php, nginx, ui, mariadb, adminer
├── docker/                 # Dockerfiles & nginx config
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
| Version history + diff viewer | React SPA |
| Cross-project search | React SPA |

## Database Schema

Tables: `projects`, `project_providers`, `skills`, `skill_versions`, `tags`, `skill_tag` (pivot), `library_skills`, `app_settings`.

- `skills.tools` is a JSON column
- `skill_versions.frontmatter` is a JSON column
- `library_skills.tags` and `library_skills.frontmatter` are JSON columns
- `app_settings.key` is unique — use static helpers `AppSetting::get()` / `AppSetting::set()`

## Skill File Format

Canonical format is YAML frontmatter + Markdown body, stored in `.agentis/skills/`:

```markdown
---
id: summarize-doc
name: Summarize Document
description: Summarizes any document to key bullet points
tags: [summarization, documents]
model: claude-sonnet-4-20250514
max_tokens: 1000
tools: []
created_at: 2026-01-15T09:00:00Z
updated_at: 2026-03-09T14:22:00Z
---

You are a precise document summarizer...
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
GET|POST       /api/projects
GET|PUT|DELETE /api/projects/{id}
POST           /api/projects/{id}/scan
POST           /api/projects/{id}/sync

GET|POST       /api/projects/{id}/skills
GET|PUT|DELETE /api/skills/{id}
POST           /api/skills/{id}/duplicate

GET            /api/skills/{id}/versions
GET            /api/skills/{id}/versions/{v}
POST           /api/skills/{id}/versions/{v}/restore

POST           /api/skills/{id}/test          # SSE streaming

GET|POST       /api/tags
DELETE         /api/tags/{id}

GET            /api/search?q=&tags=&project_id=&model=

GET            /api/library?category=&tags=&q=
POST           /api/library/{id}/import

GET            /api/settings
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
make shell-php   # bash into php container
make shell-ui    # sh into ui container
make logs        # docker compose logs -f

# Local dev (without Docker)
composer dev     # runs server, queue, pail, vite concurrently
composer test    # clears config + runs tests
```

## Key Conventions

- **No auth** — single-user app, no Sanctum or session auth on the API
- **Pest PHP** for testing
- **YAML parsing** uses `symfony/yaml`
- **Streaming** uses SSE (Server-Sent Events) for live test runner
- **File I/O** uses Laravel's local filesystem disk named "projects"
- **Slugs** are auto-generated from skill names, unique within a project
- **Version snapshots** are created on every skill save
- **Provider sync** is triggered explicitly (not automatic on save)

## Access Points

| Interface | URL |
|---|---|
| React SPA | http://localhost:5173 |
| Filament Admin | http://localhost:8000/admin |
| Laravel API | http://localhost:8000/api |
| Adminer (dev) | http://localhost:8080 |

## Implementation Phases

1. Docker environment & project scaffold
2. Database migrations & models
3. File I/O & manifest engine (`SkillFileParser`, `AgentisManifestService`)
4. Provider sync engine (6 drivers implementing `ProviderDriverInterface`)
5. Filament admin panel (ProjectResource, LibrarySkillResource, TagResource, Settings)
6. Skills CRUD API
7. React SPA core UI (routing, layout, Monaco editor, Zustand store)
8. Live test runner (SSE streaming via Anthropic PHP SDK)
9. Version history & diff viewer (Monaco Diff Editor)
10. Global library & search (25 seeded skills, FULLTEXT search)
11. Settings, polish & QA
