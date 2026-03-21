# Troubleshooting

Common issues and how to resolve them.

## Database Connection Failures

**Symptom:** `SQLSTATE[HY000] [2002] Connection refused` or `Access denied`.

**Fix:**

1. Verify `.env` database settings match your setup:
   - Docker: `DB_HOST=mariadb`
   - Local: `DB_HOST=127.0.0.1`
2. Check that MariaDB is running: `docker compose ps` or `systemctl status mariadb`
3. Verify the database exists: `mysql -u root -p -e "SHOW DATABASES;"`
4. Reset the database if corrupted: `make fresh` (drops and recreates all tables with seed data)

## Model Not Responding

**Symptom:** Test or Playground requests hang or return errors.

**Fix:**

1. Check API key configuration at **Settings > General** or in `.env`
2. Verify the provider is reachable: **Settings > Model Health** runs a connectivity check
3. For Ollama, confirm the server is running and accessible:
   ```bash
   curl http://localhost:11434/api/tags
   ```
4. For Docker, Ollama must be accessed via `http://host.docker.internal:11434`

::: tip
Use the Model Health dashboard to quickly identify which providers are online and responding.
:::

## Provider Sync Failures

**Symptom:** Sync completes but files are not written, or sync returns an error.

**Fix:**

1. Verify the project path exists and is writable by the PHP process
2. Check that providers are enabled for the project in the Filament Admin
3. For Docker, ensure the project path is inside the `PROJECTS_HOST_PATH` mount
4. Check the Laravel log for detailed errors: `make logs` or `tail -f storage/logs/laravel.log`

## Permission Errors

**Symptom:** `Permission denied` when writing skill files or syncing.

**Fix:**

1. Docker: the PHP container runs as `www-data`. Ensure the projects mount is writable:
   ```bash
   chmod -R 775 /path/to/your/projects
   ```
2. Local: ensure the PHP process user has write access to the project directories
3. Check storage permissions: `chmod -R 775 storage bootstrap/cache`

## Environment Variable Issues

**Symptom:** Features not working despite correct configuration in the UI.

**Fix:**

1. Clear the config cache after editing `.env`:
   ```bash
   php artisan config:clear
   ```
2. For Docker, restart the container after `.env` changes: `make down && make up`
3. Verify values with: `php artisan tinker` then `config('services.anthropic.key')`

## API Key Validation

Each provider validates keys differently:

| Provider | Validation |
|---|---|
| Anthropic | Must start with `sk-ant-` |
| OpenAI | Must start with `sk-` |
| Gemini | Must start with `AIza` |
| Grok | Must start with `xai-` |
| Ollama | No key required -- just needs a valid URL |

Set keys in **Settings > General** or in the `.env` file. The Settings page shows a green checkmark for each configured provider.

## Ollama Connectivity

**Symptom:** Local models section shows no models or returns connection errors.

**Fix:**

1. Install Ollama: `curl -fsSL https://ollama.com/install.sh | sh`
2. Start the server: `ollama serve`
3. Pull a model: `ollama pull llama3.2`
4. Set the URL in `.env`: `OLLAMA_URL=http://localhost:11434` (local) or `OLLAMA_URL=http://host.docker.internal:11434` (Docker)
5. Verify: `curl http://localhost:11434/api/tags` should return your models

## Using the Diagnostics Tab

Navigate to **Settings > Diagnostics** or hit the API:

```
GET /api/diagnostics
```

The diagnostics page runs checks on:

| Check | What It Verifies |
|---|---|
| Database | Connection, migrations up to date |
| Cache | Read/write functionality |
| Queue | Worker running, jobs processing |
| Storage | Disk writable, projects path accessible |
| Providers | API key presence for each LLM provider |

Each check returns **pass**, **warning**, or **fail** with a description.

Run a specific check:

```
GET /api/diagnostics/{check}
```

## Checking Container Logs

```bash
make logs              # Stream all container logs
docker compose logs php   # PHP container only
docker compose logs mariadb  # Database only
```

For Laravel application logs:

```bash
tail -f storage/logs/laravel.log
```

## Resetting the Database

If data becomes inconsistent or you want a clean slate:

```bash
make fresh   # Drops all tables, re-runs migrations, seeds default data
```

::: danger
`make fresh` destroys all data including projects, skills, versions, and settings. Use this only in development or after backing up via **Settings > Backups**.
:::

## FAQ

**Q: Can I run Orkestr without Docker?**
Yes. Install PHP 8.4, Composer, Node.js 20+, and MariaDB 11+ locally, then run `composer dev`. See [Getting Started](./getting-started).

**Q: How do I update Orkestr?**
Pull the latest code (`git pull`), rebuild containers (`make build`), and run migrations (`make migrate`).

**Q: Where are skill files stored on disk?**
In `{project_path}/.orkestr/skills/{slug}.md`. The project path is configured per project.

**Q: Can I edit provider config files directly?**
You can, but they will be overwritten on the next sync. Always edit in `.orkestr/` and sync outward.

**Q: How do I enable air-gap mode?**
Go to **Settings > General** and toggle **Air-Gap Mode**, or set `AIR_GAP_MODE=true` in `.env`. This blocks all external API calls -- only local models (Ollama, custom endpoints) will work.

**Q: The Vite dev server is not connecting to the API.**
Check CORS settings in `.env` (`SANCTUM_STATEFUL_DOMAINS=localhost:5173`) and ensure the API server is running on port 8000.
