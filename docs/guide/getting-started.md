# Getting Started

Agentis Studio runs as a local development tool. You can set it up with Docker (recommended) or run the services directly on your machine.

## Prerequisites

- **Docker & Docker Compose** (Docker method)
- **PHP 8.4**, **Composer**, **Node.js 20+**, **MariaDB 11+** (local method)

## Installation with Docker

```bash
git clone https://github.com/eooo-io/agentis-studio.git
cd agentis-studio
cp .env.example .env
```

Edit `.env` and set `PROJECTS_HOST_PATH` to the directory on your machine that contains the projects you want to manage. This path gets mounted into the PHP container so Agentis Studio can read and write `.agentis/` directories.

```bash
make build
make up
make migrate
```

Then start the React SPA locally (runs outside Docker for faster HMR):

```bash
cd ui && npm install && npm run dev
```

That's it. Docker runs PHP and MariaDB, Vite runs locally.

## Installation without Docker

```bash
git clone https://github.com/eooo-io/agentis-studio.git
cd agentis-studio
composer install
cp .env.example .env
php artisan key:generate
```

Configure your database connection in `.env`:

```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=agentis_studio
DB_USERNAME=root
DB_PASSWORD=secret
```

Run migrations and seed the database:

```bash
php artisan migrate --seed
```

Install and start the React SPA:

```bash
cd ui && npm install && cd ..
composer dev
```

`composer dev` starts the Laravel server, queue worker, log watcher, and Vite dev server concurrently.

## Access Points

| Interface | URL | Purpose |
|---|---|---|
| React SPA | http://localhost:5173 | Skill editing, testing, search (local Vite) |
| Filament Admin | http://localhost:8000/admin | Project registry, provider config, settings |
| Laravel API | http://localhost:8000/api | REST API consumed by the SPA |

## Your First Project

### 1. Register a project

Open the Filament Admin at http://localhost:8000/admin and create a new project. Give it a name and set the **path** to the root directory of an existing codebase on your machine (e.g., `/home/you/code/my-app`).

::: tip
The path must be accessible from within the PHP container. When using Docker, it is relative to the `PROJECTS_HOST_PATH` mount defined in `.env`.
:::

### 2. Scaffold the `.agentis/` directory

If your project does not already have an `.agentis/` directory, Agentis Studio creates one the first time you add a skill. The directory structure looks like:

```
my-app/
  .agentis/
    skills/
      my-first-skill.md
      another-skill.md
```

### 3. Scan existing skills

If you already have `.agentis/skills/*.md` files (maybe from a teammate or a bundle import), click **Scan** on the project card. This runs a `ProjectScanJob` that:

- Reads every `.md` file in `.agentis/skills/`
- Parses YAML frontmatter and Markdown body
- Upserts skills into the database (matched by slug)
- Creates version 1 snapshots for new skills
- Syncs tags

### 4. Enable providers

Back in the Filament Admin, edit your project and check the providers you want to sync to (e.g., Claude, Cursor). Each provider writes to a specific output path in your project directory -- see [Provider Sync](./provider-sync) for the full list.

### 5. Create a skill and sync

Open the React SPA at http://localhost:5173, navigate to your project, and click **Add Skill**. Write your prompt in the Monaco editor, fill in the frontmatter fields, and save with `Ctrl+S`.

When you are ready to push your skills to provider config files, click **Sync** on the project detail page. You can also [preview the diff](./diff-preview) first.

## Next Steps

- Read [Core Concepts](./core-concepts) to understand the data model
- Learn the [Skill File Format](/reference/skill-format) in detail
- Set up [Multi-Model Testing](./multi-model) to test skills against different LLMs
