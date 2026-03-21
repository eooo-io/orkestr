# Orkestr — Project Status

> Last updated: 2026-03-12 | Commit: `aa7b530` | 38 commits on `main`

## Overview

Orkestr is a self-hosted Agent OS for designing, executing, and managing autonomous AI agents. Developers define reusable AI skills (prompt + config) in a provider-agnostic format under `.agentis/`, then sync them to any supported AI provider's native config format.

**Stack:** Laravel 12 (PHP 8.4) + Filament 3 + React 19 + TypeScript + Vite 7 + Tailwind v4 + shadcn/ui + Monaco Editor + D3.js + MariaDB 11 + Docker

## Codebase Metrics

| Metric | Count |
|---|---|
| Total PHP files | 184 |
| Total TypeScript/TSX files | 64 |
| PHP lines (app/) | ~11,000 |
| TypeScript lines (ui/src/) | ~12,800 |
| **Total lines of code** | **~27,000** |
| Eloquent models | 22 |
| Controllers | 27 |
| Services | 34 |
| Middleware | 4 |
| Artisan commands | 4 |
| Database migrations | 32 |
| Seeders | 5 |
| React pages | 13 |
| React components | 40 |
| API route definitions | ~100 |

## Implemented Features

### Core Engine
- [x] YAML frontmatter + Markdown skill file format (`.agentis/skills/`)
- [x] Skill CRUD with slug auto-generation, unique per project
- [x] Version snapshots on every skill save
- [x] Version history with Monaco diff viewer
- [x] Skill duplication
- [x] Bulk operations (tag, assign, delete, move)
- [x] Template variables with `{{ variable }}` syntax and per-project resolution
- [x] Skill composition via `includes:` (skill-within-skill references)
- [x] Conditional skill activation (file patterns & path prefixes for context-aware delivery)
- [x] Prompt linting

### Provider Sync (7 Providers)
- [x] **Claude** — `.claude/CLAUDE.md` (all skills under H2 headings)
- [x] **Cursor** — `.cursor/rules/{slug}.mdc` (one MDC file per skill)
- [x] **Copilot** — `.github/copilot-instructions.md` (concatenated)
- [x] **Windsurf** — `.windsurf/rules/{slug}.md` (one file per skill)
- [x] **Cline** — `.clinerules` (single flat file)
- [x] **OpenAI** — `.openai/instructions.md` (concatenated)
- [x] **OpenClaw** — MCP + A2A protocol support, tool/resource/prompt server config
- [x] MCP server configuration synced across all providers via `GeneratesMcpConfig` trait
- [x] Sync preview before writing files
- [x] Conditional skill rendering per provider format

### Reverse-Sync Import
- [x] Auto-detect provider config files in a project directory
- [x] Parse existing provider configs back into Agentis skill format
- [x] Supports all 6 original providers (Claude, Cursor, Copilot, Windsurf, Cline, OpenAI)
- [x] Import UI tab in project detail

### Agent Composition
- [x] Define reusable agent personas (model, temperature, system prompt)
- [x] Assign skills to agents per project
- [x] Compose agent configs with resolved skill content
- [x] Agent preview panel

### AI-Powered Features
- [x] Live test runner with SSE streaming (Anthropic PHP SDK)
- [x] Multi-provider LLM runtime (Anthropic, OpenAI, Gemini, Ollama)
- [x] AI skill generation from natural language description
- [x] Playground mode (freeform prompt testing)

### CLI Tools (Artisan Commands)
- [x] `agentis:list` — List all projects and their skills
- [x] `agentis:scan` — Scan a project directory for skill files
- [x] `agentis:sync` — Sync skills to provider configs from terminal
- [x] `agentis:import` — Import skills from provider config files

### D3.js Visualization
- [x] **Full Project Overview** — Force-directed graph of projects, skills, agents, providers
- [x] **Skill Dependencies** — Dependency graph via `includes:` references
- [x] **Agent Composition** — Tree view of agent-to-skill assignments
- [x] **Sync Flow** — Provider sync pipeline visualization
- [x] Zoom, pan, drag, hover tooltips on all views
- [x] Available as project tab and full-screen page

### Marketplace & Library
- [x] 25+ seeded library skills across categories
- [x] Library import into projects
- [x] Skills.sh integration (discover, preview, import)
- [x] Marketplace publish/install/vote
- [x] Bundle export/import (portable skill packages)

### Monetization & Billing (Stripe)
- [x] Subscription plans (Free, Pro, Team, Enterprise) with plan seeder
- [x] Stripe Checkout integration for subscriptions
- [x] Plan changes (upgrade/downgrade) and cancellation/resume
- [x] Usage metering and budget tracking
- [x] Invoice history
- [x] Stripe Connect for marketplace seller payouts
- [x] Stripe webhook handler for payment events
- [x] Plan-based feature gating middleware (`CheckPlanFeature`, `CheckPlanLimit`, `CheckUsageBudget`)

### Organizations & Multi-Tenancy
- [x] Organization model with user membership and roles
- [x] `BelongsToOrganization` trait for scoped models
- [x] `ResolveOrganization` middleware
- [x] Organization-scoped projects, skills, and billing

### Authentication
- [x] User registration and login (Laravel Sanctum)
- [x] GitHub OAuth sign-in
- [x] Apple sign-in
- [x] `AuthGuard` component in React SPA

### Repository Connections
- [x] GitHub and GitLab repository linking
- [x] Scoped file access (AI config files only)
- [x] Branch listing and latest commit info
- [x] Pull/push skills to/from remote repositories
- [x] Repository status monitoring

### Webhooks
- [x] Outbound webhooks with configurable events per project
- [x] Webhook delivery history and retry
- [x] Inbound GitHub webhook receiver
- [x] Test webhook delivery

### Admin Panel (Filament)
- [x] Project registry CRUD
- [x] Provider config per project
- [x] Library skill management
- [x] Tag management
- [x] App settings (API keys, defaults)

### React SPA
- [x] Project list, detail, and form pages
- [x] Skill editor with Monaco Editor
- [x] Frontmatter form with conditions editor
- [x] Live test panel with SSE streaming
- [x] Version history with diff viewer
- [x] Cross-project FULLTEXT search
- [x] Sidebar navigation with command palette
- [x] Billing/subscription management page
- [x] Login and registration pages
- [x] Zustand state management
- [x] Toast notifications

### Infrastructure
- [x] Docker Compose (PHP + MariaDB)
- [x] Makefile with common dev commands
- [x] Pest PHP test suite
- [x] Git integration (auto-commit, log, diff)
- [x] Compliance skill seeder (RBAC patterns)

## Architecture

```
┌─────────────────────────────────────────────────┐
│                  React SPA (Vite)                │
│  Pages: Projects, SkillEditor, Billing, etc.     │
│  State: Zustand  │  HTTP: Axios  │  UI: shadcn   │
└────────────────────────┬────────────────────────┘
                         │ REST API
┌────────────────────────┴────────────────────────┐
│               Laravel 12 Backend                 │
│                                                  │
│  Controllers (27) ──► Services (34)              │
│       │                    │                     │
│       ▼                    ▼                     │
│  Eloquent Models (22)   Provider Drivers (7)     │
│       │                    │                     │
│       ▼                    ▼                     │
│   MariaDB            .agentis/ files             │
│                            │                     │
│                            ▼                     │
│                   Provider Config Files           │
│          (.claude/ .cursor/ .github/ etc.)        │
└──────────────────────────────────────────────────┘
```

## Database Tables

`users`, `organizations`, `organization_user`, `plans`, `projects`, `project_providers`, `skills`, `skill_versions`, `skill_variables`, `tags`, `skill_tag`, `agents`, `agent_skill`, `project_agent`, `library_skills`, `marketplace_skills`, `marketplace_payouts`, `webhooks`, `webhook_deliveries`, `project_repositories`, `project_mcp_servers`, `project_a2a_agents`, `openclaw_configs`, `usage_records`, `subscriptions`, `subscription_items`, `app_settings`, `cache`, `jobs`, `job_batches`, `failed_jobs`

## API Surface

~100 route definitions across these resource groups:

| Group | Endpoints |
|---|---|
| Projects | CRUD, scan, sync, preview, git log/diff |
| Skills | CRUD, duplicate, lint, bulk ops |
| Template Variables | index, update |
| Test Runner | skill test (SSE), playground |
| AI Generation | generate skill from description |
| Versions | list, show, restore |
| Tags | CRUD |
| Search | full-text search |
| Library | list, import |
| Skills.sh | discover, preview, import |
| Agents | CRUD, toggle, compose |
| Bundles | export, import |
| Marketplace | list, show, publish, install, vote |
| Webhooks | CRUD, deliveries, test, inbound |
| Repositories | connect, status, branches, files, pull, push |
| OpenClaw | show, update |
| MCP Servers | CRUD |
| A2A Agents | CRUD |
| Visualization | project graph data |
| Import | detect, import (reverse-sync) |
| Models | list available LLM models |
| Billing | plans, subscribe, change, cancel, invoices, usage |
| Stripe Connect | setup, status, earnings |
| Auth | register, login, logout, social OAuth |
| Settings | get, update |
