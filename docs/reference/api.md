# API Endpoints

All endpoints are served at `http://localhost:8000/api`.

## Authentication

Orkestr uses **session-based authentication** (`auth:web` guard). The React SPA shares session cookies with the Laravel backend via CORS. All API routes require authentication unless noted otherwise.

For programmatic access, Orkestr also supports **API token authentication**. Generate tokens at **Settings > API Tokens** or via the API:

```
POST /api/api-tokens
```

Include the token in requests as a Bearer token:

```
Authorization: Bearer ork_token_...
```

## Public Endpoints (No Auth Required)

```
GET  /api/health                        # Basic health check
GET  /api/diagnostics                   # Detailed system diagnostics
POST /api/stripe/webhook                # Stripe webhooks
POST /api/webhooks/github/{projectId}   # Inbound GitHub push events
GET  /api/billing/plans                 # Plan listing
```

---

## Health

```
GET /api/health
```

Returns `{ "status": "ok" }`.

---

## Projects

### List Projects

```
GET /api/projects
```

Returns an array of all projects with their provider configuration.

### Create Project

```
POST /api/projects
```

```json
{
  "name": "my-project",
  "path": "/path/to/project",
  "git_auto_commit": false
}
```

### Get Project

```
GET /api/projects/{id}
```

### Update Project

```
PUT /api/projects/{id}
```

```json
{
  "name": "new-name",
  "git_auto_commit": true
}
```

### Delete Project

```
DELETE /api/projects/{id}
```

### Scan Project

Reads `.orkestr/skills/*.md` files from disk and upserts them into the database.

```
POST /api/projects/{id}/scan
```

### Sync Project

Writes skills and composed agents to all enabled provider config files.

```
POST /api/projects/{id}/sync
```

### Preview Sync

Performs a dry-run sync and returns per-provider diffs.

```
POST /api/projects/{id}/sync/preview
```

Returns diff data with `status` (added/modified/deleted/unchanged), `current_content`, and `generated_content` for each file.

### Git Log

```
GET /api/projects/{id}/git-log?file=.orkestr/skills/my-skill.md
```

Returns commit history. The `file` parameter is optional -- omit it for full `.orkestr/` history.

### Git Diff

```
GET /api/projects/{id}/git-diff?file=.orkestr/skills/my-skill.md&ref=abc1234
```

Returns the diff of a file against a specific commit ref.

---

## Skills

### List Skills for Project

```
GET /api/projects/{id}/skills
```

### Create Skill

```
POST /api/projects/{id}/skills
```

```json
{
  "name": "Code Review",
  "description": "Reviews code for quality",
  "body": "You are a code reviewer...",
  "model": "claude-sonnet-4-6",
  "max_tokens": 4096,
  "tags": ["review", "quality"],
  "tools": [],
  "includes": ["coding-standards"],
  "template_variables": []
}
```

### Get Skill

```
GET /api/skills/{id}
```

Returns the skill with `resolved_body` (includes expanded) and `token_estimate`.

### Update Skill

```
PUT /api/skills/{id}
```

Same body as create. Creates a new version snapshot.

### Delete Skill

```
DELETE /api/skills/{id}
```

Removes from database and deletes the `.orkestr/skills/{slug}.md` file.

### Duplicate Skill

```
POST /api/skills/{id}/duplicate
```

Creates a copy with a `-copy` slug suffix.

### Lint Skill

```
GET /api/skills/{id}/lint
```

Returns an array of lint issues. See [Prompt Linting](../guide/linting).

### Generate Skill (AI)

```
POST /api/skills/generate
```

```json
{
  "description": "A skill that reviews database migrations for best practices",
  "constraints": "Focus on Laravel migrations"
}
```

Returns generated frontmatter fields and body for review before saving.

---

## Bulk Operations

### Bulk Tag

```
POST /api/skills/bulk-tag
```

```json
{
  "skill_ids": ["uuid1", "uuid2"],
  "tag_ids": ["tag-uuid"],
  "action": "add"
}
```

`action` is `"add"` or `"remove"`.

### Bulk Assign to Agent

```
POST /api/skills/bulk-assign
```

```json
{
  "skill_ids": ["uuid1", "uuid2"],
  "agent_id": "agent-uuid"
}
```

### Bulk Delete

```
POST /api/skills/bulk-delete
```

```json
{
  "skill_ids": ["uuid1", "uuid2"]
}
```

### Bulk Move

```
POST /api/skills/bulk-move
```

```json
{
  "skill_ids": ["uuid1", "uuid2"],
  "project_id": "target-project-uuid"
}
```

---

## Skill Template Variables

### Get Variable Values

```
GET /api/projects/{projectId}/skills/{skillId}/variables
```

### Set Variable Values

```
PUT /api/projects/{projectId}/skills/{skillId}/variables
```

```json
{
  "variables": {
    "language": "Python",
    "framework": "Django"
  }
}
```

---

## Versions

### List Versions

```
GET /api/skills/{id}/versions
```

### Get Version

```
GET /api/skills/{id}/versions/{versionNumber}
```

### Restore Version

```
POST /api/skills/{id}/versions/{versionNumber}/restore
```

---

## Tags

### List Tags

```
GET /api/tags
```

### Create Tag

```
POST /api/tags
```

```json
{
  "name": "security",
  "color": "#ef4444"
}
```

### Delete Tag

```
DELETE /api/tags/{id}
```

---

## Search

### Full-Text Search

```
GET /api/search?q=review&tags=security&project_id=uuid&model=claude-sonnet-4-6
```

All parameters are optional. Returns skills matching the query, grouped by project.

---

## Library

### Browse Library

```
GET /api/library?category=Laravel&tags=testing&q=pest
```

All parameters are optional.

### Import Library Skill

```
POST /api/library/{librarySkillId}/import
```

```json
{
  "project_id": "target-project-uuid"
}
```

---

## Skills.sh (GitHub Import)

### Discover Skills in a Repository

```
POST /api/skills-sh/discover
```

```json
{
  "repo": "owner/repo-name"
}
```

Returns a list of skill file paths found in the repository.

### Preview Skills

Fetch content for a batch of discovered skill files (up to 30).

```
POST /api/skills-sh/preview
```

```json
{
  "repo": "owner/repo-name",
  "paths": [".curated/code-review.md", ".curated/testing.md"]
}
```

### Import a Skill

```
POST /api/skills-sh/import
```

```json
{
  "repo": "owner/repo-name",
  "path": ".curated/code-review.md",
  "target": "project",
  "project_id": "target-project-uuid"
}
```

`target` is `"library"` or `"project"`. When targeting a project, `project_id` is required.

---

## Agents

### List All Agents

```
GET /api/agents
```

### List Project Agents

```
GET /api/projects/{id}/agents
```

Returns all agents with their per-project state (enabled, custom instructions, assigned skills).

### Toggle Agent

```
PUT /api/projects/{id}/agents/{agentId}/toggle
```

### Update Custom Instructions

```
PUT /api/projects/{id}/agents/{agentId}/instructions
```

```json
{
  "custom_instructions": "## Project Rules\n\n- Use Pest PHP for all tests..."
}
```

### Assign Skills to Agent

```
PUT /api/projects/{id}/agents/{agentId}/skills
```

```json
{
  "skill_ids": ["uuid1", "uuid2"]
}
```

### Compose Single Agent

```
GET /api/projects/{id}/agents/{agentId}/compose
```

Returns the composed Markdown output and token estimate.

### Compose All Agents

```
GET /api/projects/{id}/agents/compose
```

Returns composed output for all enabled agents.

---

## Bundles

### Export Bundle

```
POST /api/projects/{id}/export
```

```json
{
  "skill_ids": ["uuid1", "uuid2"],
  "agent_ids": ["agent-uuid"],
  "format": "zip"
}
```

`format` is `"zip"` or `"json"`. Returns the file as a download.

### Import Bundle

```
POST /api/projects/{id}/import-bundle
```

Multipart form upload with:
- `file` -- The bundle file (ZIP or JSON)
- `conflict_mode` -- `"skip"`, `"overwrite"`, or `"rename"`

---

## Webhooks

### List Webhooks

```
GET /api/projects/{id}/webhooks
```

### Create Webhook

```
POST /api/projects/{id}/webhooks
```

```json
{
  "url": "https://example.com/webhook",
  "events": ["skill.created", "skill.updated"],
  "secret": "your-shared-secret"
}
```

### Update Webhook

```
PUT /api/webhooks/{id}
```

### Delete Webhook

```
DELETE /api/webhooks/{id}
```

### View Delivery Log

```
GET /api/webhooks/{id}/deliveries
```

### Test Webhook

```
POST /api/webhooks/{id}/test
```

### GitHub Inbound Webhook

```
POST /api/webhooks/github/{projectId}
```

Receives GitHub push events and triggers a project scan.

---

## Models

### List Available Models

```
GET /api/models
```

Returns models grouped by provider with configuration status.

---

## Test Runner

### Test a Skill (SSE)

```
POST /api/skills/{id}/test
```

```json
{
  "message": "Review this code for bugs..."
}
```

Returns an SSE stream. Events contain token chunks, and a final event with usage stats.

### Playground (SSE)

```
POST /api/playground
```

```json
{
  "system_prompt": "You are a helpful assistant...",
  "messages": [
    { "role": "user", "content": "Hello" },
    { "role": "assistant", "content": "Hi there!" },
    { "role": "user", "content": "What can you do?" }
  ],
  "model": "claude-sonnet-4-6",
  "max_tokens": 4096
}
```

Returns an SSE stream.

---

## Settings

### Get Settings

```
GET /api/settings
```

### Update Settings

```
PUT /api/settings
```

```json
{
  "anthropic_api_key": "sk-ant-...",
  "openai_api_key": "sk-...",
  "gemini_api_key": "AIza...",
  "ollama_url": "http://localhost:11434",
  "default_model": "claude-sonnet-4-6"
}
```

---

## Custom Endpoints

Manage custom LLM inference endpoints (vLLM, TGI, LM Studio).

```
GET    /api/custom-endpoints              # List all custom endpoints
POST   /api/custom-endpoints              # Create a custom endpoint
PUT    /api/custom-endpoints/{id}         # Update a custom endpoint
DELETE /api/custom-endpoints/{id}         # Delete a custom endpoint
```

---

## Model Health

Monitor model provider connectivity and performance.

```
GET  /api/model-health                    # Health status for all providers
POST /api/model-health/benchmark          # Run benchmark against specified models
GET  /api/model-health/compare            # Compare performance across models
```

---

## Local Models

```
GET /api/local-models                     # List available local models (Ollama + custom endpoints)
```

---

## Air-Gap Mode

Control network isolation for restricted environments.

```
GET  /api/air-gap                         # Get current air-gap status
POST /api/air-gap                         # Enable or disable air-gap mode
```

---

## API Tokens

Manage programmatic API access tokens.

```
GET    /api/api-tokens                    # List tokens for current user
POST   /api/api-tokens                    # Create a new token
DELETE /api/api-tokens/{id}               # Revoke a token
```

---

## Guardrails

Organization-level safety and compliance policies.

```
GET    /api/organizations/{org}/guardrails          # List guardrail policies
POST   /api/organizations/{org}/guardrails          # Create a guardrail policy
PUT    /api/organizations/{org}/guardrails/{id}     # Update a policy
DELETE /api/organizations/{org}/guardrails/{id}     # Delete a policy
```

### Guardrail Profiles

```
GET    /api/guardrail-profiles            # List profiles (strict, moderate, permissive)
POST   /api/guardrail-profiles            # Create a custom profile
PUT    /api/guardrail-profiles/{id}       # Update a profile
DELETE /api/guardrail-profiles/{id}       # Delete a profile
```

---

## Security Scanner (E.3)

### Scan a Skill

```
POST /api/skills/{id}/security-scan
```

Returns findings with severity (`critical`, `high`, `medium`, `low`, `info`), category, description, and remediation suggestions.

### Scan Arbitrary Content

```
POST /api/security-scan
```

```json
{
  "content": "Skill body text to analyze for security risks..."
}
```

---

## Content Review (E.3)

### Submit for Review

```
POST /api/skills/{id}/review
POST /api/agents/{id}/review
```

### Approve or Reject

```
POST /api/skill-reviews/{id}/approve
POST /api/skill-reviews/{id}/reject
```

```json
{
  "reason": "Needs more specific error handling guidance"
}
```

---

## Endpoint Approvals (E.3)

### List Pending Approvals

```
GET /api/projects/{id}/endpoint-approvals
```

### Approve or Reject

```
POST /api/endpoint-approvals/{type}/{id}/approve
POST /api/endpoint-approvals/{type}/{id}/reject
```

`{type}` is `custom-endpoint` or `mcp-server`.

---

## Guardrail Reports (E.3)

```
GET /api/organizations/{org}/guardrail-reports           # Violation summary
GET /api/organizations/{org}/guardrail-reports/trends     # Violation trends over time
GET /api/organizations/{org}/guardrail-reports/export     # CSV export
```

### Dismiss a Violation

```
POST /api/guardrail-violations/{id}/dismiss
```

```json
{
  "reason": "False positive -- pattern matched a code example"
}
```

---

## Skill Reviews (E.6)

### List Reviews

```
GET /api/skills/{id}/reviews
```

### Submit a Review

```
POST /api/skills/{id}/reviews
```

```json
{
  "status": "approved",
  "comment": "Looks good, clear structure and examples."
}
```

### Approve or Reject

```
POST /api/skill-reviews/{id}/approve
POST /api/skill-reviews/{id}/reject
```

---

## Skill Ownership (E.6)

```
GET /api/skills/{id}/ownership
PUT /api/skills/{id}/ownership
```

```json
{
  "owner_id": "user-uuid",
  "codeowners": ["user-uuid-1", "user-uuid-2"]
}
```

---

## Skill Analytics (E.6)

```
GET /api/skills/{id}/analytics            # Usage analytics for a single skill
GET /api/analytics/top-skills             # Most-used skills across all projects
GET /api/analytics/trends                 # Usage trends over time
```

Query parameters: `period` (`7d`, `30d`, `90d`, `all`), `limit`.

---

## Regression Tests (E.6)

### Manage Test Cases

```
GET    /api/skills/{id}/test-cases        # List test cases
POST   /api/skills/{id}/test-cases        # Create a test case
PUT    /api/skill-test-cases/{id}         # Update a test case
DELETE /api/skill-test-cases/{id}         # Delete a test case
```

### Run All Tests

```
POST /api/skills/{id}/test-cases/run-all
```

Returns pass/fail results for each test case with model output.

---

## Cross-Model Benchmark (E.6)

```
POST /api/skills/{id}/benchmark
```

```json
{
  "models": ["claude-sonnet-4-6", "gpt-5.4", "gemini-3.1-pro"],
  "message": "Test input message...",
  "max_tokens": 2048
}
```

Returns side-by-side results with output, latency, token counts, and estimated cost per model.

---

## Skill Inheritance (E.6)

```
GET /api/skills/{id}/resolve              # Resolved skill with parent chain
GET /api/skills/{id}/children             # List child skills
PUT /api/skills/{id}/inheritance          # Set parent skill
```

```json
{
  "parent_id": "parent-skill-uuid"
}
```

---

## Reports (E.6)

```
GET /api/reports/skills                   # Skill inventory CSV
GET /api/reports/usage                    # Token consumption CSV
GET /api/reports/audit                    # Audit log CSV
```

All report endpoints return downloadable CSV data.

---

## Notifications (E.6)

```
GET  /api/notifications                   # List notifications for current user
POST /api/notifications/read-all          # Mark all as read
POST /api/notifications/{id}/read         # Mark one as read
```

Returns guardrail violations, review requests, sync results, and system alerts.

---

## GitHub Org Import (E.6)

### Discover Repositories

```
POST /api/import/github/discover
```

```json
{
  "org": "my-github-org"
}
```

### Import Selected Skills

```
POST /api/import/github/import
```

```json
{
  "org": "my-github-org",
  "selections": [
    { "repo": "my-github-org/api-service", "path": ".orkestr/skills/review.md" }
  ],
  "project_id": "target-project-uuid"
}
```

---

# 2026 Roadmap additions (Phases 0–6)

## Model staleness (Phase 1)

### Get staleness status

```http
GET /api/skills/{skill}/staleness?current_model=claude-sonnet-4-6
```

Response:

```json
{
  "data": {
    "is_stale": true,
    "reason": "needs_revalidation",
    "tuned_for_model": "claude-sonnet-4-6",
    "last_validated_model": null,
    "last_validated_at": null,
    "suggested_action": "Run an eval suite against claude-sonnet-4-6 to confirm behavior."
  }
}
```

`reason` values: `ok`, `needs_tuning`, `needs_revalidation`, `model_deprecated`. `current_model` is optional.

### Update tuned-for model

```http
PUT /api/skills/{skill}/staleness        (editor+)
{ "tuned_for_model": "claude-opus-4-6" }
```

Returns the fresh staleness status.

## Compose preview + sharing (Phase 2)

### Compose with model override

```http
GET /api/projects/{project}/agents/{agent}/compose?model=gpt-5.4&depth=full
```

Response now includes `target_model`, `model_context_window`, and `skill_breakdown[]` with per-skill `starts_at_char` / `ends_at_char` offsets for UI highlighting.

### Create a share link

```http
POST /api/projects/{project}/agents/{agent}/compose/share    (editor+)
{
  "model": null,
  "depth": "full",
  "expires_in_days": 7,
  "is_snapshot": true
}
```

Response on success (201):

```json
{
  "data": {
    "uuid": "…",
    "url": "/share/compose/…",
    "expires_at": "2026-04-26T…Z",
    "is_snapshot": true
  }
}
```

Response on secret-scan refusal (422):

```json
{
  "error": "Refusing to create share link: composed output contains potential secrets.",
  "secrets": [
    { "rule": "secret_in_prompt", "message": "Potential Anthropic API key detected.", "line": 14 }
  ]
}
```

### Public share

```http
GET /api/share/compose/{uuid}         (public, throttle:30,1)
```

Returns the composed payload. `410` on expired, `404` on missing. Increments `access_count` + `last_accessed_at`.

### Delete a share

```http
DELETE /api/share/compose/{uuid}      (creator only)
```

Returns `403` for anyone else.

## Eval regression gates (Phase 3)

### Gate config

```http
GET  /api/skills/{skill}/eval-gate
PUT  /api/skills/{skill}/eval-gate    (editor+)
{
  "enabled": true,
  "required_suite_ids": [7],
  "fail_threshold_delta": -5.00,
  "auto_run_on_save": true,
  "block_sync": false
}
```

### Run gate now

```http
POST /api/skills/{skill}/eval-gate/run-now   (editor+)
```

Returns `{ enqueued_run_ids, baseline_info, est_duration_seconds, reason }`.

### Gate status (polled by banner)

```http
GET /api/skills/{skill}/eval-gate/status
```

Returns `{ enabled, pending_count, can_sync, runs: [{ run_id, status, overall_score, delta }, …] }`.

### Eval run status (polled during runs)

```http
GET /api/eval-runs/{eval_run}
```

Returns the current `SkillEvalRun` row including `status`, `overall_score`, `delta_score`, and `results[]`.

### Sync blocked (409)

When `ProviderSyncService::syncProject` hits an eval gate in block-sync mode:

```http
POST /api/projects/{project}/sync    → 409
{
  "error": "Sync blocked by one or more skill eval gates.",
  "blocked_skills": [
    {
      "skill_id": 42,
      "skill_slug": "invoice-helper",
      "skill_name": "Invoice Helper",
      "last_delta": -8.50,
      "last_run_id": 118
    }
  ]
}
```

## Runtime safety (Phase 4)

### Execute with per-run budget

```http
POST /api/projects/{project}/agents/{agent}/execute     (editor+)
{
  "input": { "message": "…" },
  "token_budget": 50000,
  "cost_budget_usd": 2.50
}
```

### Run detail exposes budget + halt info

`GET /api/runs/{run}` now returns:

```json
{
  "status": "halted_guardrail",
  "halt_reason": "loop_detected",
  "halt_step_id": 142,
  "token_budget": 50000,
  "cost_budget_microcents": 2500000,
  "total_tokens": 18400,
  "total_cost_microcents": 87500
}
```

`halt_reason` values: `loop_detected`, `turn_cap_exceeded`, `budget_token_exceeded`, `budget_cost_exceeded`.

## Agent social layer (Phase 5)

### Agent profile

```http
GET /api/agents/{agent}/profile
```

Returns owner, reputation, domain summary, total invocations, last 10 non-private runs.

### Agent routing

```http
POST /api/agents/route
{
  "question": "Who handles invoice refunds?",
  "project_id": 14
}
```

Returns an ordered list of matches with `score` + human-readable `reasoning`.

### Work feed

```http
GET /api/work-feed?agent_id=&user_id=&project_id=&per_page=25
```

Paginated feed of `team` + `org` visible runs scoped to the caller's current org. Only includes `completed` + `halted_guardrail` runs.

### Fork a run

```http
POST /api/runs/{run}/fork
```

Clones `input` + `config` into a new pending private run owned by the forker. Linked via `forked_from_run_id`.

### Set run visibility

```http
PUT /api/runs/{run}/visibility     (creator only)
{ "visibility": "org" }    // private | team | org
```

### Project role assignments

```http
GET    /api/projects/{project}/role-assignments
POST   /api/projects/{project}/role-assignments          (editor+)
PUT    /api/projects/{project}/role-assignments/{id}     (editor+)
DELETE /api/projects/{project}/role-assignments/{id}     (editor+)
```

Body for create: `{ user_id, role: 'ic' | 'dri' | 'coach', scope?, started_at? }`. `DELETE` sets `ended_at` rather than removing the row.

## Compound learning (Phase 6)

### Capability discovery

```http
GET  /api/agents/{agent}/capability-suggestions
POST /api/agents/{agent}/capability-suggestions/dismiss
{ "suggestion_key": "unused_mcp:42", "expires_in_days": 30 }
```

### Skill proposals

```http
GET  /api/skill-proposals[?skill_id=X]
GET  /api/skill-proposals/{proposal}
POST /api/skill-proposals/{proposal}/accept   (editor+)
POST /api/skill-proposals/{proposal}/reject   (editor+)
{ "suppress_days": 30 }
```

### Cross-project propagation

```http
GET  /api/orgs/{organization}/skill-propagations
POST /api/skill-propagations/{propagation}/accept    (editor+)
{ "body_override": "Customized for this project…" }
POST /api/skill-propagations/{propagation}/dismiss   (editor+)
GET  /api/skills/{skill}/lineage
```

