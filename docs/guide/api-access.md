# API Access

Orkestr exposes a full REST API and provides auto-generated SDKs so you can integrate skill management, agent orchestration, and provider sync into your own tooling.

## API Token Authentication

Session-based auth works for the React SPA, but for programmatic access you need an API token.

### Creating a Token

Navigate to **Settings > API Tokens** and click **Create Token**. Configure:

| Field | Description |
|---|---|
| **Name** | A label for the token (e.g., "CI Pipeline", "VS Code Extension") |
| **Abilities** | Scoped permissions -- choose from `skills:read`, `skills:write`, `projects:read`, `projects:write`, `sync`, `agents:read`, `agents:write`, or `*` for full access |
| **Expires at** | Optional expiration date. Tokens without an expiration remain valid until revoked. |

::: warning
The full token value is shown only once at creation time. Copy it immediately -- it cannot be retrieved later.
:::

### Using the Token

Include the token as a Bearer token in the `Authorization` header:

```bash
curl -H "Authorization: Bearer ork_token_abc123..." \
     http://localhost:8000/api/projects
```

If both a session cookie and a Bearer token are present, the session takes precedence. The `AuthenticateApiToken` middleware activates only when no session cookie is found.

### Revoking Tokens

Delete a token from Settings or via the API:

```
DELETE /api/api-tokens/{id}
```

## OpenAPI Specification

Orkestr auto-generates an OpenAPI 3.1 specification that documents every endpoint, request body, and response schema.

| URL | Description |
|---|---|
| `/api/openapi.json` | Machine-readable OpenAPI 3.1 spec (public, no auth) |
| `/api/docs` | Swagger UI -- interactive API explorer (public, no auth) |

The Swagger UI lets you authenticate with your API token and execute requests directly from the browser.

## Auto-Generated SDKs

Orkestr generates three SDK clients from the OpenAPI spec. Each follows its language's standard conventions and uses SOLID architecture (one service class per resource domain, dependency injection, interface-based contracts).

### TypeScript SDK

Follows the Google TypeScript style guide. Download from **Settings > SDK Downloads** or:

```
GET /api/sdk/typescript
```

```typescript
import { OrkestrClient } from './orkestr-sdk';

const client = new OrkestrClient({
  baseUrl: 'http://localhost:8000',
  token: 'ork_token_abc123...',
});

const projects = await client.projects.list();
const skill = await client.skills.get('skill-uuid');
```

### PHP SDK

PSR-12 compliant. Requires PHP 8.1+ and Guzzle.

```
GET /api/sdk/php
```

```php
use Orkestr\Client;

$client = new Client(
    baseUrl: 'http://localhost:8000',
    token: 'ork_token_abc123...',
);

$projects = $client->projects()->list();
$skill = $client->skills()->get('skill-uuid');
```

### Python SDK

PEP 8 compliant. Uses `httpx` for HTTP and Pydantic for models.

```
GET /api/sdk/python
```

```python
from orkestr import OrkestrClient

client = OrkestrClient(
    base_url="http://localhost:8000",
    token="ork_token_abc123...",
)

projects = client.projects.list()
skill = client.skills.get("skill-uuid")
```

## CLI Tools

Orkestr ships two Artisan commands for server-side management and deployment.

### orkestr:deploy

Run pre-deployment checks and deploy configuration:

```bash
php artisan orkestr:deploy --check    # Dry-run: validate env, DB, providers
php artisan orkestr:deploy            # Full deploy: migrate, cache, sync
```

### orkestr:manage

Inspect and manage the running instance:

```bash
php artisan orkestr:manage status     # Instance health summary
php artisan orkestr:manage projects   # List all projects
php artisan orkestr:manage agents     # List all agents with status
php artisan orkestr:manage skills     # List skills across projects
```

::: tip
The CLI tools are useful in CI/CD pipelines and cron jobs. Combine `orkestr:deploy --check` with your deployment pipeline to gate releases on configuration validity.
:::
