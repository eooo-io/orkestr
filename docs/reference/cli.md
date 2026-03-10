# CLI and Makefile

Agentis Studio provides Makefile targets for Docker operations and Composer scripts for local development.

## Makefile Targets (Docker)

All targets operate on the Docker Compose stack defined in `docker-compose.yml`.

### Service Management

```bash
make up          # Start all containers in detached mode
make down        # Stop and remove all containers
make build       # Rebuild all images from scratch (--no-cache)
make logs        # Tail logs from all containers
```

### Database

```bash
make migrate     # Run migrations and seeders
make fresh       # Drop all tables, re-run migrations and seeders
```

::: warning
`make fresh` destroys all data. Use it only during development to reset to a clean state.
:::

### Testing

```bash
make test        # Run the Pest PHP test suite inside the container
```

### Shell Access

```bash
make shell-php   # Open a bash shell in the PHP container
make shell-ui    # Open a sh shell in the UI (Node) container
```

### Other

```bash
make tinker      # Open Laravel Tinker (interactive REPL) in the PHP container
make npm-install # Run npm install in the UI container
```

## Composer Scripts (Local Development)

These commands run directly on your machine without Docker.

### Start Development

```bash
composer dev
```

Runs four processes concurrently:
- Laravel development server (`php artisan serve`)
- Queue worker (`php artisan queue:work`)
- Log watcher (`php artisan pail`)
- Vite dev server (for the React SPA)

### Run Tests

```bash
composer test
```

Clears the config cache and runs the Pest PHP test suite.

## TypeScript Type Checking

The React SPA has its own type checking:

```bash
cd ui
npx tsc --noEmit
```

This checks all TypeScript files for type errors without producing output files.

## Docker Compose Services

The `docker-compose.yml` defines these services:

| Service | Port | Description |
|---|---|---|
| `php` | -- | PHP-FPM 8.4 (no direct port, accessed via nginx) |
| `nginx` | 8000 | Web server proxying to PHP-FPM |
| `ui` | 5173 | Vite dev server for the React SPA |
| `mariadb` | 3306 | MariaDB 11 database |
| `adminer` | 8080 | Database browser UI |

## Environment Variables

Key variables in `.env`:

| Variable | Description |
|---|---|
| `PROJECTS_HOST_PATH` | Host path mounted into the PHP container for project file access |
| `DB_HOST` | `127.0.0.1` for local, `mariadb` for Docker |
| `DB_DATABASE` | Database name (default: `agentis_studio`) |
| `ANTHROPIC_API_KEY` | API key for Claude models |
| `OPENAI_API_KEY` | API key for OpenAI models |
| `GEMINI_API_KEY` | API key for Gemini models |
| `OLLAMA_URL` | URL for local Ollama instance |
