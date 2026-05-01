# Execution Worker Split — Moving the agent loop out of Laravel

> RFC. Not an accepted plan — a proposed one.
> Created: 2026-04-21
> Status: Draft

## One-line summary

Keep Laravel as the control plane. Move `RunAgentJob`, `RunEvalSuiteJob`, the `ExecutionGuardrailService` loop, and the MCP/A2A clients into a new **TypeScript execution worker** that reads from the same MariaDB and consumes from a shared Redis queue.

## Context

Orkestr's Laravel backend has grown to ~60 migrations, ~40 controllers, and handles everything: CRUD, auth, Filament admin, migrations, versioning, provider sync, multi-tenant orgs, webhooks, schedules, **and** agent execution (the `RunAgentJob` loop — Goal → Perceive → Reason → Act → Observe — plus eval runs, MCP tool dispatch, A2A delegation).

The first block is Laravel's strength. The second block is where the seams show:

- **LLM SDK gap.** `mozex/anthropic-laravel` is a community wrapper. Anthropic, OpenAI, and Google all publish first-class Python and TypeScript SDKs with features (prompt caching ergonomics, batch API, tool-use streaming) that land in PHP months later or never.
- **MCP protocol ownership.** We maintain ~1,200 LOC of MCP client code (`app/Services/Mcp/`) — stdio transport, SSE transport, connection pool, server manager, message framing. Anthropic ships a reference TypeScript implementation of the full MCP client. We're reimplementing it.
- **PHP's process-per-request shape.** Agent execution wants persistent workers that can hold MCP connections open, stream tokens, and run a multi-turn loop for minutes. The `timeout=900` on `RunAgentJob` is a bandaid; the underlying model is mismatched.
- **Streaming ergonomics.** SSE works in Laravel, but awkwardly. Node's async model is the native shape for streaming agent output.

The frontend is already TypeScript. The team already operates a Node toolchain (Vite, Monaco, React Flow). Adding a **TypeScript execution worker** keeps the language count at two.

## What moves vs. what stays

### Moves to the TS worker

Things on the critical path of an agent run:

| Current location | New home |
|---|---|
| `app/Jobs/RunAgentJob.php` | TS worker — primary agent loop |
| `app/Jobs/RunEvalSuiteJob.php` | TS worker — eval execution path |
| `app/Services/Execution/AgentExecutionService.php` | TS worker — synchronous playground path |
| `app/Services/Execution/ExecutionGuardrailService.php` | TS — loop detection, turn caps, budget checks |
| `app/Services/Execution/ToolDispatcher.php` | TS — MCP / A2A / memory tool routing |
| `app/Services/Execution/Guards/*` | TS — ApprovalGuard, BudgetGuard, DataAccessGuard, ToolGuard, OutputGuard |
| `app/Services/Execution/CostCalculator.php` | TS — per-step cost accounting |
| `app/Services/LLM/*` (all 16 files) | TS — Anthropic/OpenAI/Gemini/Grok/OpenRouter/Ollama providers + router + fallback |
| `app/Services/Mcp/*` (all 17 files) | TS — MCP client (ideally via `@modelcontextprotocol/sdk`) |
| `app/Services/A2a/*` (all 3 files) | TS — A2A delegation |
| `app/Services/EvalScoring/*` | TS — KeywordScorer, LlmJudgeScorer, ScorerFactory |

### Stays in Laravel

Everything that's not the execution critical path:

- All CRUD: projects, skills, agents, versions, tags, library, tokens, webhooks
- Filament admin panel (a genuine win — auto-generated admin UIs are hard to replace)
- Auth: Sanctum, GitHub/Apple OAuth, organization middleware
- Migrations + schema ownership (Laravel stays the source of truth)
- Provider sync engine (`app/Services/Providers/*` + `ProviderSyncService`)
- `SkillCompositionService`, `TemplateResolver`, `SkillFileParser`, `ManifestService`, `AgentComposeService`, `PromptLinter`
- Scheduled jobs with short tasks: `RecomputeAgentReputationJob`, `ExtractMemoryPatternsJob`, `SuggestSkillPropagationsJob`, `DispatchWebhookJob`
- All non-execution APIs: gate config, staleness, compose share, work feed, propagations, proposals
- Audit log, notifications, guardrail policy storage

### Intentionally ambiguous (decide during design)

- **`AgentComposeService`** builds the system prompt. Today Laravel does it because CRUD owns skill resolution. Keeping it in Laravel means the worker calls back to an HTTP endpoint at loop-start to get the composed prompt — fine for cold-start, noisy if we ever recompose mid-run. A copy in the worker is tempting but violates single-source-of-truth. **Proposal:** keep in Laravel, the worker fetches once per run.
- **`ExecutionGuardrailService::halt()` and the notification write.** Halt logic moves to TS; the notification row write could be an HTTP call back to a Laravel `POST /internal/notifications` endpoint or a direct DB insert. DB insert is simpler; HTTP is cleaner boundary. **Proposal:** direct DB write for now, revisit if the shared-DB coupling bites.
- **SSE endpoints for playground streaming.** Currently `POST /api/playground` (Laravel) does SSE. If the TS worker owns execution, SSE should be served from the worker — means the SPA has to know a second origin or we run a reverse proxy. **Proposal:** put the TS worker behind the same origin via nginx `/api/execution/*` path routing.

## Boundary design

### Communication model

```
┌─────────────┐     POST /runs (kick off)       ┌──────────────┐
│             │  ────────────────────────────▶  │              │
│   Laravel   │                                 │  TS Worker   │
│  (control)  │    Redis queue (BullMQ-compat)  │  (execution) │
│             │  ──── pending_run_id:142 ────▶  │              │
└─────┬───────┘                                 └──────┬───────┘
      │                                                │
      └───────┬─────── MariaDB ─────────────┬──────────┘
              │  (shared schema, authority  │
              │   per-table — see below)    │
              └─────────────────────────────┘
```

### Who owns which table

This is the most important contract in the split. **Write authority stays with one side** for each table:

| Table | Authoritative writer | Reader |
|---|---|---|
| `projects`, `agents`, `skills`, `skill_versions`, `library_skills`, `tags`, `skill_tag`, `users`, `organizations`, `project_agent`, `agent_skill` | Laravel | Both |
| `project_mcp_servers`, `agent_mcp_server`, `project_a2a_agents`, `agent_a2a_agent` | Laravel | Both |
| `skill_eval_suites`, `skill_eval_prompts`, `skill_eval_gates` | Laravel | Both |
| `execution_runs`, `execution_steps` | **TS worker** | Both |
| `skill_eval_runs` (status, results, overall_score, delta_score) | **TS worker** | Both |
| `agent_memories` | **TS worker** | Both |
| `notifications` (agent.halt and related execution-driven events) | **TS worker** | Both |
| `audit_log` (execution events) | **TS worker** | Laravel (for UI) |
| All other tables (webhooks, gotchas, proposals, propagations, reviews, roles, etc.) | Laravel | Laravel |

This means Laravel creates the `execution_runs` row with `status=pending` and enqueues; the TS worker is the only process that transitions it to `running` / `completed` / `failed` / `halted_guardrail`.

### Queue contract

Redis-backed. BullMQ on the TS side; Laravel dispatches via Horizon or raw Redis with a matching payload shape.

**Job payload:**

```json
{
  "type": "run_agent",
  "run_id": 142,
  "trigger_type": "manual|scheduled|webhook|fork",
  "created_by": 7,
  "dispatched_at": "2026-04-21T10:00:00Z"
}
```

The worker fetches everything else (agent, skill context, budget, MCP config) from the DB using `run_id`. **Payload is minimal and idempotent.** If the worker crashes mid-job, retry re-reads the fresh state.

### Schema change coupling

The biggest risk. Laravel migrations drive the schema; the worker reads it. If Laravel adds a required column the worker doesn't know about, things break silently.

Mitigations:

1. **Generate TypeScript types from Laravel migrations** as part of CI. On every migration PR, run `php artisan db:types-export` (to be built — likely a 1-page Laravel command that introspects the schema and writes `execution-worker/src/db-types.ts`).
2. **Keep a CHANGELOG.worker.md** in the root listing every schema change that affects the worker. PRs that modify `execution_runs`, `execution_steps`, `agent_memories`, `skill_eval_runs`, or `notifications` must have an entry.
3. **Run the TS worker's test suite in Laravel's CI** against the Laravel migration set. A Laravel PR that breaks the worker fails CI before merging.
4. **Feature flag schema-sensitive worker paths.** New columns get wired into the worker via a follow-up PR, not the same PR as the migration.

### Deploy shape

Two processes, same box for dev, separate pods in prod:

```
┌─────────────────────────────┐
│  nginx (or Traefik)         │
│  /api/execution/* → worker  │
│  /api/*          → Laravel  │
│  /               → SPA      │
└──────────┬──────────────────┘
           │
    ┌──────┴──────┐
    │   Laravel   │   php artisan serve / php-fpm
    │   :8000     │
    └─────────────┘
    ┌─────────────┐
    │  TS Worker  │   node dist/index.js (or bun)
    │   :8001     │
    └─────────────┘
    ┌─────────────┐
    │   Redis     │   shared queue + cache
    └─────────────┘
    ┌─────────────┐
    │  MariaDB    │   shared DB
    └─────────────┘
```

Docker Compose picks up a new `worker` service. Production adds a deployment manifest; scaling is independent of the Laravel pod.

## Tech stack for the worker

Picks with reasons:

| Concern | Choice | Why |
|---|---|---|
| Runtime | Node 22 LTS (keep Bun as a future option) | Ecosystem maturity, operational familiarity |
| Language | TypeScript (strict mode) | Type safety on the protocol boundaries matters |
| HTTP framework | Hono | Minimal, fast, good streaming support, Node + Bun compatible |
| Queue | BullMQ | Mature, Redis-backed, pairs with Horizon's queue format via a thin adapter |
| DB | Prisma | Generated types from the Laravel schema (validated against the schema introspection), migrations owned elsewhere — Prisma is read/write client only |
| MCP | `@modelcontextprotocol/sdk` | Official SDK; replaces ~1,200 LOC of our PHP MCP client |
| LLM SDKs | `@anthropic-ai/sdk`, `openai`, `@google/generative-ai`, `ollama` | First-class; supports prompt caching, batch, streaming, tool use natively |
| Testing | Vitest | Fast, TS-native, parallel workers |
| Logging | `pino` | Structured JSON, fast; ships through the same log aggregation as Laravel |

## Migration plan

This is the hard part. A big-bang cutover is out — the worker needs to prove itself on a subset of runs first.

### Phase A — parallel implementation (4-6 weeks)

1. Stand up the TS worker with a stub handler that reads a `run_agent` job and writes `status=running` → `status=completed` with a dummy step. Verifies queue + DB + deploy pipeline.
2. Port the Anthropic provider + `ModelRouter` + `CostCalculator`. Add a Vitest suite against a mocked Anthropic SDK.
3. Port the MCP client using the official SDK. Add fixtures for stdio + SSE transports.
4. Port the agent loop itself (`runLoop` from `RunAgentJob`) with guardrails. At this point the worker can run a real agent end-to-end against test inputs.
5. Port eval runs + scorers (`RunEvalSuiteJob`, `KeywordScorer`, `LlmJudgeScorer`).

Laravel keeps its existing `RunAgentJob` during this phase. Nothing production routes to the worker yet.

### Phase B — shadow mode (2 weeks)

1. Add `execution_runs.executor` column — `'laravel'` or `'ts_worker'`, default `'laravel'`.
2. On dispatch, Laravel rolls a dice: 10% of runs get `executor='ts_worker'` and the matching BullMQ payload; rest stay on the Laravel job. Configurable via org-level or feature-flag setting.
3. Compare results: same inputs → same outputs? Telemetry dashboard for parity.
4. Ramp the TS worker's share to 50%, 100%, while leaving the Laravel path working as a fallback.

### Phase C — deprecation (1-2 weeks)

1. All runs route to the TS worker. Laravel's `RunAgentJob` and related services stay in the codebase but are no longer dispatched.
2. A release later, delete the Laravel execution code and migrate the `executor` column away.

Total ballpark: **8-10 weeks** end to end. Could be compressed if the team commits a second person to it; not recommended for a solo-maintained fork.

## Risks + mitigations

| Risk | Mitigation |
|---|---|
| Schema drift between Laravel migrations and worker types | CI gate that runs worker tests against Laravel's migration set; generated type export on every migration PR |
| Two languages = two places to hold context | Keep the worker narrow — only execution critical path. Resist pulling CRUD into it |
| Ops complexity — one more process to watch | Same container orchestration as Laravel; same log aggregation. If you can't operate two services, the split isn't worth it |
| Playground SSE needs origin routing | nginx path-based routing (`/api/execution/stream/*` → worker). Documented in deploy guide |
| Debugging distributed failures | Correlate by `run_id` in structured logs on both sides. Every worker log line includes the run id |
| `agent_memories` dual-writer trap if Laravel ever wants to create memories directly | `agent_memories` write authority explicitly moves to the worker. Laravel can read, never writes (enforced by review, not at the DB level) |
| The team decides halfway through that Python would have been better | The contract (queue payload + DB authority table) is language-agnostic. A Python rewrite later only touches the worker, not Laravel |

## Open questions

1. **Do we want the worker to own its own schema for worker-internal state** (retry bookkeeping, connection pool cache) or piggyback on Laravel's DB? Leaning: separate, in a `worker_*` table prefix, owned by the worker's own Prisma migrations.
2. **How do we handle `executor='laravel'` runs during shadow mode when the Laravel worker is down?** Fail the run, or fail over to TS worker? Leaning: fail fast during shadow; operators switch the flag.
3. **Does the worker need its own auth layer?** If Laravel is the only enqueuer, no. If we want the SPA to talk to the worker directly for SSE, yes — Sanctum token bearer.
4. **Bun or Node?** Node for phase A. Reevaluate at phase C. Bun's startup time + perf are attractive but operational maturity is still catching up.
5. **What about Filament's execution run detail view?** Stays in Laravel (read-only against the DB). No change.

## Rollback

Every phase has a rollback path:

- **Phase A:** no code routes to the worker. Delete the directory; Laravel keeps working.
- **Phase B:** flip the feature flag to 0% TS worker. Laravel picks up all runs again.
- **Phase C:** revert the PR that deleted Laravel's execution code. This is the riskiest window — pin the Laravel code removal to a dedicated PR that can be cherry-reverted cleanly.

## Alternatives considered

### Python + FastAPI + Celery

The "ecosystem-correct" answer. Best LLM SDKs, biggest agent-engineering community, LangGraph/CrewAI as reference patterns. Rejected because adding Python as a third language has real operational cost (extra CI, extra container base image, extra interview skill). TS closes 80% of the ecosystem gap while staying in a language the team already operates.

### Go

Best-in-class for persistent workers holding many MCP connections open. Rejected because the LLM/agent ecosystem in Go is still sparse and growing — we'd be reimplementing more than we save.

### Stay in PHP, switch to RoadRunner or Swoole

Persistent-worker runtime for PHP would solve the process model problem. Rejected because it doesn't fix the LLM SDK gap, and operating Swoole/RoadRunner has its own rough edges.

### Extract to a separate Laravel app

Same language, different process. Possible but adds no ecosystem benefit — we're still writing PHP against a thin Anthropic wrapper.

## Next steps

If this plan is accepted:

1. Open a tracking issue (likely a Phase 8 milestone) with sub-issues for phase A deliverables
2. Scaffold `execution-worker/` at the repo root with Node + Hono + Prisma + BullMQ wiring
3. Land the `execution_runs.executor` column + feature flag in a Laravel-only PR before writing worker code
4. Port the Anthropic provider first (smallest scope, highest validation value)
