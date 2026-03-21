# Self-Hosted Deployment

This guide covers deploying Orkestr in a production environment.

## Docker Compose (Production)

Create a `docker-compose.prod.yml` or use the included `docker-compose.yml` with a production `.env`:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://orkestr.yourcompany.com

DB_HOST=mariadb
DB_DATABASE=orkestr
DB_USERNAME=orkestr
DB_PASSWORD=<strong-random-password>

# Session
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true

# LLM Providers (add as needed)
ANTHROPIC_API_KEY=sk-ant-...
OPENAI_API_KEY=sk-...
GEMINI_API_KEY=AIza...
GROK_API_KEY=xai-...
OLLAMA_URL=http://host.docker.internal:11434
```

```bash
docker compose up -d
docker compose exec php php artisan migrate --force
```

## Reverse Proxy

Orkestr should sit behind a reverse proxy that handles SSL termination.

### Nginx

```nginx
server {
    listen 443 ssl http2;
    server_name orkestr.yourcompany.com;

    ssl_certificate     /etc/ssl/certs/orkestr.crt;
    ssl_certificate_key /etc/ssl/private/orkestr.key;

    location / {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    location /vite/ {
        proxy_pass http://127.0.0.1:5173;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
    }
}
```

### Caddy

```caddy
orkestr.yourcompany.com {
    reverse_proxy localhost:8000
}
```

Caddy handles SSL automatically via Let's Encrypt.

### Traefik

```yaml
# docker-compose labels
labels:
  - "traefik.enable=true"
  - "traefik.http.routers.orkestr.rule=Host(`orkestr.yourcompany.com`)"
  - "traefik.http.routers.orkestr.tls.certresolver=letsencrypt"
  - "traefik.http.services.orkestr.loadbalancer.server.port=8000"
```

## SSL/TLS

For production, always run Orkestr behind HTTPS. Options:

- **Let's Encrypt** via Caddy or Traefik (automatic)
- **Self-signed certificates** for internal/air-gapped networks
- **Corporate CA** certificates mounted into the reverse proxy

Set `SESSION_SECURE_COOKIE=true` and `APP_URL=https://...` in your `.env`.

## Database Backup

Run scheduled backups with the built-in command:

```bash
php artisan orkestr:backup
```

This exports the database and configuration to a timestamped archive. Schedule it via cron:

```cron
0 2 * * * cd /path/to/orkestr && php artisan orkestr:backup >> /var/log/orkestr-backup.log 2>&1
```

For MariaDB native backups:

```bash
docker compose exec mariadb mysqldump -u root -p orkestr > backup-$(date +%Y%m%d).sql
```

## Upgrade Procedure

To upgrade Orkestr to a new version:

```bash
# Pull latest changes
git pull origin main

# Rebuild containers
docker compose build --no-cache
docker compose up -d

# Run migrations
docker compose exec php php artisan orkestr:upgrade
```

The `orkestr:upgrade` command runs migrations, clears caches, and applies any version-specific data transformations. Always back up your database before upgrading.

## Health Checks

Orkestr exposes two health endpoints (no authentication required):

| Endpoint | Purpose |
|---|---|
| `GET /api/health` | Basic health check -- returns `{"status": "ok"}` |
| `GET /api/diagnostics` | Detailed diagnostics -- database, queue, disk, provider connectivity |

Use these for load balancer health probes and monitoring:

```bash
curl -sf https://orkestr.yourcompany.com/api/health || echo "Orkestr is down"
```

## License

Orkestr is free and open source under the MIT license. No license key or activation is required.

## Resource Requirements

| Component | Minimum | Recommended |
|---|---|---|
| CPU | 2 cores | 4+ cores |
| RAM | 2 GB | 8 GB (more if running local models) |
| Disk | 10 GB | 50 GB+ (for local model storage) |
| Database | MariaDB 11+ | MariaDB 11+ with dedicated volume |

## Environment Variables Reference

| Variable | Description | Default |
|---|---|---|
| `APP_ENV` | Environment (local, production) | `local` |
| `APP_URL` | Public URL | `http://localhost:8000` |
| `DB_HOST` | Database host | `mariadb` |
| `DB_DATABASE` | Database name | `orkestr` |
| `ANTHROPIC_API_KEY` | Anthropic API key | -- |
| `OPENAI_API_KEY` | OpenAI API key | -- |
| `GEMINI_API_KEY` | Google Gemini API key | -- |
| `GROK_API_KEY` | xAI Grok API key | -- |
| `OLLAMA_URL` | Ollama server URL | `http://localhost:11434` |
| `SESSION_SECURE_COOKIE` | Require HTTPS for cookies | `false` |
| `PROJECTS_HOST_PATH` | Host path for project mounts | -- |
