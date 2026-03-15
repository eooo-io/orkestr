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
| API token management | React SPA |
| Custom endpoints CRUD | React SPA |
| Model health dashboard | React SPA |
| Local model browser | React SPA |
| Guardrail policies/profiles/violations | React SPA |
| Skill analytics & reports | React SPA |
| Skill reviews & ownership | React SPA |
| Notifications | React SPA |
| GitHub org import | React SPA |

## Database Schema

Tables: `projects`, `project_providers`, `skills`, `skill_versions`, `tags`, `skill_tag` (pivot), `library_skills`, `app_settings`, `agents`, `project_agent` (pivot), `agent_skill` (pivot), `marketplace_skills`, `webhooks`, `webhook_deliveries`, `skill_variables`, `workflows`, `workflow_steps`, `workflow_edges`, `workflow_versions`, `execution_runs`, `execution_steps`, `agent_memories`, `agent_schedules`, `organizations`, `organization_user` (pivot), `organization_invitations`, `sso_providers`, `content_policies`, `guardrail_policies`, `guardrail_profiles`, `guardrail_violations`, `custom_endpoints`, `api_tokens`, `skill_reviews`, `skill_analytics`, `skill_test_cases`, `notifications`, `licenses`, `backups`.

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

## Authentication & Authorization

- **Session-based auth** using Laravel's `auth:web` guard — cookies, not tokens
- **Multi-auth:** email/password, GitHub OAuth, Apple Sign In
- **Multi-tenant:** Organizations with role-based access (owner, admin, editor, viewer, member)
- **Plan-based gates:** free, pro, teams — enforced via `CheckPlanFeature`, `CheckPlanLimit`, `CheckUsageBudget` middleware
- **Filament admin** protected with Filament's `Authenticate` middleware
- **API routes** protected with `auth:web` middleware (session cookies shared with SPA)
- **Organization resolution:** `ResolveOrganization` middleware resolves via `X-Organization-Id` header or user's `current_organization_id`
- **Default seeded user:** `admin@admin.com` / `password`

### Auth Routes (defined in `routes/auth.php`, uses `web` middleware)

```
POST /api/auth/register    # Email registration
POST /api/auth/login       # Email login
POST /api/auth/logout      # Logout (session invalidation)
GET  /api/auth/me          # Current user

GET  /auth/github/redirect # GitHub OAuth redirect
GET  /auth/github/callback # GitHub OAuth callback
GET  /auth/apple/redirect  # Apple Sign In redirect
POST /auth/apple/callback  # Apple Sign In callback (form_post)
```

### Public API Routes (no auth)

```
GET  /api/health                        # Health check
POST /api/stripe/webhook                # Stripe webhooks
POST /api/webhooks/github/{projectId}   # Inbound GitHub push
GET  /api/billing/plans                 # Plan listing
```

## API Endpoints

All consumed by the React SPA. Protected by `auth:web` middleware (session-based).

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

# Repositories
GET            /api/projects/{id}/repositories
POST           /api/projects/{id}/repositories
PUT|DELETE     /api/projects/{id}/repositories/{provider}
GET            /api/projects/{id}/repositories/{provider}/status
GET            /api/projects/{id}/repositories/{provider}/branches
GET            /api/projects/{id}/repositories/{provider}/latest-commit
GET            /api/projects/{id}/repositories/{provider}/files
POST           /api/projects/{id}/repositories/{provider}/pull
POST           /api/projects/{id}/repositories/{provider}/push
GET            /api/repositories/allowed-paths

# OpenClaw Config
GET|PUT        /api/projects/{id}/openclaw

# MCP Servers
GET|POST       /api/projects/{id}/mcp-servers
PUT|DELETE     /api/mcp-servers/{id}

# A2A Agents
GET|POST       /api/projects/{id}/a2a-agents
PUT|DELETE     /api/a2a-agents/{id}

# Visualization
GET            /api/projects/{id}/graph

# Import (Reverse-Sync)
POST           /api/import/detect
POST           /api/projects/{id}/import

# Models & Settings
GET            /api/models
GET|PUT        /api/settings

# Billing & Subscriptions
GET            /api/billing/status
POST           /api/billing/subscribe
POST           /api/billing/change-plan
POST           /api/billing/cancel
POST           /api/billing/resume
POST           /api/billing/setup-intent
PUT            /api/billing/payment-method
GET            /api/billing/invoices
GET            /api/billing/usage

# Stripe Connect (Marketplace Sellers)
POST           /api/billing/connect
GET            /api/billing/connect/status
GET            /api/billing/earnings

# Custom Endpoints (E.4)
GET|POST       /api/custom-endpoints
GET|PUT|DELETE /api/custom-endpoints/{id}
POST           /api/custom-endpoints/{id}/health
POST           /api/custom-endpoints/{id}/discover

# Model Health & Benchmarking (E.4)
GET            /api/model-health
GET            /api/model-health/{provider}
POST           /api/model-health/benchmark
POST           /api/model-health/compare

# Local Models (E.4)
GET            /api/local-models
GET            /api/local-models/ollama/{model}

# Air-Gap Mode (E.4)
GET|POST       /api/air-gap

# API Tokens (E.5)
GET|POST       /api/api-tokens
DELETE         /api/api-tokens/{id}

# SDK Downloads (E.5)
GET            /api/sdk/typescript
GET            /api/sdk/php
GET            /api/sdk/python

# OpenAPI Spec (E.5)
GET            /api/openapi.json            # Public
GET            /api/docs                     # Swagger UI, public

# Guardrail Policies (E.3)
GET|POST       /api/organizations/{org}/guardrails
PUT|DELETE     /api/guardrails/{id}
GET            /api/organizations/{org}/guardrails/resolve

# Guardrail Profiles (E.3)
GET|POST       /api/guardrail-profiles
GET|DELETE     /api/guardrail-profiles/{id}

# Guardrail Reports (E.3)
GET            /api/organizations/{org}/guardrail-reports
GET            /api/organizations/{org}/guardrail-reports/trends
GET            /api/organizations/{org}/guardrail-reports/export
POST           /api/guardrail-violations/{id}/dismiss

# Security Scanner (E.3)
POST           /api/skills/{id}/security-scan
POST           /api/security-scan

# Content Review (E.3)
POST           /api/skills/{id}/review
POST           /api/agents/{id}/review

# Endpoint Approvals (E.3)
GET            /api/projects/{id}/endpoint-approvals
POST           /api/endpoint-approvals/{type}/{id}/approve
POST           /api/endpoint-approvals/{type}/{id}/reject

# Content Policies (E.1)
GET|POST       /api/organizations/{org}/content-policies
GET|PUT|DELETE /api/content-policies/{id}

# SSO Providers (E.1)
GET|POST       /api/organizations/{org}/sso-providers
GET|PUT|DELETE /api/sso-providers/{id}
POST           /api/sso-providers/{id}/test

# Skill Reviews (E.6)
GET|POST       /api/skills/{id}/reviews
POST           /api/skill-reviews/{id}/approve
POST           /api/skill-reviews/{id}/reject

# Skill Ownership (E.6)
GET|PUT        /api/skills/{id}/ownership

# Notifications (E.6)
GET            /api/notifications
POST           /api/notifications/read-all
POST           /api/notifications/{id}/read

# Skill Analytics (E.6)
GET            /api/skills/{id}/analytics
GET            /api/analytics/top-skills
GET            /api/analytics/trends

# Regression Tests (E.6)
GET|POST       /api/skills/{id}/test-cases
PUT|DELETE     /api/skill-test-cases/{id}
POST           /api/skills/{id}/test-cases/run-all

# Cross-Model Benchmark (E.6)
POST           /api/skills/{id}/benchmark

# Skill Inheritance (E.6)
GET            /api/skills/{id}/resolve
GET            /api/skills/{id}/children
PUT            /api/skills/{id}/inheritance

# Reports (E.6)
GET            /api/reports/skills
GET            /api/reports/usage
GET            /api/reports/audit

# GitHub Org Import (E.6)
POST           /api/import/github/discover
POST           /api/import/github/import

# License (E.2)
GET            /api/license/status
POST           /api/license/activate

# Setup Wizard (E.2)
GET            /api/setup/status
POST           /api/setup/api-keys
POST           /api/setup/default-model
POST           /api/setup/quick-start
POST           /api/setup/complete

# Backups (E.2)
GET|POST       /api/backups
POST           /api/backups/restore
GET            /api/backups/{filename}/download

# Health Diagnostics (E.2)
GET            /api/diagnostics
GET            /api/diagnostics/{check}
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

- **Session auth** — `auth:web` guard on all API routes, session cookies shared with React SPA via CORS
- **Pest PHP** for testing
- **YAML parsing** uses `symfony/yaml`
- **Streaming** uses SSE (Server-Sent Events) for test runner and playground
- **File I/O** uses Laravel's local filesystem disk named "projects"
- **Slugs** are auto-generated from skill names, unique within a project
- **Version snapshots** are created on every skill save
- **Provider sync** is triggered explicitly (not automatic on save)
- **Multi-model** — LLMProviderFactory routes by model prefix: `claude-` → Anthropic, `gpt-`/`o3` → OpenAI, `gemini-` → Gemini, `grok-` → Grok (xAI), `custom:` → OpenAI-compatible endpoint, default → Ollama
- **API token auth** — Bearer token via `AuthenticateApiToken` middleware (fallback when no session cookie)
- **Guardrails** — Org → Project → Agent cascading policies, profiles (strict/moderate/permissive), violation tracking
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
