# Getting Started

Orkestr by eooo.ai is a self-hosted agent orchestration platform. You deploy it on your own infrastructure and access it through a browser. It supports Docker (recommended), a one-line installer, or manual local setup.

## Prerequisites

- **Docker & Docker Compose** (Docker method -- recommended)
- **PHP 8.4**, **Composer**, **Node.js 20+**, **MariaDB 11+** (local method)

## One-Line Install

::: warning
The installer script is not yet available. This documents the planned installation method.
:::

```bash
curl -sSL https://get.orkestr.dev | bash
```

This downloads the latest release, sets up Docker containers, runs migrations, and starts the application. After installation completes, open `http://localhost:8000` to access the setup wizard.

## Installation with Docker (Recommended)

```bash
git clone https://github.com/eooo-io/orkestr.git
cd orkestr
cp .env.example .env
```

Edit `.env` and configure the following:

```env
# Database
DB_HOST=mariadb
DB_PORT=3306
DB_DATABASE=orkestr
DB_USERNAME=root
DB_PASSWORD=secret

# LLM Provider API Keys (add the ones you use)
ANTHROPIC_API_KEY=sk-ant-...
OPENAI_API_KEY=sk-...
GEMINI_API_KEY=AIza...
GROK_API_KEY=xai-...

# Local Models (Ollama)
OLLAMA_URL=http://host.docker.internal:11434

# Project path mount
PROJECTS_HOST_PATH=/path/to/your/projects
```

Build and start the containers:

```bash
make build
make up
make migrate
```

Then start the React SPA locally (runs outside Docker for faster HMR):

```bash
cd ui && npm install && npm run dev
```

## Installation without Docker

```bash
git clone https://github.com/eooo-io/orkestr.git
cd orkestr
composer install
cp .env.example .env
php artisan key:generate
```

Configure your `.env` with database credentials and API keys as shown above (use `DB_HOST=127.0.0.1` for local installs).

Run migrations and seed the database:

```bash
php artisan migrate --seed
```

Install and start all services:

```bash
cd ui && npm install && cd ..
composer dev
```

`composer dev` starts the Laravel server, queue worker, log watcher, and Vite dev server concurrently.

## Setup Wizard

On first launch, Orkestr runs a setup wizard that walks you through:

1. Creating your organization
2. Configuring LLM provider API keys
3. Setting up your first project
4. Choosing default guardrail policies

## Authentication

Orkestr uses **session-based authentication**. All API routes are protected by the `auth:web` guard -- cookies are shared between the Laravel backend and the React SPA via CORS.

**Default login credentials:**

| Field | Value |
|---|---|
| Email | `admin@admin.com` |
| Password | `password` |

::: danger
Change the default password immediately after first login in a production deployment.
:::

Orkestr also supports GitHub OAuth and Apple Sign In. See [Self-Hosted Deployment](./self-hosted-deployment) for production auth configuration.

## Access Points

| Interface | URL | Purpose |
|---|---|---|
| React SPA | http://localhost:5173 | Skill editing, agent design, workflows, testing |
| Filament Admin | http://localhost:8000/admin | Project registry, provider config, organization settings |
| API | http://localhost:8000/api | REST API consumed by the SPA |

## Your First Project

### 1. Log in and create a project

Open the Filament Admin at http://localhost:8000/admin, log in with the default credentials, and create a new project. Give it a name and set the **path** to the root directory of an existing codebase.

::: tip
When using Docker, the path is relative to the `PROJECTS_HOST_PATH` mount defined in `.env`.
:::

### 2. Scaffold the `.orkestr/` directory

If your project does not already have an `.orkestr/` directory, Orkestr creates one the first time you add a skill:

```
my-app/
  .orkestr/
    skills/
      my-first-skill.md
      another-skill.md
```

### 3. Scan existing skills

If you already have `.orkestr/skills/*.md` files, click **Scan** on the project card. This reads every skill file, parses YAML frontmatter, upserts skills into the database, and creates version snapshots.

### 4. Enable providers and sync

Edit your project in the Filament Admin and check the providers you want to sync to (e.g., Claude, Cursor). Then open the React SPA, navigate to your project, and click **Sync**. You can [preview the diff](./diff-preview) before writing anything to disk.

## Next Steps

- [Core Concepts](./core-concepts) -- understand the data model
- [Architecture](./architecture) -- three-layer architecture overview
- [Local Models](./local-models) -- set up Ollama and custom endpoints
- [Guardrails](./guardrails) -- configure organization-level policies
- [Self-Hosted Deployment](./self-hosted-deployment) -- production deployment guide
- [Skill File Format](/reference/skill-format) -- YAML frontmatter reference
