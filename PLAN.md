# Agentis Studio — Implementation Plan

> This file tracks implementation progress across sessions.
> Refer to `CLAUDE.md` for architecture details and `AGENTIS_STUDIO_PROJECT_PLAN.md` for the full spec.

---

## Current Status

**Phase 1 is COMPLETE.** All issues #1–#8 can be closed.
Starting from Phase 2.

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

## Phase 2: Database Migrations & Models — TODO

> All backend. No frontend dependencies.

### Order of operations:
1. **#9** — `create_projects_table` + `create_project_providers_table` migrations
2. **#10** — `create_skills_table` (with FULLTEXT index) + `create_skill_versions_table` migrations
3. **#11** — `create_tags_table` + `create_skill_tag_table` (pivot) + `create_library_skills_table` + `create_app_settings_table` migrations
4. **#12** — All Eloquent models: Project, ProjectProvider, Skill, SkillVersion, Tag, LibrarySkill, AppSetting

### Key details:
- `skills.tools` → JSON column, cast to array
- `skill_versions.frontmatter` → JSON column, cast to array
- `library_skills.tags` + `library_skills.frontmatter` → JSON columns
- `app_settings.key` → unique, static helpers `AppSetting::get()` / `AppSetting::set()`
- UUID auto-generated on Project, Skill, LibrarySkill
- FULLTEXT index on `skills(name, description, body)` for MariaDB
- All FKs cascade on delete
- Unique constraint on `[project_id, slug]` in skills
- Unique constraint on `[project_id, provider_slug]` in project_providers

### Verify:
- `php artisan migrate` runs clean
- All relationships work in Tinker
- AppSetting static helpers work

---

## Phase 3: File I/O & Manifest Engine — TODO

> Depends on: Phase 2 models

### Order of operations:
1. **#13** — `SkillFileParser` service (parseFile, renderFile, validateFrontmatter)
   - Uses `symfony/yaml` for YAML between `---` delimiters
   - Required frontmatter: `id`, `name`
2. **#14** — `AgentisManifestService` (scanProject, writeManifest, scaffoldProject, writeSkillFile, deleteSkillFile, skillExists)
   - Uses Laravel filesystem, reads/writes `.agentis/` directory
3. **#15** — `ProjectScanJob` (upserts skills by slug+project_id, creates v1 snapshots)
4. **#16** — Pest tests for all above (use temp directories)

### Verify:
- Round-trip: render → parse → identical output
- Scan temp dir with 3 skills → all in DB with v1 snapshots

---

## Phase 4: Provider Sync Engine — TODO

> Depends on: Phase 2 models, Phase 3 file I/O

### Order of operations:
1. **#17** — `ProviderDriverInterface` + `ProviderSyncService`
2. **#27** — ClaudeDriver (single .claude/CLAUDE.md) + CursorDriver (per-skill .mdc files)
3. **#28** — CopilotDriver (.github/copilot-instructions.md) + WindsurfDriver (per-skill .md files)
4. **#29** — ClineDriver (single .clinerules) + OpenAIDriver (.openai/instructions.md)
5. **#30** — `POST /api/projects/{id}/sync` endpoint + unit tests per driver

### Output format reference:
| Provider | Path | Style |
|---|---|---|
| Claude | `.claude/CLAUDE.md` | Concatenated H2 |
| Cursor | `.cursor/rules/{slug}.mdc` | Per-file, YAML frontmatter |
| Copilot | `.github/copilot-instructions.md` | Concatenated H2 |
| Windsurf | `.windsurf/rules/{slug}.md` | Per-file |
| Cline | `.clinerules` | Concatenated flat |
| OpenAI | `.openai/instructions.md` | Concatenated H2 |

---

## Phase 5: Filament Admin Panel — TODO

> Depends on: Phase 2 models, Phase 3 (for scan action), Phase 4 (for sync action)

### Order of operations:
1. **#31** — ProjectResource (CRUD + provider checkboxes + Scan/Sync/Open actions)
2. **#32** — LibrarySkillResource (CRUD + category filter)
3. **#33** — TagResource (CRUD + ColorPicker)
4. **#34** — Settings page (API key, default model, rescan all)
5. **#35** — Dashboard (stats widget + SPA link)

---

## Phase 6: Skills CRUD API — TODO

> Depends on: Phase 2 models, Phase 3 file I/O

### Order of operations:
1. **#19** — API Resources (ProjectResource, SkillResource, VersionResource)
2. **#36** — SkillController (index, store, show, update, destroy, duplicate)
3. **#37** — VersionController (index, show, restore)
4. **#18** — TagController, SearchController, LibraryController, SettingsController
5. **#20** — Register all routes in routes/api.php

### Key behaviors:
- Every skill save → new SkillVersion snapshot
- Slug auto-generated, unique within project
- Duplicate: "-copy" suffix, optional target_project_id
- Restore: loads past version, updates skill + file, creates new snapshot
- Search: FULLTEXT on MariaDB, fallback to LIKE, results grouped by project
- Library import: unique slug (append -1/-2), write file, create v1

---

## Phase 7: React SPA Core UI — TODO

> Depends on: Phase 6 API

### Order of operations:
1. **#25** — Zustand store + Axios client (typed methods, error toast, beforeunload guard)
2. **#21** — Layout shell (sidebar with project list, search, nav, admin link)
3. **#22** — Projects page (card grid, sync button)
4. **#23** — ProjectDetail page (skill grid, add/sync actions, grid/list toggle)
5. **#24** — SkillEditor page (FrontmatterForm + Monaco + RawToggle + ActionBar + tab stubs)

### Component hierarchy:
```
App
├── Layout (sidebar + main)
│   ├── Projects (card grid)
│   ├── ProjectDetail (skill grid)
│   ├── SkillEditor
│   │   ├── FrontmatterForm
│   │   ├── MonacoEditor
│   │   ├── ActionBar
│   │   └── Tabs: Test | Versions (Phase 8 & 9)
│   ├── Library (Phase 10)
│   └── Search (Phase 10)
```

---

## Phase 8: Live Test Runner — TODO

> Depends on: Phase 6 API, Phase 7 SkillEditor

1. **#26** — SkillTestController (SSE streaming via Anthropic SDK)
   - `POST /api/skills/{id}/test` → text/event-stream
   - Reads API key from AppSetting or env
2. **#38** — LiveTestPanel React component
   - fetch + ReadableStream (not EventSource)
   - Token counter, elapsed timer, copy button, stop button

---

## Phase 9: Version History & Diff Viewer — TODO

> Depends on: Phase 6 VersionController, Phase 7 SkillEditor

1. **#39** — VersionHistoryPanel (version list, checkboxes for diff, timeline UI)
2. **#40** — Monaco Diff Editor (side-by-side, readOnly)
3. **#41** — Restore flow (AlertDialog → POST restore → reload)

---

## Phase 10: Global Library & Search — TODO

> Depends on: Phase 6 API, Phase 7 Layout

1. **#42** — LibrarySkillSeeder (25 skills, 6 categories, min 100 words each)
2. **#45** — Library import endpoint (slug collision handling)
3. **#43** — Library page (category sidebar, search, import modal)
4. **#44** — Search page (FULLTEXT, filters, grouped results, highlighting)

---

## Phase 11: Settings, Polish & QA — TODO

> Depends on: All previous phases

1. **#46** — Settings page in SPA (API key status, provider reference)
2. **#47** — Global toast notifications (Axios interceptor)
3. **#48** — Keyboard shortcuts (Ctrl+S, Ctrl+K, Escape)
4. **#49** — Empty states + loading skeletons
5. **#50** — Unsaved changes navigation guard
6. **#51** — End-to-end QA checklist

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
