# Production Deployment

**Goal:** Deploy Orkestr for production use with a reverse proxy, SSL, backups, and monitoring.

**Time:** 45 minutes

## Ingredients

- A server (bare metal or VM) with at least 4 CPU cores and 8GB RAM
- Docker and Docker Compose installed
- A domain name pointed at your server
- (Optional) Ollama for local models

## Steps

### 1. Clone and Configure

```bash
git clone https://github.com/eooo-io/agentis-studio.git
cd agentis-studio
cp .env.example .env
```

Edit `.env` for production:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://orkestr.yourcompany.com

# Strong database password
DB_HOST=mariadb
DB_DATABASE=orkestr
DB_USERNAME=orkestr
DB_PASSWORD=generate-a-strong-password-here

# LLM Providers (add what you use)
ANTHROPIC_API_KEY=sk-ant-...
OPENAI_API_KEY=sk-...
OLLAMA_URL=http://host.docker.internal:11434

# Project files location
PROJECTS_HOST_PATH=/data/orkestr/projects

# Session and security
SESSION_DRIVER=database
SESSION_LIFETIME=120
```

### 2. Build and Start

```bash
make build
make up
make migrate
```

### 3. Set Up Reverse Proxy (Caddy)

Caddy is the simplest option for automatic HTTPS:

```bash
# Caddyfile
cat > /etc/caddy/Caddyfile << 'EOF'
orkestr.yourcompany.com {
    reverse_proxy localhost:8000
    encode gzip
    header {
        X-Frame-Options DENY
        X-Content-Type-Options nosniff
        Strict-Transport-Security "max-age=63072000; includeSubDomains"
    }
}
EOF

sudo systemctl restart caddy
```

Caddy automatically obtains and renews Let's Encrypt certificates.

### 4. Change Default Credentials

Log in immediately and change the default password:

1. Go to `https://orkestr.yourcompany.com`
2. Log in with `admin@admin.com` / `password`
3. Change the password immediately in Settings

### 5. Configure Backups

Set up automated backups:

```bash
# Cron job for daily backups at 3am
(crontab -l 2>/dev/null; echo "0 3 * * * cd /path/to/agentis-studio && make backup") | crontab -
```

Or via the Orkestr API:

```bash
# Create a backup
curl -X POST https://orkestr.yourcompany.com/api/backups \
  -H "Authorization: Bearer your-api-token"
```

Backups include:
- Database dump (all tables)
- `.agentis/` skill files
- Configuration files

### 6. Set Up Monitoring

Use the built-in diagnostics endpoint:

```bash
# Health check (for load balancers and monitoring)
curl https://orkestr.yourcompany.com/api/health

# Detailed diagnostics (requires auth)
curl https://orkestr.yourcompany.com/api/diagnostics \
  -H "Authorization: Bearer your-api-token"
```

The diagnostics endpoint checks:
- Database connectivity
- Model provider reachability
- MCP server health
- Queue worker status
- Disk space

### 7. Configure Organization and SSO

For team deployments:

1. Go to **Settings → Organizations** and create your org
2. Go to **Settings → SSO** to configure SAML or OIDC:
   - **Provider name:** "Company SSO"
   - **Driver:** OIDC
   - **Config:** Client ID, Secret, Discovery URL from your IdP
3. Invite team members via **Settings → Users**

### 8. Set Guardrail Policies

Before opening to users, configure organization-level guardrails:

1. Create budget policies (daily limits per org, per project)
2. Set tool restrictions (block dangerous operations)
3. Enable output scanning (PII and secret redaction)
4. Choose default guardrail profile (Moderate recommended for most teams)

See [Enterprise Guardrails](./enterprise-guardrails) for detailed configuration.

## Result

You have a production Orkestr instance with:
- HTTPS with automatic certificate renewal
- Strong database credentials
- Automated daily backups
- Health monitoring
- SSO for team authentication
- Guardrail policies for safety
- A clean URL at your company domain

## Security Checklist

- [ ] Default password changed
- [ ] HTTPS configured
- [ ] Database password is strong and unique
- [ ] API keys stored in `.env` (not committed to git)
- [ ] Backups configured and tested
- [ ] Guardrail policies in place
- [ ] SSO configured (if multi-user)
- [ ] Firewall rules restrict access to necessary ports only
- [ ] Regular updates scheduled
