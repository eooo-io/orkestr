# Settings

The Settings hub is a unified admin interface with 12 tabs organized into 4 sections. Access it from the **Settings** link in the sidebar.

## Layout

The left side shows a vertical tab navigation grouped into sections:

| Section | Tabs |
|---|---|
| **Settings** | General, License |
| **Administration** | Agents, Library, Tags |
| **Access** | Users, Organizations, SSO, Content Policies |
| **System** | Infrastructure, Backups, Diagnostics |

---

## Settings Section

### General

The main configuration tab for API keys, default model, and core settings.

**API Keys** -- enter keys for each LLM provider. Keys are stored encrypted in the `app_settings` table. A green badge indicates a key is configured; a gray badge means it is not set.

| Provider | Key Format |
|---|---|
| Anthropic | `sk-ant-...` |
| OpenAI | `sk-...` |
| Google Gemini | `AIza...` |
| Grok (xAI) | `xai-...` |
| OpenRouter | `sk-or-...` |

**Default Model** -- the model used when a skill or agent does not specify one. Defaults to `claude-sonnet-4-6`. The dropdown lists all models from configured providers.

**Ollama URL** -- the URL for your local Ollama instance (e.g., `http://localhost:11434`).

**Air-Gap Mode** -- toggle to block all outbound network calls. When enabled, only local models (Ollama, custom endpoints) are available. See [Models](./models#air-gap-mode) for details.

**SDK Downloads** -- download auto-generated client SDKs for TypeScript, PHP, and Python. Each SDK is a self-contained package generated from the OpenAPI spec.

```
GET /api/sdk/typescript
GET /api/sdk/php
```

::: tip
After changing API keys, the model selector across the app updates automatically on the next page load. No restart is required.
:::

### License

Manage your Orkestr license for self-hosted deployments.

- **Status** -- shows current license tier (free, pro, teams), expiration date, and activation status
- **Activation** -- enter a license key to activate or upgrade
- **Usage** -- current month's token usage against plan limits

```
GET  /api/license/status
POST /api/license/activate
```

::: warning
Without an active license, Orkestr runs in free-tier mode with limited projects, skills, and execution history retention.
:::

---

## Administration Section

### Agents

Default agent definitions that are available across all projects. This tab replaces the former Filament-based agent management.

- **List** -- view all global agents with name, role, model, and sort order
- **Create** -- add a new agent with full configuration (name, role, system prompt, model, tools, autonomy level, planning mode)
- **Edit** -- modify any agent's configuration
- **Delete** -- remove an agent (does not affect project-specific assignments)

Agents created here serve as templates. Projects can enable/disable them and add custom instructions per agent.

### Library

The global skill library -- reusable skills that can be imported into any project.

- **Browse** -- filter by category and tags
- **Create** -- add new library skills with YAML frontmatter and Markdown body
- **Edit** -- update existing library skills
- **Delete** -- remove from the library

The database ships with 25 seeded library skills covering common use cases (code review, documentation, testing, etc.).

### Tags

Manage the global tag taxonomy used to categorize skills.

- **List** -- all tags with usage counts
- **Create** -- add new tags
- **Delete** -- remove unused tags

::: tip
Tags are shared across the organization. Establish a consistent tagging convention early -- it makes cross-project search much more effective.
:::

---

## Access Section

### Users

User management with role-based access control.

- **List** -- all users in the current organization with their roles
- **Create** -- add a new user (email, name, password, role)
- **Edit** -- change a user's role or details
- **Delete** -- remove a user from the system

Role changes take effect immediately. See [Organizations](./organizations#roles) for role definitions.

### Organizations

Manage organization settings and membership.

- **Details** -- name, slug, description, plan
- **Members** -- list of all members with roles, invite new members, remove existing ones
- **Invitations** -- pending invitations with cancel/resend options

This tab absorbs what was previously a standalone Workspace page.

### SSO

Configure Single Sign-On providers for the organization.

- **List** -- all SSO providers with enabled/disabled status
- **Add** -- configure a new SAML2 or OIDC provider
- **Edit** -- update provider configuration
- **Test** -- validate the SSO handshake before enforcing it
- **Delete** -- remove a provider

```
POST /api/sso-providers/{id}/test
```

See [Organizations](./organizations#sso--saml-setup) for detailed SSO configuration.

### Content Policies

Organization-level content filtering policies that apply to all skills and agents.

- **List** -- all content policies with scope and status
- **Create** -- define a new policy with name, description, and JSON rules
- **Edit** -- modify policy rules
- **Delete** -- remove a policy

Content policies are evaluated during security scans and content reviews. They define what content is acceptable in skills and agent prompts.

```
GET|POST   /api/organizations/{org}/content-policies
GET|PUT|DELETE /api/content-policies/{id}
```

---

## System Section

### Infrastructure

A consolidated view of four subsections:

**API Tokens** -- create and manage Bearer tokens for programmatic API access. Each token has a name, scoped abilities (read, write, admin), and an optional expiration date.

```
GET|POST /api/api-tokens
DELETE   /api/api-tokens/{id}
```

**Custom Endpoints** -- manage OpenAI-compatible inference endpoints (vLLM, TGI, LM Studio). Add, edit, test health, and discover models. See [Models](./models#custom-endpoints).

**Model Health** -- real-time provider status dashboard showing connectivity, latency, and availability for all configured providers. Run benchmarks and compare models. See [Models](./models#model-health--benchmarking).

**Local Models** -- browse Ollama models available on your local instance. Pull new models with one-click download and SSE progress streaming. See [Local Models](./local-models).

### Backups

Create, download, and restore database backups.

- **Create** -- generates a timestamped backup of the database
- **List** -- all available backups with size and creation date
- **Download** -- download a backup file
- **Restore** -- restore the database from a backup file

```
GET|POST /api/backups
POST     /api/backups/restore
GET      /api/backups/{filename}/download
```

::: warning
Restoring a backup overwrites the current database. Always download the current state as a backup before restoring an older one.
:::

### Diagnostics

System health checks that verify all infrastructure components are working.

- **Database** -- connection status, query latency
- **Cache** -- cache driver status, read/write test
- **Queue** -- queue worker status, job throughput
- **Storage** -- filesystem access, available disk space
- **Providers** -- LLM provider connectivity

```
GET /api/diagnostics
GET /api/diagnostics/{check}
```

Each check returns one of three statuses:

| Status | Meaning |
|---|---|
| **Pass** | Component is healthy |
| **Warning** | Component works but with degraded performance |
| **Fail** | Component is unreachable or broken |

::: tip
Run diagnostics after any infrastructure change (server migration, Docker update, network configuration). The `/api/diagnostics` endpoint is also suitable for external monitoring tools.
:::
