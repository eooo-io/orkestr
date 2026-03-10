# Agentis Studio

> Universal AI skill/agent configuration manager for multi-provider development workflows.

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white" alt="PHP 8.4">
  <img src="https://img.shields.io/badge/Laravel-12.x-FF2D20?logo=laravel&logoColor=white" alt="Laravel 12">
  <img src="https://img.shields.io/badge/Filament-3.x-FDAE4B?logo=laravel&logoColor=white" alt="Filament 3">
  <img src="https://img.shields.io/badge/Livewire-4.x-FB70A9?logo=livewire&logoColor=white" alt="Livewire 4">
  <img src="https://img.shields.io/badge/React-19-61DAFB?logo=react&logoColor=black" alt="React">
  <img src="https://img.shields.io/badge/TypeScript-5.x-3178C6?logo=typescript&logoColor=white" alt="TypeScript">
  <img src="https://img.shields.io/badge/Vite-7.x-646CFF?logo=vite&logoColor=white" alt="Vite">
  <img src="https://img.shields.io/badge/Tailwind_CSS-4.x-06B6D4?logo=tailwindcss&logoColor=white" alt="Tailwind CSS 4">
  <img src="https://img.shields.io/badge/shadcn%2Fui-latest-000000?logo=shadcnui&logoColor=white" alt="shadcn/ui">
  <img src="https://img.shields.io/badge/Monaco_Editor-latest-1E1E1E?logo=visualstudiocode&logoColor=white" alt="Monaco Editor">
  <img src="https://img.shields.io/badge/MariaDB-11.x-003545?logo=mariadb&logoColor=white" alt="MariaDB 11">
  <img src="https://img.shields.io/badge/Docker-Compose-2496ED?logo=docker&logoColor=white" alt="Docker">
  <img src="https://img.shields.io/badge/Anthropic-Claude_API-D4A574?logo=anthropic&logoColor=white" alt="Anthropic">
</p>

## What It Does

Define, edit, and organize reusable AI skills (prompts + config) in a **provider-agnostic format**, then sync them outward to the native config format of any supported AI provider.

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

### AI-Powered
- **Skill Generation** — Describe what you want in natural language and Claude generates a complete skill with frontmatter
- **Multi-Model Test Runner** — Stream test responses from Anthropic (Claude), OpenAI (GPT-4o, o3), Google Gemini, and local Ollama models
- **Playground** — Multi-turn chat interface with project/skill/agent system prompt picker, per-turn stats, and abort support

### Agent System
- **9 Pre-built Agents** — Orchestrator, PM, Architect, QA, Design, Code Review, Infrastructure, CI/CD, Security
- **Agent Compose** — Merge base instructions + custom per-project instructions + assigned skill bodies into a single output
- **Per-Project Configuration** — Enable/disable agents, set custom instructions, and assign skills per project
- **Token Budget Preview** — See composed agent output with token estimates before syncing

### Provider Sync
- **6 Providers** — Claude, Cursor, GitHub Copilot, Windsurf, Cline, OpenAI
- **Diff Preview** — Side-by-side Monaco diff showing exactly what will change before confirming a sync
- **Dry-Run Mode** — Preview proposed file changes per provider without writing to disk
- **Git Auto-Commit** — Optionally commit skill changes to the project's git repo automatically

### Organization & Discovery
- **Command Palette** — Ctrl+K / Cmd+K for instant fuzzy search across skills, projects, pages, and actions
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

## Architecture

The project is a **Laravel 12** app at the repository root with a separate **React SPA** in `ui/`.

- **Filament Admin** — Project registry, provider config, global library, tags, settings
- **React SPA** — Skill editing (Monaco), live test runner (SSE streaming), version history (diff viewer), cross-project search

## Quick Start

### With Docker

```bash
cp .env.example .env
# Edit .env — set PROJECTS_HOST_PATH to your local dev directory

make build
make up
make migrate
```

| Interface | URL |
|---|---|
| React SPA | http://localhost:5173 |
| Filament Admin | http://localhost:8000/admin |
| Laravel API | http://localhost:8000/api |
| Adminer | http://localhost:8080 |

### Without Docker

```bash
composer install
cp .env.example .env
php artisan key:generate

# Configure DB_HOST=127.0.0.1 and DB credentials in .env

php artisan migrate --seed
npm install

# In ui/
cd ui && npm install

# Start everything
composer dev
```

## Project Structure

```
agentis-studio/
├── app/                    # Laravel application
│   ├── Filament/           # Filament resources & pages
│   ├── Http/Controllers/   # API controllers (consumed by React SPA)
│   ├── Http/Resources/     # API Resources
│   ├── Models/             # Eloquent models
│   ├── Services/           # Business logic + provider sync drivers
│   └── Jobs/               # ProjectScanJob
├── database/migrations/    # Schema migrations
├── routes/
│   ├── api.php             # REST API for React SPA
│   └── web.php             # Filament auto-registers here
├── ui/                     # React + Vite + TypeScript SPA
│   └── src/
│       ├── pages/          # Projects, ProjectDetail, SkillEditor, Library, Search
│       ├── components/     # layout/, skills/, library/
│       ├── store/          # Zustand store
│       ├── api/            # Axios client
│       └── types/          # TypeScript types
├── docker-compose.yml      # php, nginx, ui, mariadb, adminer
├── docker/                 # Dockerfiles & nginx config
└── Makefile
```

## Skill File Format

Skills are stored as YAML frontmatter + Markdown body in `.agentis/skills/`:

```markdown
---
id: summarize-doc
name: Summarize Document
description: Summarizes any document to key bullet points
tags: [summarization, documents]
model: claude-sonnet-4-20250514
max_tokens: 1000
---

You are a precise document summarizer...
```

## Development Commands

```bash
make up          # docker compose up -d
make down        # docker compose down
make build       # docker compose build --no-cache
make migrate     # php artisan migrate --seed
make fresh       # php artisan migrate:fresh --seed
make test        # php artisan test
make shell-php   # bash into php container
make shell-ui    # sh into ui container
make logs        # docker compose logs -f
```

## License

MIT
