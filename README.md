<div align="center">

```
 █████╗  ██████╗ ███████╗███╗   ██╗████████╗██╗███████╗
██╔══██╗██╔════╝ ██╔════╝████╗  ██║╚══██╔══╝██║██╔════╝
███████║██║  ███╗█████╗  ██╔██╗ ██║   ██║   ██║███████╗
██╔══██║██║   ██║██╔══╝  ██║╚██╗██║   ██║   ██║╚════██║
██║  ██║╚██████╔╝███████╗██║ ╚████║   ██║   ██║███████║
╚═╝  ╚═╝ ╚═════╝ ╚══════╝╚═╝  ╚═══╝   ╚═╝   ╚═╝╚══════╝
                    S  T  U  D  I  O
```

**Universal AI skill/agent configuration manager for multi-provider development workflows.**

</div>

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.4-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.4">
  <img src="https://img.shields.io/badge/Laravel-12.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel 12">
  <img src="https://img.shields.io/badge/Filament-3.x-FDAE4B?style=for-the-badge&logo=laravel&logoColor=white" alt="Filament 3">
  <img src="https://img.shields.io/badge/React-19-61DAFB?style=for-the-badge&logo=react&logoColor=black" alt="React">
  <img src="https://img.shields.io/badge/TypeScript-5.x-3178C6?style=for-the-badge&logo=typescript&logoColor=white" alt="TypeScript">
  <img src="https://img.shields.io/badge/Tailwind_CSS-4.x-06B6D4?style=for-the-badge&logo=tailwindcss&logoColor=white" alt="Tailwind CSS 4">
  <img src="https://img.shields.io/badge/shadcn%2Fui-latest-000000?style=for-the-badge&logo=shadcnui&logoColor=white" alt="shadcn/ui">
  <img src="https://img.shields.io/badge/Monaco_Editor-latest-1E1E1E?style=for-the-badge&logo=visualstudiocode&logoColor=white" alt="Monaco Editor">
  <img src="https://img.shields.io/badge/MariaDB-11.x-003545?style=for-the-badge&logo=mariadb&logoColor=white" alt="MariaDB 11">
  <img src="https://img.shields.io/badge/Docker-Compose-2496ED?style=for-the-badge&logo=docker&logoColor=white" alt="Docker">
  <img src="https://img.shields.io/badge/Anthropic-Claude_API-D4A574?style=for-the-badge&logo=anthropic&logoColor=white" alt="Anthropic">
  <img src="https://img.shields.io/badge/OpenAI-GPT--4o-412991?style=for-the-badge&logo=openai&logoColor=white" alt="OpenAI">
  <img src="https://img.shields.io/badge/Google-Gemini-4285F4?style=for-the-badge&logo=google&logoColor=white" alt="Google Gemini">
  <img src="https://img.shields.io/badge/Ollama-Local-000000?style=for-the-badge&logo=ollama&logoColor=white" alt="Ollama">
</p>

---

## What It Does

Define, edit, and organize reusable AI skills (prompts + config) in a **provider-agnostic format**, then sync them outward to the native config format of any supported AI coding assistant.

**Core philosophy:** `.agentis/` is the single source of truth. All provider-specific files are derived outputs — never edited directly.

### Supported Providers

| Provider | Output | Format |
|---|---|---|
| Claude | `.claude/CLAUDE.md` | All skills under H2 headings |
| Cursor | `.cursor/rules/{slug}.mdc` | One MDC file per skill |
| GitHub Copilot | `.github/copilot-instructions.md` | All skills concatenated |
| Windsurf | `.windsurf/rules/{slug}.md` | One file per skill |
| Cline | `.clinerules` | Single flat file |
| OpenAI | `.openai/instructions.md` | All skills concatenated |

---

## Features

### Skill Management
- **Monaco Editor** — Full-featured code editor with Markdown syntax highlighting for editing skill prompts
- **YAML Frontmatter** — Skills stored as portable `.md` files with structured metadata (model, tags, tools, max_tokens)
- **Version History** — Every save creates a snapshot; compare any two versions with Monaco Diff Editor and restore with one click
- **Skill Dependencies** — `includes` system for composable prompts with recursive resolution and circular dependency detection
- **Template Variables** — `{{variable}}` placeholders in skills with per-project values, resolved at compose/sync time
- **Prompt Linting** — 8 rule-based checks (vague instructions, weak constraints, conflicting directives, missing output format, excessive length, role confusion, missing examples, redundancy)
- **Duplicate & Move** — Clone skills within or across projects; bulk move multiple skills at once
- **Token Estimation** — Live token count with model-specific context limit warnings (color-coded at 75%/90% thresholds)
- **Git Auto-Commit** — Optionally commit skill file changes to the project's git repo automatically on every save

### AI-Powered
- **Skill Generation** — Describe what you want in natural language and Claude generates a complete skill with frontmatter, tags, and body
- **Multi-Model Test Runner** — Stream test responses from Anthropic (Claude), OpenAI (GPT-4o, o3), Google Gemini, and local Ollama models
- **Playground** — Multi-turn chat interface with project/skill/agent system prompt picker, per-turn stats (tokens, elapsed time), and abort support

### Agent System
- **9 Pre-built Agents** — Orchestrator, PM, Architect, QA, Design, Code Review, Infrastructure, CI/CD, Security
- **Agent Compose** — Merge base instructions + custom per-project instructions + assigned skill bodies into a single composed output
- **Per-Project Configuration** — Enable/disable agents, set custom instructions, and assign skills per project
- **Token Budget Preview** — See composed agent output with token estimates before syncing

### Provider Sync
- **6 Providers** — Claude, Cursor, GitHub Copilot, Windsurf, Cline, OpenAI
- **Diff Preview** — Side-by-side Monaco diff showing exactly what will change before confirming a sync
- **Dry-Run Mode** — Preview proposed file changes per provider without writing to disk
- **Template Resolution** — Variables and includes are resolved before writing to provider files

### Organization & Discovery
- **Command Palette** — `Ctrl+K` / `Cmd+K` for instant fuzzy search across skills, projects, pages, and actions
- **Tags** — Categorize skills with color-coded tags; filter and search by tag
- **Cross-Project Search** — FULLTEXT search across all skills with tag, project, and model filters
- **Bulk Operations** — Multi-select skills for batch tagging, agent assignment, moving, and deletion

### Sharing & Collaboration
- **Skill Library** — 25 pre-seeded skills across 6 categories (Laravel, PHP, TypeScript, FinTech, DevOps, Writing)
- **Bundle Export/Import** — Export selected skills and agents as ZIP or JSON bundles; import with conflict resolution (skip/overwrite/rename)
- **Skill Marketplace** — Self-hosted marketplace for publishing, discovering, and installing community skills with ratings and download tracking

### Integrations
- **Webhook System** — Configure outbound webhooks for skill/project events with HMAC-SHA256 signing and delivery logs
- **GitHub Inbound Webhooks** — Receive push events to auto-scan projects for new or changed skills
- **Filament Admin Panel** — Full admin UI for project registry, provider config, library management, tags, and settings

---

## Architecture

```
┌─────────────────────────────────────────────────────────┐
│                    React SPA (Vite)                      │
│  Skills Editor · Playground · Search · Library · Market  │
└──────────────────────┬──────────────────────────────────┘
                       │ REST API + SSE
┌──────────────────────┴──────────────────────────────────┐
│                  Laravel 12 Backend                       │
│  Controllers · Services · Jobs · LLM Providers           │
├──────────────┬──────────────┬───────────────────────────┤
│ Filament 3.x │ Provider Sync│  Multi-Model LLM Layer    │
│ Admin Panel  │ (6 drivers)  │  Anthropic · OpenAI ·     │
│              │              │  Gemini · Ollama           │
└──────┬───────┴──────┬───────┴───────────┬───────────────┘
       │              │                   │
  MariaDB 11    .agentis/ files     External APIs
```

- **Filament Admin** — Project registry, provider config, global library, tags, settings
- **React SPA** — Skill editing (Monaco), playground (multi-turn chat), version history (diff viewer), marketplace, cross-project search

---

## Quick Start

### With Docker

```bash
cp .env.example .env
# Edit .env — set PROJECTS_HOST_PATH to your local dev directory

make build
make up
make migrate

# Start the React SPA locally
cd ui && npm install && npm run dev
```

Docker runs PHP + MariaDB. The Vite dev server runs locally for faster HMR.

### Without Docker

```bash
composer install
cp .env.example .env
php artisan key:generate

# Configure DB_HOST=127.0.0.1 and DB credentials in .env

php artisan migrate --seed
cd ui && npm install && cd ..

# Start everything (server, queue, pail, vite)
composer dev
```

### Access Points

| Interface | URL |
|---|---|
| React SPA | http://localhost:5173 |
| Filament Admin | http://localhost:8000/admin |
| Laravel API | http://localhost:8000/api |

---

## Skill File Format

Skills are stored as YAML frontmatter + Markdown body in `.agentis/skills/`:

```markdown
---
id: summarize-doc
name: Summarize Document
description: Summarizes any document to key bullet points
tags: [summarization, documents]
model: claude-sonnet-4-6
max_tokens: 1000
includes: [base-instructions]
template_variables: [language, tone]
---

You are a precise document summarizer.
Write in {{language}} with a {{tone}} tone.
```

Required frontmatter fields: `id`, `name`. All others are optional.

---

## Project Structure

```
agentis-studio/
├── app/
│   ├── Filament/           # Admin panel resources & pages
│   ├── Http/Controllers/   # API controllers (consumed by React SPA)
│   ├── Http/Resources/     # API Resources
│   ├── Models/             # Eloquent models
│   ├── Services/           # Business logic
│   │   ├── AgentComposeService.php
│   │   ├── AgentisManifestService.php
│   │   ├── BundleExportService.php
│   │   ├── BundleImportService.php
│   │   ├── GitService.php
│   │   ├── PromptLinter.php
│   │   ├── ProviderSyncService.php
│   │   ├── SkillCompositionService.php
│   │   ├── SkillFileParser.php
│   │   ├── TemplateResolver.php
│   │   ├── WebhookDispatcher.php
│   │   ├── LLM/            # Multi-model provider layer
│   │   └── Providers/      # 6 provider sync drivers
│   └── Jobs/               # ProjectScanJob
├── database/migrations/    # Schema migrations
├── routes/
│   ├── api.php             # REST API for React SPA
│   └── web.php             # Filament auto-registers here
├── ui/                     # React + Vite + TypeScript SPA
│   └── src/
│       ├── pages/          # Projects, SkillEditor, Playground, Library, Marketplace, Search
│       ├── components/     # layout/, skills/, library/, agents/, marketplace/
│       ├── store/          # Zustand store
│       ├── api/            # Axios client
│       └── types/          # TypeScript types
├── docker-compose.yml
├── docker/                 # Dockerfile & PHP config
└── Makefile
```

---

## API Endpoints

All consumed by the React SPA. No auth middleware — single-user local application.

<details>
<summary>Full API reference</summary>

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

# Versions
GET            /api/skills/{id}/versions
GET            /api/skills/{id}/versions/{v}
POST           /api/skills/{id}/versions/{v}/restore

# Agents
GET            /api/agents
GET            /api/projects/{id}/agents
POST           /api/projects/{id}/agents/{agentId}/toggle
PUT             /api/projects/{id}/agents/{agentId}/instructions
POST           /api/projects/{id}/agents/{agentId}/skills
GET            /api/projects/{id}/agents/{agentId}/compose
GET            /api/projects/{id}/agents/compose-all

# Testing & Playground
POST           /api/skills/{id}/test          (SSE streaming)
POST           /api/playground                 (SSE streaming)

# Tags, Search, Library
GET|POST       /api/tags
DELETE         /api/tags/{id}
GET            /api/search?q=&tags=&project_id=&model=
GET            /api/library?category=&tags=&q=
POST           /api/library/{id}/import

# Marketplace
GET            /api/marketplace
POST           /api/marketplace/publish
POST           /api/marketplace/{id}/install
POST           /api/marketplace/{id}/vote

# Bulk Operations
POST           /api/skills/bulk-tag
POST           /api/skills/bulk-assign
POST           /api/skills/bulk-delete
POST           /api/skills/bulk-move

# Bundle Export/Import
POST           /api/projects/{id}/export
POST           /api/projects/{id}/import-bundle

# Template Variables
GET|PUT        /api/projects/{id}/skills/{skillId}/variables

# Webhooks
GET|POST       /api/projects/{id}/webhooks
PUT|DELETE     /api/webhooks/{id}
GET            /api/webhooks/{id}/deliveries
POST           /api/webhooks/{id}/test
POST           /api/webhooks/inbound/github

# Settings & Models
GET            /api/settings
GET            /api/models
```

</details>

---

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
make logs        # docker compose logs -f

# Local dev
composer dev     # runs server, queue, pail, vite concurrently
composer test    # clears config + runs tests

# Type checking
cd ui && npx tsc --noEmit
```

---

## Tech Stack

| Layer | Technology |
|---|---|
| Runtime | PHP 8.4 |
| Framework | Laravel 12.x |
| Admin UI | Filament 3.x + Livewire 4.x |
| Frontend SPA | React 19 + Vite + TypeScript |
| Styling | Tailwind CSS v4 + shadcn/ui |
| Code Editor | Monaco Editor |
| State Management | Zustand |
| Database | MariaDB 11.x |
| LLM Providers | Anthropic, OpenAI, Google Gemini, Ollama |
| Container | Docker + Docker Compose |

---

## License

MIT
