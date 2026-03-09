# Agentis Studio — Project Plan & Implementation Phases
> A universal AI skill/agent configuration manager for multi-provider development workflows.

---

## 1. Project Overview

### Purpose
Agentis Studio is a web-based management interface that allows a developer or small team to:
- Define, edit, and organize reusable AI skills (prompts + config) in a **provider-agnostic format**
- **Sync those skills outward** to the native config format of any supported AI provider (Claude, Cursor, Copilot, Windsurf, Cline, OpenAI)
- **Test skills live** against the Claude API with streaming output
- **Browse a global library** of importable, reusable skills
- **Track version history** of every skill with diff comparison
- **Search and tag** skills across all registered projects

### Core Philosophy
> `.agentis/` is the single source of truth. All provider-specific files are derived outputs — never edited directly.

### Intended User
Head of Software Development / Systems Architect — single-user, running locally on a mounted filesystem, with skills shared across multiple Laravel/TALL stack projects.

---

## 2. System Architecture

### Directory Convention (per project)
```
/your-project/
├── .agentis/
│   ├── manifest.json          # Project metadata + registered providers
│   └── skills/
│       ├── summarize-doc.md
│       ├── extract-entities.md
│       └── code-review.md
├── .claude/                   # ← synced output (Anthropic)
│   └── CLAUDE.md
├── .cursor/                   # ← synced output
│   └── rules/
│       ├── summarize-doc.mdc
│       └── extract-entities.mdc
├── .github/                   # ← synced output (Copilot)
│   └── copilot-instructions.md
├── .windsurf/                 # ← synced output
│   └── rules/
│       ├── summarize-doc.md
│       └── extract-entities.md
├── .clinerules                # ← synced output (single file)
└── .openai/                   # ← synced output
    └── instructions.md
```

### Skill File Format (canonical — YAML frontmatter + Markdown body)
```
---
id: summarize-doc
name: Summarize Document
description: Summarizes any document to key bullet points
tags: [summarization, documents, productivity]
model: claude-sonnet-4-20250514
max_tokens: 1000
tools: []
created_at: 2026-01-15T09:00:00Z
updated_at: 2026-03-09T14:22:00Z
---

You are a precise document summarizer working within a FinTech context.

Given the following document, extract:
- The core purpose
- Key decisions or findings
- Any action items

Respond in structured Markdown.
```

### Project Manifest Format
```json
{
  "id": "uuid-v4",
  "name": "Immotege Core API",
  "path": "/var/www/immotege-api",
  "description": "Main Laravel API backend",
  "providers": ["claude", "cursor", "copilot"],
  "skills": ["summarize-doc", "extract-entities", "code-review"],
  "created_at": "2026-01-15T09:00:00Z",
  "synced_at": "2026-03-09T14:00:00Z"
}
```

---

## 3. Tech Stack

| Layer | Technology | Version | Rationale |
|---|---|---|---|
| **Runtime** | PHP | 8.4.x | Latest stable, readonly properties, improved performance |
| **Framework** | Laravel | 12.x | Latest, improved bootstrapping, typed config |
| **Admin UI** | Filament | 3.x (latest stable) | Project registry, library, settings, provider config |
| **Reactive Components** | Livewire | 4.x | Powers Filament + custom interactive Blade components |
| **Frontend Editor SPA** | React + Vite + TypeScript | Latest | Monaco editor, live tester, diff viewer |
| **Styling** | Tailwind CSS v4 | Latest | Shared across Filament customization and React SPA |
| **Component Library** | shadcn/ui | Latest | React SPA UI primitives |
| **Code Editor** | Monaco Editor | Latest | VS Code engine for skill editing and diff view |
| **Database** | MariaDB | 11.x | Projects, tags, versions, library, app settings |
| **File I/O** | Laravel Filesystem (local) | — | Read/write `.agentis/` on mounted paths |
| **LLM Runtime** | Anthropic PHP SDK | Latest | Streaming skill test runner |
| **State Management** | Zustand | Latest | React SPA global state |
| **HTTP Client** | Axios | Latest | React → Laravel API calls |
| **Containerization** | Docker + Docker Compose | Latest | Full local dev + production parity |

### Architectural Split: Filament vs React SPA

| Concern | Handled By | Reason |
|---|---|---|
| Project registry (register/unregister paths) | **Filament** | CRUD admin table, no custom UI needed |
| Provider config per project | **Filament** | Checkbox toggles, simple form |
| Global library management | **Filament** | Rich table with filters, bulk actions |
| App settings (API keys, defaults) | **Filament** | Settings plugin page |
| Skill CRUD + Monaco editor | **React SPA** | Monaco requires full DOM control |
| Live test runner (SSE streaming) | **React SPA** | Real-time streaming UI |
| Version history + diff viewer | **React SPA** | Monaco Diff Editor integration |
| Cross-project search | **React SPA** | Instant search UX |
| Tag management | **Filament** | Simple admin table |

---

## 4. Provider Sync Engine

| Provider | Output Target | Output Format | Notes |
|---|---|---|---|
| **Anthropic / Claude** | `.claude/CLAUDE.md` | Markdown | All skills, H2 heading per skill |
| **Cursor** | `.cursor/rules/{slug}.mdc` | MDC (YAML frontmatter + MD) | One file per skill |
| **GitHub Copilot** | `.github/copilot-instructions.md` | Markdown | All skills concatenated |
| **Windsurf** | `.windsurf/rules/{slug}.md` | Markdown | One file per skill |
| **Cline** | `.clinerules` | Markdown | Single flat file, all skills |
| **OpenAI** | `.openai/instructions.md` | Markdown | All skills concatenated |

---

## 5. Database Schema

```sql
-- Registered local projects
projects
  id, uuid, name, description, path, synced_at, created_at, updated_at

-- Provider targets per project (claude|cursor|copilot|windsurf|cline|openai)
project_providers
  id, project_id FK, provider_slug

-- Skills (mirrors filesystem, DB is the working copy)
skills
  id, uuid, project_id FK, slug, name, description,
  model, max_tokens, tools (JSON), body (LONGTEXT),
  created_at, updated_at

-- Snapshot on every save
skill_versions
  id, skill_id FK, version_number, frontmatter (JSON),
  body (LONGTEXT), note (nullable), saved_at

-- Tags
tags
  id, name, color

skill_tag (pivot)
  skill_id, tag_id

-- Global importable library
library_skills
  id, uuid, name, slug, description, category,
  tags (JSON), frontmatter (JSON), body (LONGTEXT),
  source, created_at

-- Key/value app config (API keys, defaults)
app_settings
  id, key (unique), value (TEXT), created_at, updated_at
```

---

## 6. API Endpoints (consumed by React SPA)

```
# Projects
GET    /api/projects
POST   /api/projects
GET    /api/projects/{id}
PUT    /api/projects/{id}
DELETE /api/projects/{id}
POST   /api/projects/{id}/scan        Re-scan .agentis/ and sync DB
POST   /api/projects/{id}/sync        Write all provider output files

# Skills
GET    /api/projects/{id}/skills
POST   /api/projects/{id}/skills
GET    /api/skills/{id}
PUT    /api/skills/{id}
DELETE /api/skills/{id}
POST   /api/skills/{id}/duplicate

# Versions
GET    /api/skills/{id}/versions
GET    /api/skills/{id}/versions/{v}
POST   /api/skills/{id}/versions/{v}/restore

# Live Test Runner (SSE)
POST   /api/skills/{id}/test

# Tags
GET    /api/tags
POST   /api/tags
DELETE /api/tags/{id}

# Search
GET    /api/search?q=&tags=&project_id=&model=

# Library
GET    /api/library?category=&tags=&q=
POST   /api/library/{id}/import

# Settings (read-only from SPA)
GET    /api/settings
```

---

## 7. Filament Admin Panels

```
Filament Resources
├── ProjectResource
│   ├── Table: name, path, providers (badges), skill_count, synced_at, actions
│   ├── Form: name, description, path input, provider CheckboxList (all 6 options)
│   └── Actions: Scan Now, Sync Now, Open in Editor (→ React SPA)
│
├── LibrarySkillResource
│   ├── Table: name, category (badge), tags, created_at
│   ├── Form: name, slug (auto), description, category (select),
│             tags, model (select), max_tokens, body (Textarea tall)
│   └── Filters: category, tags
│
├── TagResource
│   ├── Table: name, color swatch, skill_count
│   └── Form: name (text), color (ColorPicker)
│
└── Filament Pages
    ├── SettingsPage
    │   ├── ANTHROPIC_API_KEY (PasswordInput → app_settings)
    │   ├── DEFAULT_MODEL (Select)
    │   ├── Provider conventions reference (static ViewField)
    │   └── Rescan All Projects (dispatches ProjectScanJob for all)
    └── DashboardPage
        ├── StatsOverview: total projects, total skills, last synced
        └── Quick link → React SPA Editor
```

---

## 8. React SPA Pages & Components

```
Layout
├── Sidebar (fixed 240px)
│   ├── ProjectList (from API)
│   ├── GlobalSearch input (Ctrl+K)
│   └── Nav: Library, Search, "Admin Panel" (→ /admin)
│
├── /projects              ProjectCards grid
├── /projects/:id          Skill grid + project header
├── /skills/new            SkillEditor (create mode, ?project_id=)
├── /skills/:id            SkillEditor (edit mode)
│   ├── Left (60%): FrontmatterForm + MonacoEditor + RawToggle + ActionBar
│   └── Right (40%, tabbed):
│       ├── Tab "Test"     → LiveTestPanel
│       └── Tab "Versions" → VersionHistoryPanel
│           ├── Version list (checkbox select for diff)
│           ├── Monaco Diff Editor (2 versions selected)
│           └── Restore button with confirmation
├── /library               Category sidebar + skill grid + import modal
└── /search                Filter bar + cross-project results grouped by project
```

---

## 9. Docker Compose Architecture

```yaml
# docker-compose.yml
services:

  php:
    build:
      context: ./docker/php
      dockerfile: Dockerfile        # php:8.4-fpm, composer, pdo_mysql, mbstring
    volumes:
      - ./api:/var/www/api
      - projects:/projects          # Shared mount for local project directories
    depends_on:
      - mariadb
    networks:
      - agentis

  nginx:
    image: nginx:alpine
    ports:
      - "8000:80"                   # Laravel API + Filament admin
    volumes:
      - ./api:/var/www/api
      - ./docker/nginx/api.conf:/etc/nginx/conf.d/default.conf
    depends_on:
      - php
    networks:
      - agentis

  ui:
    build:
      context: ./docker/node
      dockerfile: Dockerfile        # node:22-alpine, Vite dev server
    ports:
      - "5173:5173"                 # React SPA
    volumes:
      - ./ui:/app
      - /app/node_modules
    command: npm run dev -- --host
    networks:
      - agentis

  mariadb:
    image: mariadb:11
    environment:
      MARIADB_ROOT_PASSWORD: root
      MARIADB_DATABASE: agentis
      MARIADB_USER: agentis
      MARIADB_PASSWORD: secret
    volumes:
      - mariadb_data:/var/lib/mysql
    ports:
      - "3306:3306"
    networks:
      - agentis

  adminer:
    image: adminer
    ports:
      - "8080:8080"                 # DB admin UI (dev only)
    networks:
      - agentis

volumes:
  mariadb_data:
  projects:
    driver: local
    driver_opts:
      type: bind
      o: bind
      device: ${PROJECTS_HOST_PATH} # Defined in root .env → your local dev directory

networks:
  agentis:
    driver: bridge
```

### Dockerfile — PHP 8.4
```dockerfile
FROM php:8.4-fpm

RUN apt-get update && apt-get install -y \
    git curl zip unzip libpng-dev libonig-dev libxml2-dev \
    && docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd opcache

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/api
RUN chown -R www-data:www-data /var/www
```

---

## 10. Full Repository Structure

```
agentis-studio/
├── docker-compose.yml
├── docker-compose.prod.yml
├── .env.example                         # PROJECTS_HOST_PATH=
├── Makefile
├── README.md
│
├── docker/
│   ├── php/
│   │   ├── Dockerfile                   # php:8.4-fpm + extensions
│   │   └── php.ini
│   ├── nginx/
│   │   └── api.conf
│   └── node/
│       └── Dockerfile                   # node:22-alpine
│
├── api/                                 # Laravel 12
│   ├── app/
│   │   ├── Filament/
│   │   │   ├── Resources/
│   │   │   │   ├── ProjectResource.php
│   │   │   │   ├── LibrarySkillResource.php
│   │   │   │   └── TagResource.php
│   │   │   └── Pages/
│   │   │       ├── Dashboard.php
│   │   │       └── Settings.php
│   │   ├── Http/Controllers/
│   │   │   ├── ProjectController.php
│   │   │   ├── SkillController.php
│   │   │   ├── VersionController.php
│   │   │   ├── TagController.php
│   │   │   ├── SkillTestController.php
│   │   │   ├── LibraryController.php
│   │   │   ├── SearchController.php
│   │   │   └── SettingsController.php
│   │   ├── Http/Resources/
│   │   │   ├── ProjectResource.php
│   │   │   ├── SkillResource.php
│   │   │   └── VersionResource.php
│   │   ├── Services/
│   │   │   ├── AgentisManifestService.php
│   │   │   ├── SkillFileParser.php
│   │   │   ├── ProviderSyncService.php
│   │   │   └── Providers/
│   │   │       ├── ProviderDriverInterface.php
│   │   │       ├── ClaudeDriver.php
│   │   │       ├── CursorDriver.php
│   │   │       ├── CopilotDriver.php
│   │   │       ├── WindsurfDriver.php
│   │   │       ├── ClineDriver.php
│   │   │       └── OpenAIDriver.php
│   │   ├── Models/
│   │   │   ├── Project.php
│   │   │   ├── Skill.php
│   │   │   ├── SkillVersion.php
│   │   │   ├── Tag.php
│   │   │   ├── LibrarySkill.php
│   │   │   └── AppSetting.php
│   │   └── Jobs/
│   │       └── ProjectScanJob.php
│   ├── database/
│   │   ├── migrations/
│   │   └── seeders/
│   │       └── LibrarySkillSeeder.php   # 25 pre-built skills
│   └── routes/
│       ├── api.php
│       └── web.php                      # Filament auto-registers here
│
└── ui/                                  # React + Vite + TypeScript
    ├── src/
    │   ├── pages/
    │   │   ├── Projects.tsx
    │   │   ├── ProjectDetail.tsx
    │   │   ├── SkillEditor.tsx
    │   │   ├── Library.tsx
    │   │   └── Search.tsx
    │   ├── components/
    │   │   ├── layout/
    │   │   │   ├── Sidebar.tsx
    │   │   │   └── TopBar.tsx
    │   │   ├── skills/
    │   │   │   ├── SkillCard.tsx
    │   │   │   ├── FrontmatterForm.tsx
    │   │   │   ├── MonacoSkillEditor.tsx
    │   │   │   ├── LiveTestPanel.tsx
    │   │   │   └── VersionHistoryPanel.tsx
    │   │   └── library/
    │   │       ├── LibrarySkillCard.tsx
    │   │       └── LibraryImportModal.tsx
    │   ├── store/
    │   │   └── useAppStore.ts
    │   ├── api/
    │   │   └── client.ts
    │   └── types/
    │       └── index.ts
    └── vite.config.ts
```

---

## 11. Environment Variables

```bash
# .env (root — read by Docker Compose)
PROJECTS_HOST_PATH=/Users/you/dev      # Host path bind-mounted as /projects in containers

# api/.env
APP_NAME="Agentis Studio"
APP_ENV=local
APP_URL=http://localhost:8000
APP_KEY=

DB_CONNECTION=mariadb
DB_HOST=mariadb
DB_PORT=3306
DB_DATABASE=agentis
DB_USERNAME=agentis
DB_PASSWORD=secret

ANTHROPIC_API_KEY=sk-ant-...
PROJECTS_BASE_PATH=/projects           # Docker internal path
FILESYSTEM_DISK=local
FILAMENT_ADMIN_PATH=admin

# ui/.env
VITE_API_URL=http://localhost:8000/api
VITE_ADMIN_URL=http://localhost:8000/admin
```

---

## 12. Makefile

```makefile
up:
	docker compose up -d

down:
	docker compose down

build:
	docker compose build --no-cache

migrate:
	docker compose exec php php artisan migrate --seed

fresh:
	docker compose exec php php artisan migrate:fresh --seed

tinker:
	docker compose exec php php artisan tinker

npm-install:
	docker compose exec ui npm install

logs:
	docker compose logs -f

shell-php:
	docker compose exec php bash

shell-ui:
	docker compose exec ui sh

test:
	docker compose exec php php artisan test
```

---

## 13. Implementation Phases

---

### Phase 1 — Docker Environment & Project Scaffold
**Goal:** All containers running, apps accessible, DB connected, Filament installed.

**Claude CLI instructions:**
```
Create the agentis-studio monorepo with:
  - docker-compose.yml (php, nginx, ui, mariadb, adminer services as specified)
  - docker/php/Dockerfile (php:8.4-fpm + pdo_mysql + mbstring + bcmath + opcache + composer)
  - docker/nginx/api.conf (proxy to php-fpm, root /var/www/api/public)
  - docker/node/Dockerfile (node:22-alpine)
  - .env.example and Makefile

Create Laravel 12 project in api/:
  - composer require filament/filament:"^3.0" -W
  - composer require livewire/livewire:"^4.0"
  - php artisan filament:install --panels
  - php artisan make:filament-user (seeder-based, no interactive prompt)
  - Configure MariaDB connection in config/database.php
  - Add CORS allow list for http://localhost:5173 in config/cors.php
  - Remove Sanctum (single-user, no auth on API)
  - Register a local filesystem disk named "projects" in config/filesystems.php
    pointing to env('PROJECTS_BASE_PATH', '/projects')

Create React + Vite + TypeScript project in ui/:
  - npm create vite@latest . -- --template react-ts
  - Install: tailwindcss @tailwindcss/vite
  - Install: shadcn/ui (init with default style, zinc base color)
  - Install: @monaco-editor/react axios zustand react-router-dom lucide-react
  - Configure vite.config.ts with proxy: /api → http://nginx:80

Verify:
  docker compose up -d
  http://localhost:8000       → Laravel welcome page
  http://localhost:8000/admin → Filament login panel
  http://localhost:5173       → Vite React app
  http://localhost:8080       → Adminer
```

---

### Phase 2 — Database Migrations & Models
**Goal:** Full schema migrated, all Eloquent models with relationships and casts.

**Claude CLI instructions:**
```
Create migrations for all tables (run in this order):
  1. create_projects_table
  2. create_project_providers_table (FK: project_id)
  3. create_skills_table (FK: project_id)
  4. create_skill_versions_table (FK: skill_id)
  5. create_tags_table
  6. create_skill_tag_table (pivot, FKs: skill_id, tag_id)
  7. create_library_skills_table
  8. create_app_settings_table (key unique)

Create Eloquent models with full relationships:
  Project:
    - hasMany(Skill::class)
    - hasMany(ProjectProvider::class)
    - casts: providers as array via accessor (joins project_providers)
  
  Skill:
    - belongsTo(Project::class)
    - hasMany(SkillVersion::class)
    - belongsToMany(Tag::class)
    - casts: tools → array
  
  SkillVersion:
    - belongsTo(Skill::class)
    - casts: frontmatter → array, saved_at → datetime
  
  Tag:
    - belongsToMany(Skill::class)
  
  LibrarySkill:
    - casts: tags → array, frontmatter → array
  
  AppSetting:
    - Static helper: AppSetting::get(string $key, mixed $default = null)
    - Static helper: AppSetting::set(string $key, mixed $value): void

Run: php artisan migrate
```

---

### Phase 3 — File I/O & Manifest Engine
**Goal:** Laravel reads and writes `.agentis/` skill files reliably.

**Claude CLI instructions:**
```
Create app/Services/SkillFileParser.php:
  - parseFile(string $absolutePath): array
    Returns: ['frontmatter' => [...], 'body' => '...']
    Uses symfony/yaml to parse the YAML block between --- delimiters
  - renderFile(array $frontmatter, string $body): string
    Writes canonical YAML frontmatter + Markdown body with --- delimiters
  - validateFrontmatter(array $data): array
    Returns array of validation errors (empty = valid)
    Required fields: id, name

Create app/Services/AgentisManifestService.php:
  - scanProject(string $absolutePath): array
    Reads .agentis/manifest.json and all .agentis/skills/*.md files
    Returns: ['manifest' => [...], 'skills' => [...parsed skill arrays...]]
  - writeManifest(Project $project): void
    Writes .agentis/manifest.json from current DB state
  - scaffoldProject(string $absolutePath, string $name): void
    Creates .agentis/manifest.json and .agentis/skills/ directory
  - writeSkillFile(string $projectPath, array $frontmatter, string $body): void
  - deleteSkillFile(string $projectPath, string $slug): void
  - skillExists(string $projectPath, string $slug): bool

Create app/Jobs/ProjectScanJob.php:
  - Accepts Project model
  - Calls AgentisManifestService::scanProject()
  - Upserts skills (match on slug + project_id)
  - Creates SkillVersion (v1) for newly discovered skills
  - Updates project.synced_at = now()

Write PHPUnit feature tests:
  - Scan a temp directory with 3 valid skill files
  - Parse skill with all optional fields present
  - Parse skill with only required fields
  - Round-trip test: render then re-parse, assert identical
  - Scaffold new .agentis/ directory then verify structure

composer require symfony/yaml
```

---

### Phase 4 — Provider Sync Engine
**Goal:** All 6 provider drivers write correct output files from `.agentis/` skills.

**Claude CLI instructions:**
```
Create app/Services/Providers/ProviderDriverInterface.php:
  interface ProviderDriverInterface {
    public function sync(Project $project, Collection $skills): void;
    public function getOutputPaths(Project $project): array;
    public function clean(Project $project): void;
  }

Implement all 6 drivers in app/Services/Providers/:

ClaudeDriver:
  Output: {project.path}/.claude/CLAUDE.md
  Format:
    # CLAUDE.md\n\n
    ## {skill.name}\n\n{skill.body}\n\n---\n\n  (repeat per skill)

CursorDriver:
  Output: {project.path}/.cursor/rules/{skill.slug}.mdc (one file per skill)
  Format: YAML frontmatter block:
    ---
    description: {skill.description}
    tags: {skill.tags as yaml list}
    alwaysApply: false
    ---
    {skill.body}

CopilotDriver:
  Output: {project.path}/.github/copilot-instructions.md
  Format:
    # GitHub Copilot Instructions\n\n
    ## {skill.name}\n\n{skill.body}\n\n---\n\n  (repeat per skill)

WindsurfDriver:
  Output: {project.path}/.windsurf/rules/{skill.slug}.md (one file per skill)
  Format:
    # {skill.name}\n\n{skill.body}

ClineDriver:
  Output: {project.path}/.clinerules
  Format: all skills concatenated:
    # {skill.name}\n\n{skill.body}\n\n---\n\n

OpenAIDriver:
  Output: {project.path}/.openai/instructions.md
  Format:
    # Instructions\n\n
    ## {skill.name}\n\n{skill.body}\n\n---\n\n  (repeat per skill)

Create app/Services/ProviderSyncService.php:
  - getDriver(string $slug): ProviderDriverInterface
  - syncProject(Project $project): void
    Loads project's active providers, runs each driver, updates synced_at

Add endpoints:
  POST /api/projects/{id}/sync → calls ProviderSyncService::syncProject()

Write unit tests per driver asserting exact output file contents and paths.
```

---

### Phase 5 — Filament Admin Panel
**Goal:** Filament resources fully operational for projects, library, tags, settings.

**Claude CLI instructions:**
```
Create Filament resources:

php artisan make:filament-resource Project --generate
Customize ProjectResource:
  - Table: TextColumn(name), TextColumn(path, limit 40), 
           BadgeColumn(providers via relationship count),
           TextColumn(skills_count computed), TextColumn(synced_at relative)
  - Form: TextInput(name required), Textarea(description),
          TextInput(path required, hint "absolute server path"),
          CheckboxList(providers, options: claude/cursor/copilot/windsurf/cline/openai)
          — saves to project_providers table via sync()
  - Header actions:
      Action("Scan Now") → dispatch(new ProjectScanJob($record)) → success notification
      Action("Sync Now") → app(ProviderSyncService::class)->syncProject($record) → notification
      Action("Open in Editor") → redirect to VITE_APP_URL/projects/{id}, new tab

php artisan make:filament-resource LibrarySkill --generate
Customize LibrarySkillResource:
  - Table: name, category (badge), created_at
  - Form: name, slug (auto from name), description, 
          Select(category, options: Laravel/PHP/TypeScript/FinTech/DevOps/Writing),
          TagsInput(tags), Select(model), NumberInput(max_tokens),
          Textarea(body, rows: 20)
  - Filters: SelectFilter(category)

php artisan make:filament-resource Tag --generate
Customize TagResource:
  - Table: name, ColorColumn(color), skills_count
  - Form: TextInput(name), ColorPicker(color)

Create app/Filament/Pages/Settings.php (extends Page):
  - PasswordInput: ANTHROPIC_API_KEY → AppSetting::set()
  - Select: DEFAULT_MODEL
  - ViewField: static HTML table of provider conventions
  - Action("Rescan All Projects"): Project::all()->each(fn($p) => dispatch(new ProjectScanJob($p)))

Customize app/Filament/Pages/Dashboard.php:
  - StatsOverviewWidget: total projects, total skills, last synced timestamp
  - Add link button to React SPA URL
```

---

### Phase 6 — Skills CRUD API
**Goal:** Full REST API for skill lifecycle consumed by React SPA.

**Claude CLI instructions:**
```
Create app/Http/Controllers/SkillController.php:
  index(Project $project):
    - Supports filters: ?tags[]=&model=
    - Returns SkillResource collection with tags eager-loaded
  
  store(Request $request, Project $project):
    - Validate: name required, model required, body required
    - Auto-generate slug from name (unique within project)
    - Write file via AgentisManifestService::writeSkillFile()
    - Create DB record
    - Create SkillVersion (version_number: 1, note: "Initial version")
    - Return SkillResource (201)
  
  show(Skill $skill):
    - Return SkillResource with tags and current_version_number
  
  update(Request $request, Skill $skill):
    - Validate fields
    - Update DB record
    - Overwrite file on disk
    - Create new SkillVersion (increment version_number, store note from ?note= param)
    - Return SkillResource (200)
  
  destroy(Skill $skill):
    - Delete file via AgentisManifestService::deleteSkillFile()
    - Delete DB record (cascade deletes versions via FK)
    - Return 204
  
  duplicate(Request $request, Skill $skill):
    - Validate: target_project_id (defaults to same project)
    - Copy skill with "-copy" suffix on slug
    - Write new file, create DB record, create v1 snapshot
    - Return new SkillResource (201)

Create app/Http/Controllers/VersionController.php:
  index(Skill $skill): return all versions (id, version_number, note, saved_at)
  show(Skill $skill, int $version): return full frontmatter + body
  restore(Skill $skill, int $version):
    - Load past version
    - Update skill record with past frontmatter + body
    - Write file to disk
    - Create new snapshot: version_number + 1, note "Restored from v{version}"
    - Return SkillResource

Create TagController: index (with skill_count), store, destroy

Create API Resources:
  SkillResource: id, uuid, project_id, slug, name, description, model,
                 max_tokens, tools, body, tags, current_version_number, timestamps
  VersionResource: id, skill_id, version_number, frontmatter, body, note, saved_at
  ProjectResource: id, uuid, name, description, path, providers, skills_count, synced_at

Register all routes in routes/api.php with Route::apiResource() where applicable.
No auth middleware — single user application.
```

---

### Phase 7 — React SPA Core UI
**Goal:** Navigable app with project list, skill grid, and working Monaco editor.

**Claude CLI instructions:**
```
Configure React Router in main.tsx with routes:
  /                   → redirect to /projects
  /projects           → <Projects />
  /projects/:id       → <ProjectDetail />
  /skills/new         → <SkillEditor /> (create mode, read ?project_id from URL)
  /skills/:id         → <SkillEditor /> (edit mode)
  /library            → <Library />
  /search             → <Search />

Build Layout component (app shell):
  - Fixed left sidebar 240px: logo, ProjectList (from GET /api/projects),
    search input (Ctrl+K focuses, navigates to /search?q=),
    nav links (Library, Search), footer link "Admin Panel" → VITE_ADMIN_URL
  - Main content area fills remaining width

Build Projects.tsx: grid of ProjectCards
  Props from API: name, path (truncated with title tooltip), provider badge chips,
  skills_count, synced_at (relative), "Open" button → /projects/:id,
  "Sync" button → POST /api/projects/:id/sync

Build ProjectDetail.tsx:
  - Header: name, path, provider badges, "Add Skill" → /skills/new?project_id=:id,
    "Sync Now" button, "Manage" link → admin/projects/:id
  - Toggle: grid / list view
  - SkillCard: name, description (2-line truncate), tags (badge chips),
    model (monospace badge), updated_at relative, "Edit" → /skills/:id

Build SkillEditor.tsx (most complex component):
  Left panel (60%):
    FrontmatterForm:
      - TextInput: name (onChange auto-derives slug preview)
      - TextInput: description
      - Select: model (claude-opus-4, claude-sonnet-4, gpt-4o, gpt-4-turbo)
      - NumberInput: max_tokens (default 1000)
      - TagsInput: type to search GET /api/tags, create on Enter if not found
    
    MonacoEditor:
      - language: "markdown", theme: "vs-dark"
      - height: calc(100vh - 280px)
      - onChange: sets isDirty in Zustand store
    
    RawToggle button:
      - Switches editor to show full YAML frontmatter + body combined
      - Parse back to structured form on toggle-off
    
    ActionBar (sticky bottom):
      - "Save" button (primary) + Ctrl+S shortcut
      - "Duplicate" button → dialog to select target project
      - "Delete" button (destructive) → AlertDialog confirmation
  
  Right panel (40%):
    shadcn Tabs: "Test" | "Versions"
    Test tab: <LiveTestPanel skillId={id} /> (Phase 8)
    Versions tab: <VersionHistoryPanel skillId={id} /> (Phase 9)
  
  Create mode vs Edit mode:
    - Create: empty form, POST on save, redirect to /skills/:newId
    - Edit: prefill from GET /api/skills/:id, PUT on save

Configure Zustand store (src/store/useAppStore.ts):
  - projects: Project[]
  - activeProjectId: string | null
  - isDirty: boolean (unsaved changes)
  - setDirty(v: boolean): void

Configure Axios client (src/api/client.ts):
  - baseURL from import.meta.env.VITE_API_URL
  - Response interceptor: show toast on error status codes
  - Export typed API methods for all endpoints

Add unsaved changes guard:
  useBeforeUnload when isDirty is true
  React Router loader/blocker when navigating away dirty
```

---

### Phase 8 — Live Test Runner
**Goal:** Stream Claude API responses in real-time within the editor Test panel.

**Claude CLI instructions:**
```
Create app/Http/Controllers/SkillTestController.php:
  POST /api/skills/{id}/test
  Request body: { user_message: string, model_override?: string, max_tokens_override?: int }
  
  - Load skill from DB
  - Read ANTHROPIC_API_KEY from AppSetting or env()
  - Return 422 if key not set with message "Anthropic API key not configured"
  - Build request to Anthropic API using anthropic/anthropic-sdk-php:
      system: skill.body
      messages: [{ role: "user", content: user_message }]
      model: model_override ?? skill.model
      max_tokens: max_tokens_override ?? skill.max_tokens
      stream: true
  - Set response headers:
      Content-Type: text/event-stream
      Cache-Control: no-cache
      X-Accel-Buffering: no
  - Stream chunks as: data: {"token": "..."}\n\n
  - On stream complete: data: {"done": true, "total_tokens": N}\n\n
  - On exception: data: {"error": "message"}\n\n

Install: composer require anthropic-php/client

Build LiveTestPanel.tsx React component:
  - Textarea: placeholder "Enter a test message to run against this skill..."
  - Select: model override (default from skill's model field)
  - "Run Test" button → disabled while streaming
  - "Stop" button (shown during stream) → aborts fetch
  
  Streaming implementation (use fetch + ReadableStream, not EventSource):
    const response = await fetch('/api/skills/:id/test', { method: 'POST', body: ... })
    const reader = response.body.getReader()
    // Read chunks, parse SSE data lines, append tokens to output
  
  Output area:
    - Monospace font, dark bg, auto-scroll to bottom
    - Tokens appended as they arrive
    - Live token counter (top right of output area)
    - Elapsed time counter (starts on submit)
    - After done: final token count + latency + "Copy" button
  
  Error display:
    - Red banner if error event received or fetch throws
    - "API key not configured" message links to Admin settings
```

---

### Phase 9 — Version History & Diff Viewer
**Goal:** Full version browsing and Monaco-powered side-by-side diff comparison.

**Claude CLI instructions:**
```
Build VersionHistoryPanel.tsx:

Layout: two columns (list left, preview/diff right)

Left column — VersionList:
  - Fetch GET /api/skills/:id/versions on tab open (lazy, with loading skeleton)
  - Each VersionItem shows:
      Version badge (e.g. "v4"), saved_at (relative), note (italic, if set)
      Checkbox for diff selection (max 2 selectable)
      "Restore" button (disabled on current version)
  - Current version: ring highlight + "Current" badge
  - Timeline connector line between items (CSS border-left)

Right column — Preview / Diff:
  - Default (0-1 checkboxes): Monaco Editor showing selected (or latest) version body
    language: markdown, theme: vs-dark, readOnly: true
  - Diff mode (exactly 2 checkboxes): MonacoDiffEditor
    original: older version body, modified: newer version body, readOnly: true
    renderSideBySide: true
  - "Exit diff" button resets checkboxes

Restore flow:
  - "Restore v{n}" → shadcn AlertDialog:
    title: "Restore version {n}?"
    description: "This will overwrite the current skill content. A new version will be saved."
    Confirm → POST /api/skills/:id/versions/:v/restore
    On success:
      - Reload skill data in parent SkillEditor (refetch GET /api/skills/:id)
      - Show success toast: "Skill restored to version {n} — saved as v{new}"
      - Reload version list
      - Reset checkboxes
```

---

### Phase 10 — Global Library & Search
**Goal:** Browse 25 pre-built skills, import to any project, cross-project search.

**Claude CLI instructions:**
```
Seed LibrarySkillSeeder with 25 complete skills across categories:
  Laravel (5): eloquent-optimization, api-resource-design, laravel-code-review,
               service-class-refactor, filament-form-builder
  PHP (3): php-security-audit, type-safety-review, error-handling-patterns
  TypeScript/React (3): react-component-review, typescript-strict-check, 
                        accessibility-audit
  FinTech (5): transaction-categorization, risk-assessment-summary, 
               compliance-language-check, kyc-document-review, 
               financial-report-summarizer
  DevOps (4): dockerfile-review, docker-compose-audit, 
              env-variable-security, ci-pipeline-review
  Writing (5): readme-generator, commit-message-writer, 
               changelog-generator, api-documentation, 
               technical-spec-writer

Each skill must have: complete YAML frontmatter + substantial system prompt body (min 100 words)

Build /library page:
  Left sidebar (200px):
    - "All" option with total count
    - Category list with count per category
    - Tag cloud (top 15 tags)
  
  Main area:
    - Search input (debounced 300ms → GET /api/library?q=)
    - Grid of LibrarySkillCards:
        name, category badge (color-coded), description (2-line truncate), tag chips
    - Empty state if no results
  
  LibrarySkillModal (shadcn Dialog):
    - All frontmatter fields displayed in a clean grid
    - Monaco Editor preview (readOnly, height 300px)
    - "Import to Project" button → ProjectSelectDialog
      (shadcn Select populated from GET /api/projects)
    - On select + confirm: POST /api/library/:id/import { project_id }
    - On success: toast + navigate to /skills/:newSkillId

Build POST /api/library/{id}/import in LibraryController:
  - Load library skill
  - Generate unique slug in target project (append -1, -2 if collision)
  - Write file to target project's .agentis/skills/
  - Create DB record + SkillVersion v1 (note: "Imported from library")
  - Return SkillResource (201)

Build /search page:
  - URL param ?q= pre-fills search input
  - FilterBar: project MultiSelect, tag chips, model Select
  - Debounced fetch → GET /api/search?q=&tags[]=&project_id=&model=
  - Results grouped by project (sticky section header with project name + path)
  - SkillResult: name (match highlighted), description excerpt (highlighted),
    tags, model badge, "Edit" link → /skills/:id
  - Loading skeleton, empty state with library CTA

Implement GET /api/search in SearchController:
  - FULLTEXT index on skills(name, description, body) — add to migration
  - Falls back to LIKE if FULLTEXT unavailable
  - Returns: { projects: [{ project: {...}, skills: [...] }] }
  - Highlights matched term by wrapping in <mark> tags in name and description
```

---

### Phase 11 — Settings, Polish & QA
**Goal:** Full settings page, error handling, empty states, shortcuts, end-to-end QA.

**Claude CLI instructions:**
```
Build /settings page in React SPA:
  - API Key Status: "Configured ✓" or "Not configured ✗" (from GET /api/settings)
  - "Manage in Admin" button → VITE_ADMIN_URL/settings
  - Default model display (from GET /api/settings)
  - Provider conventions reference table (static):
      Provider | Output Target | Format | Notes
      (all 6 rows as defined in architecture)

Global UX polish:
  - Axios error interceptor → shadcn Toast on all 4xx/5xx with message from API
  - React Router navigation blocker: prompt when isDirty is true
  - Keyboard shortcuts:
      Ctrl+S → trigger save in SkillEditor (document-level listener)
      Ctrl+K → focus global search input in Sidebar
      Escape → close any open Dialog/AlertDialog

Empty states (with icon + message + CTA button):
  /projects (no projects):
    Icon, "No projects registered yet"
    Button: "Open Admin Panel" → VITE_ADMIN_URL/projects/create

  /projects/:id (no skills):
    Icon, "This project has no skills yet"
    Buttons: "Create Skill" → /skills/new?project_id=:id
             "Import from Library" → /library

  /search (no results):
    Icon, "No skills found for '{query}'"
    Button: "Browse Library" → /library

Loading states:
  - Skeleton loaders for all list/grid views (react-loading-skeleton or CSS)
  - Monaco editor loading spinner (built-in @monaco-editor/react loader prop)
  - Streaming spinner in LiveTestPanel while awaiting first token

End-to-end QA checklist:
  [ ] docker compose up → all 5 services healthy
  [ ] Register project in Filament → Scan → skills appear in SPA
  [ ] Create skill in SPA → .agentis/skills/{slug}.md written to disk
  [ ] Edit + save skill → version increments, previous versions accessible
  [ ] Select 2 versions → Monaco diff editor renders correctly
  [ ] Restore past version → file updated on disk, new snapshot created
  [ ] Sync project with all 6 providers → verify each output file format
  [ ] Import library skill into project → appears in SPA, file on disk
  [ ] Search across 2 projects with tag filter → correct grouped results
  [ ] Run live test → tokens stream in LiveTestPanel, timer and count correct
  [ ] Filament: CRUD all resource types without errors
  [ ] No console errors in React SPA across all pages
```

---

## 14. Access Points Summary

| Interface | URL | Purpose |
|---|---|---|
| React SPA | http://localhost:5173 | Skill editing, testing, diff, search, library |
| Filament Admin | http://localhost:8000/admin | Projects, library, tags, settings |
| Laravel API | http://localhost:8000/api | JSON REST API (consumed by SPA) |
| Adminer | http://localhost:8080 | Direct DB access (dev only) |

---

## 15. Success Criteria (Definition of Done)

- [ ] `docker compose up` starts all 5 services cleanly
- [ ] `http://localhost:8000/admin` serves Filament with all 3 resources + settings page
- [ ] `http://localhost:5173` serves React SPA
- [ ] Register a project path in Filament → Scan → skills listed in SPA
- [ ] Create a skill in Monaco editor → `.agentis/skills/{slug}.md` written to disk
- [ ] Edit and save → version snapshot increments in DB
- [ ] Browse version list → select 2 → Monaco diff renders correctly
- [ ] Restore past version → file updated, new snapshot created
- [ ] Sync project → all 6 provider output files written in correct formats
- [ ] Browse library → import skill into project → skill appears, file on disk
- [ ] Full-text search across projects with tag + model filters
- [ ] Live test runner → streaming tokens appear, latency/token count shown
- [ ] All Filament resources fully operational (Project, LibrarySkill, Tag)
- [ ] API key stored via Filament settings, status visible in SPA

---

*Generated for Agentis Studio — Immotege FinTech internal tooling*
*Stack: PHP 8.4 / Laravel 12 / Filament 3 / Livewire 4 / React + Vite + TypeScript / MariaDB 11 / Docker Compose*
*LLM Runtime: Claude API (Anthropic) + 6-provider sync engine*
