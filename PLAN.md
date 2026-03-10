# Agentis Studio — Implementation Plan

> This file tracks implementation progress across sessions.
> Refer to `CLAUDE.md` for architecture details and `AGENTIS_STUDIO_PROJECT_PLAN.md` for the full spec.

---

## Current Status

**Phase 16 is COMPLETE.** All phases 1–16 done.

---

## Phase 1: Docker Environment & Project Scaffold — DONE

All items complete:
- [x] #1 docker-compose.yml (php, nginx, ui, mariadb, adminer)
- [x] #2 Dockerfiles (docker/php, docker/nginx, docker/node)
- [x] #3 .env.example + Makefile
- [x] #4 Filament 3.x installed, AdminPanelProvider at /admin
- [x] #5 symfony/yaml + mozex/anthropic-laravel installed
- [x] #6 `projects` filesystem disk, CORS config, api routes registered
- [x] #7 React + Vite + TS SPA in ui/ (Tailwind v4, shadcn/ui, Monaco, Zustand, Router, Lucide)
- [x] #8 SPA base structure (types, api/client.ts, store, routing stubs)

**Initial commit pushed to GitHub.**

---

## Phase 2: Database Migrations & Models — DONE

All items complete:
- [x] #9 — `create_projects_table` + `create_project_providers_table` migrations
- [x] #10 — `create_skills_table` (with FULLTEXT index) + `create_skill_versions_table` migrations
- [x] #11 — `create_tags_table` + `create_skill_tag_table` (pivot) + `create_library_skills_table` + `create_app_settings_table` migrations
- [x] #12 — All Eloquent models: Project, ProjectProvider, Skill, SkillVersion, Tag, LibrarySkill, AppSetting

All verified: migrations run clean, relationships work, AppSetting helpers work, JSON casts work, UUIDs auto-generate, slugs auto-generate.

Also fixed: docker-compose.yml now sets `DB_HOST=mariadb` via environment override so `.env` can keep `127.0.0.1` for local dev.

---

## Phase 3: File I/O & Manifest Engine — DONE

All items complete:
- [x] #13 — `SkillFileParser` (parseFile, parseContent, renderFile, validateFrontmatter)
- [x] #14 — `AgentisManifestService` (scanProject, writeManifest, scaffoldProject, writeSkillFile, deleteSkillFile, skillExists)
- [x] #15 — `ProjectScanJob` (upserts by slug+project_id, creates v1 snapshots, syncs tags)
- [x] #16 — 19 Pest tests (7 unit + 12 feature), all passing

Also fixed: FULLTEXT index migration now conditionally skips on SQLite for test compatibility.

---

## Phase 4: Provider Sync Engine — DONE

All items complete:
- [x] #17 — `ProviderDriverInterface` + `ProviderSyncService`
- [x] #27 — ClaudeDriver + CursorDriver
- [x] #28 — CopilotDriver + WindsurfDriver
- [x] #29 — ClineDriver + OpenAIDriver
- [x] #30 — Provider sync tests (10 tests, all passing)

28 total tests, 116 assertions, all green.

---

## Phase 5: Filament Admin Panel — DONE

All items complete:
- [x] #31 — ProjectResource (CRUD + provider checkboxes + Scan/Sync/Open Editor actions)
- [x] #32 — LibrarySkillResource (CRUD + category filter + tags)
- [x] #33 — TagResource (CRUD + ColorPicker + skill count)
- [x] #34 — Settings page (API key, default model, provider reference, rescan all)
- [x] #35 — Dashboard (StatsOverview widget + SPA link)

---

## Phase 6: Skills CRUD API — DONE

All items complete:
- [x] #19 — API Resources (ProjectResource, SkillResource, VersionResource)
- [x] #36 — SkillController (index, store, show, update, destroy, duplicate)
- [x] #37 — VersionController (index, show, restore)
- [x] #18 — TagController, SearchController, LibraryController, SettingsController
- [x] #20 — 24 API routes registered in routes/api.php

All endpoints verified via smoke test. FULLTEXT search with LIKE fallback for SQLite.

---

## Phase 7: React SPA Core UI — DONE

All items complete:
- [x] #25 — Zustand store (projects, toast, dirty state) + typed Axios client (all endpoints)
- [x] #21 — Layout shell (Sidebar with project list, nav, admin link) + Toast
- [x] #22 — Projects page (card grid, sync button, empty state)
- [x] #23 — ProjectDetail page (skill grid, scan/sync/add, grid/list toggle, loading skeleton)
- [x] #24 — SkillEditor page (FrontmatterForm + Monaco + ActionBar + Ctrl+S + beforeunload guard + tab stubs)

Also built: Search page with debounced FULLTEXT search and grouped results. Library page stub.
TypeScript clean (`tsc --noEmit`), Vite production build passes.

---

## Phase 8: Live Test Runner — DONE

All items complete:
- [x] #26 — SkillTestController (SSE streaming via Anthropic PHP SDK, reads API key from AppSetting/env)
- [x] #38 — LiveTestPanel (ReadableStream, token counter, elapsed timer, copy, stop, Ctrl+Enter)

---

## Phase 9: Version History & Diff Viewer — DONE

All items complete:
- [x] #39 — VersionHistoryPanel (version list, checkboxes, timestamps, notes)
- [x] #40 — Monaco Diff Editor (side-by-side, readOnly, compare any 2 versions)
- [x] #41 — Restore flow (confirm dialog, POST restore, reload skill + versions)

---

## Phase 10: Global Library & Search — DONE

All items complete:
- [x] #42 — LibrarySkillSeeder (25 skills, 6 categories: Laravel, PHP, TypeScript, FinTech, DevOps, Writing)
- [x] #45 — Library import endpoint (slug collision handling, tag sync, v1 version, file write)
- [x] #43 — Library page (category sidebar, search, import modal with project picker)
- [x] #44 — Search page (FULLTEXT, tag/project/model filters, grouped results)

---

## Phase 11: Settings, Polish & QA — DONE

All items complete:
- [x] #46 — Settings page in SPA (API key status, default model, provider sync reference table)
- [x] #47 — Global toast notifications (Axios 500 error interceptor with lazy store import)
- [x] #48 — Keyboard shortcuts (Ctrl+S save, Ctrl+K search, Escape blur/back)
- [x] #49 — Empty states + loading skeletons (Projects, ProjectDetail, Library, Search, SkillEditor)
- [x] #50 — Unsaved changes navigation guard (beforeunload + unstable_usePrompt)
- [x] #51 — End-to-end QA (28 tests pass, tsc clean, Vite build passes, all API endpoints verified)

---

## Phase 12: Agent Compose & Export — DONE

Agents feature was largely pre-built (models, migrations, seeder, controller, UI). Remaining work completed:
- [x] #52 — Agents + project_agent + agent_skill migrations & models
- [x] #53 — Seed 9 default agents (Orchestrator, PM, Architect, QA, Design, Code Review, Infrastructure, CI/CD, Security)
- [x] #54 — Agent API endpoints (index, projectAgents, toggle, updateInstructions, assignSkills, compose, composeAll)
- [x] #55 — Agent configuration UI (AgentsTab, AgentConfigModal, tab integration in ProjectDetail)
- [x] #56 — AgentComposeService (merge base + custom + skills, token estimation, markdown rendering)
- [x] #58 — Compose preview UI (AgentComposePreview modal, token budget progress bar, copy-to-clipboard)
- [x] #57 — Provider sync integration — all 6 drivers handle composedAgents, ProviderSyncService calls composeAll()

---

## Phase 13: Token Estimation & Budget Warnings — DONE

- [x] #62 — Token counting and budget warnings for skills and agents
  - `token_estimate` added to SkillResource API response (via AgentComposeService::estimateTokens)
  - Per-skill token count displayed in SkillCard (with Coins icon, formatted as 1.2k)
  - Live token counter in FrontmatterForm (client-side estimateTokens)
  - Model-specific context limits (200k for all Claude models)
  - Color-coded warnings: green (normal), yellow (>75%), red (>90%)
  - Token budget already present in AgentComposePreview (from Phase 12)

---

## Phase 14: AI-Assisted Skill Generation — DONE

- [x] #70 — Natural language skill generation via Anthropic API
  - `POST /api/skills/generate` endpoint (SkillGenerateController)
  - Calls Claude Sonnet 4 with expert prompt engineer system prompt
  - Returns structured JSON: name, description, model, max_tokens, tags, body
  - GenerateSkillModal component (description textarea + constraints + loading state)
  - Integrated in SkillEditor ActionBar ("Generate" button) — fills editor with result for review
  - Integrated in ProjectDetail ("Generate" button) — creates skill directly and navigates to editor
  - Fixed 2 pre-existing test failures (ProviderSyncService constructor dependency)

---

## Phase 15: Skill Playground with Streaming — DONE

- [x] #59 — Streaming test runner UI with agent system prompt support
  - New `POST /api/playground` endpoint — supports custom system prompt, multi-turn messages, model override
  - Refactored `SkillTestController` — extracted shared `stream()` method, added `playground()` action
  - Full `Playground` page with split-pane layout (config sidebar + chat area)
  - Project picker → loads skills and enabled agents as system prompt sources
  - Agent compose integration — fetches composed output via `AgentComposeService`
  - System prompt preview with token estimate
  - Model and max_tokens configuration
  - Multi-turn conversation (maintained in component state)
  - Real-time SSE streaming with cursor animation
  - Per-turn stats: elapsed time, input/output token counts
  - Copy per-message, clear conversation
  - Ctrl+Enter to send, auto-scroll, abort support
  - Added to sidebar nav and router (`/playground`)

---

## Phase 16: Skill Dependencies & Composition — DONE

- [x] #60 — Skill include/extend system for composable prompts
  - Migration: `includes` JSON column added to `skills` table
  - Model: `includes` added to fillable + array cast
  - `SkillCompositionService` — recursive include resolution with circular dependency detection (max depth 5)
  - `SkillResource` — exposes `includes`, `resolved_body`, token estimate uses resolved body
  - `SkillController` — accepts `includes` on store/update/duplicate, writes to .agentis files
  - `ProviderSyncService` — pre-resolves includes, passes resolved bodies to all 6 drivers
  - `AgentComposeService` — uses resolved bodies when composing agents
  - `SkillTestController` — tests skills with resolved body (includes prepended)
  - `ProjectScanJob` — parses `includes` from frontmatter on scan
  - `ProviderDriverInterface` + all 6 drivers — updated to accept `$resolvedBodies` parameter
  - UI: Includes picker in FrontmatterForm (toggle buttons for sibling skills)
  - UI: Token estimate shows "(resolved)" when includes are active
  - SkillEditor loads project skills for includes picker

---

## Future Phases (Backlog)

| Phase | Feature | Issues |
|-------|---------|--------|
| 17 | Git-Backed Skill Versioning | #61 |
| 18 | Prompt Linting | #63 |
| 19 | Team/Workspace Sharing | #64 |
| 20 | Provider Diff Preview | #65 |
| 21 | Skill Templates | #66 |
| 22 | Bulk Operations | #67 |
| 23 | Command Palette | #68 |
| 24 | Skill Marketplace | #69 |
| 25 | Webhook/Event System | #71 |
| 26 | Multi-Model Test Runner | #72 |

---

## Implementation Notes

### Parallelizable work:
- Phase 2 + nothing (foundation, must go first)
- Phase 3 + Phase 6 API Resources (#19) can start together
- Phase 4 + Phase 6 controllers can be partially parallel
- Phase 5 Filament can run alongside Phase 6/7
- Phase 7 frontend depends on Phase 6 API being done
- Phase 8, 9, 10 frontend work can run after Phase 7

### Tech decisions made:
- Anthropic SDK: `mozex/anthropic-laravel` (not `anthropic-php/client` — doesn't exist on Packagist)
- Laravel app at repo root (not in `api/` subdirectory as original plan suggested)
- React SPA in `ui/` (not a git submodule — nested .git was removed)
- shadcn/ui v4 initialized with Geist font
- DB_HOST defaults to `127.0.0.1` in .env for local dev, `mariadb` in .env.example for Docker
- Remote uses HTTPS (SSH key not configured for this machine)

### Commands:
```bash
# Local dev
composer dev                    # server + queue + pail + vite

# Docker
make up && make migrate         # start + seed

# Tests
composer test                   # or: make test (Docker)
cd ui && npx tsc --noEmit      # type-check SPA
```
