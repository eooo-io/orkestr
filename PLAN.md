# Orkestr by eooo.ai — Implementation Plan

> This file tracks implementation progress across sessions.
> Refer to `CLAUDE.md` for architecture details.

---

## Roadmap Overview

Orkestr is a self-hosted agent orchestration platform. It runs on customer infrastructure, connects to local data via MCP, and works with any model — cloud APIs or local inference. The roadmap:

```
Phase A — Agent Designer  (COMPLETE)
  Design agents as full loop definitions (Goal → Perceive → Reason → Act → Observe).
  Export to Claude Agent SDK, LangGraph, CrewAI, and generic JSON.

Phase B — Orchestration  (COMPLETE)
  Multi-agent workflows as DAGs. Visual workflow builder. Human-in-the-loop checkpoints.
  Delegation chains, handoff conditions, shared context.

Phase C — Design + Runtime  (COMPLETE)
  Agent runtime inside Orkestr. Execute agent loops with real tool calls.
  Playground evolves into execution environment. Memory persistence. Traces & cost tracking.

Phase D — Production-Ready Agent Teams  (COMPLETE)
  Multi-model routing & fallback chains. Agent schedules & event triggers.
  Organization & team management. Agent autonomy levels & permissions.
  Performance dashboards. Competitive positioning.

Phase E — Ship the Self-Hosted Product  (COMPLETE)
  E.1: Admin & security (SSO, audit logs, content policies).
  E.2: Deployment & packaging (Docker hardening, license keys, setup wizard, backup/restore).
  E.3: Guardrails & safety (org policies, profiles, endpoint validation, security scanner).
  E.4: Local model support & air-gap (Grok, vLLM, OpenAI-compatible endpoints, air-gap mode).
  E.5: API & developer access (OpenAPI spec, SDKs, API tokens, CLI tool).
  E.6: Enterprise readiness (review workflows, skill analytics, cross-model benchmarking).

Phase F — Launch-Ready (Tier 1)  (COMPLETE)
  F.1: Frontend catch-up — Settings & Infrastructure UI.
  F.2: Frontend catch-up — Guardrails & Security UI.
  F.3: Frontend catch-up — Enterprise Features UI.
  F.4: Documentation site update for self-hosted pivot.
  F.5: Install & deployment polish.
  F.6: Bug fixes & polish.

Phase G — Self-Hosted Differentiation (Tier 2)  (COMPLETE)
  G.1: VS Code extension.
  G.2: GitHub Action for CI/CD.
  G.3: Model Pull UI (one-click Ollama downloads).
  G.4: Agent execution replay & trace viewer.

Phase H — Settings Consolidation & Filament Migration  (COMPLETE)
  H.1: Settings hub scaffold (vertical tabs, sub-section routing).
  H.2: License & billing migration.
  H.3: Agent & skill administration (from Filament).
  H.4: User & org administration (from Filament, absorb Workspace).
  H.5: Security & policies (SSO, content policies from Filament).
  H.6: Infrastructure & system (consolidate standalone pages, backups, diagnostics).
  H.7: Cleanup (remove Filament link, simplify sidebar, remove dead pages).
  G.5: Helm chart / Kubernetes operator.

Phase I — Interactive Canvas Builder  (COMPLETE)
  I.1: WYSIWYG canvas with drag-drop, A2A chains, node editing, auto-layout.

Phase J — OpenRouter Integration  (COMPLETE)
  J.1: Single API key access to 200+ models.

Phase K — QA & Documentation  (COMPLETE)
  K.1: Playwright setup, browser test suites, QA plan with manual checklist.
  K.2: 18 new docs pages, 5 expanded, sidebar restructured.

Phase L — Canvas Composer  (COMPLETE)
  L.1: Detail panel overhaul — full entity editors in the right flyout.
  L.2: Canvas CRUD — create, delete, and connect entities directly on canvas.
  L.3: Connection drawing — drag-to-connect edges between any compatible nodes.
  L.4: Canvas UX polish — multi-select, undo/redo, context menus, keyboard shortcuts.
  L.5: Backend & persistence — edge config storage, canvas-specific API, graph refresh.

Phase M — Agent Runtime & Deployment  (COMPLETE)
  M.1: Runtime infrastructure — queue workers, MCP pool, budget enforcement, execution job.
  M.2: Canvas execution — run buttons, output drawer, status indicators, execution history.
  M.3: Scheduled & triggered — cron scheduler, webhook triggers, A2A execution, notifications.
  M.4: Agent task assignment — task model, orchestrator routing, canvas task queue, autonomous pickup.

Phase N — Agent Memory & Data Sources  (COMPLETE)
  N.1: Docker infrastructure — PostgreSQL + pgvector + MinIO containers.
  N.2: Agent long-term memory — embeddings, remember/recall/forget, auto-inject into context.
  N.3: Document storage — MinIO MCP server for agent-accessible file storage.
  N.4: Knowledge base — PostgreSQL MCP server for structured data per agent.
  N.5: Memory integration & UI — canvas memory panel, data source settings, execution trace.

Phase O — Skill Architecture Evolution  (COMPLETE)
  O.1: Folder-per-skill support — skill directories with assets, scripts, and reference data.
  O.2: Progressive disclosure — tiered context loading (summary → body → assets) for compose/sync.
  O.3: Gotcha sections & feedback loop — structured failure capture, test-runner integration.
  O.4: Skill taxonomy & classification — first-class categories, capability uplift vs. encoded preference.
  O.5: Eval suites & A/B testing — multi-prompt evals, with/without comparison, description quality scoring.

Phase P — Agent Communication & Artifacts  (COMPLETE)
  P.1: Artifact system — typed agent outputs (report, code, dataset, decision), versioning, preview, sharing.
  P.2: Notification channels — Slack, Teams, email, webhook integrations for agent-to-human comms.
  P.3: Approval gates — human-in-the-loop approval requests that block execution until resolved.
  P.4: Event bus — pub/sub event system, agents publish/subscribe, external event ingestion.
  P.5: Artifact UI — artifact browser, preview renderer, diff viewer, approval workflow.

Phase Q — Agent Runtime & Identity  (COMPLETE)
  Q.1: Daemon agents — long-running persistent processes with heartbeat, state management, wake-on-event.
  Q.2: Credential vault — per-agent secret management, scoped access, rotation, audit trail.
  Q.3: Agent identity — agent-scoped API tokens, RBAC per agent, network policies, resource quotas.
  Q.4: Agent lifecycle — provisioning, health checks, scaling, graceful retirement, rollback.
  Q.5: Runtime dashboard — real-time agent status, token burn rate, cost attribution, APM telemetry.

Phase R — Intelligence & Extensibility  (COMPLETE)
  R.1: Natural language control plane — conversational agent/OS management via chat interface.
  R.2: Plugin system — extension API, custom node types, custom tools, custom UI panels.
  R.3: Agent templates marketplace — full agent configs (not just skills) installable from marketplace.
  R.4: Cross-agent shared memory — shared memory pools, knowledge graphs, collective learning.
  R.5: Smart task routing — agent capability matching, load balancing, SLA-aware scheduling.

Phase S — Collaboration & Mobile  (PLANNED)
  S.1: Real-time collaboration — cursor presence, live updates, collaborative debugging, comments/threads.
  S.2: Mobile control plane — responsive PWA, push notifications, approve/reject, emergency kill switch.
  S.3: Advanced observability — Grafana-level dashboards, custom metrics, alerting rules, cost forecasting.
  S.4: Agent-to-agent negotiation — task bidding, capability advertisement, autonomous team formation.
  S.5: Federation — multi-instance Orkestr clusters, cross-org agent delegation, federated identity.
```

The existing Component Layer (skills, provider sync, MCP, A2A) remains the foundation. Each phase builds on the previous.

---

## Architecture: The Agent Loop

```
Goal
  │
  ▼
Perceive (input + memory retrieval)
  │
  ▼
Reason (model + planning loop)
  │
  ▼
Act (tool call or output)
  │
  ▼
Observe (result fed back into context)
  │
  └──► Repeat until goal met or termination condition hit
```

### Three Layers

```
┌─────────────────────────────────────────┐
│  Orchestration Layer  (Phase B)          │
│  Workflows, DAGs, delegation chains,     │
│  human-in-the-loop checkpoints           │
├─────────────────────────────────────────┤
│  Agent Layer  (Phase A)                  │
│  Goal, Perceive, Reason, Act, Observe    │
│  Each agent is a complete loop           │
├─────────────────────────────────────────┤
│  Component Layer  (Phases 1–26, done)    │
│  Skills, Tools (MCP/A2A), Provider Sync, │
│  Memory (context sources), Schemas       │
└─────────────────────────────────────────┘
```

---

## Current Status

**Phases 1–26 COMPLETE.** Component Layer fully built.
**Phase A COMPLETE** — Agent Designer (25 issues, all closed).
**Phase B COMPLETE** — Orchestration (27 issues, all closed).
**Phase C COMPLETE** — Design + Runtime (35 issues, all closed).
**Phase D COMPLETE** — Production-Ready Agent Teams (29 issues across 5 milestones, all closed).
**Phase E COMPLETE** — Ship the Self-Hosted Product (58 issues across 6 milestones: E.1–E.6, #201–#268).
**Phase F COMPLETE** — Launch-Ready / Tier 1 (38 issues across 6 milestones: F.1–F.6, #301–#338).
**Phase G COMPLETE** — Self-Hosted Differentiation / Tier 2 (17 issues across 5 milestones: G.1–G.5, #401–#417).

**Phase H COMPLETE** — Settings Consolidation & Filament Migration (19 issues across 7 milestones: H.1–H.7, #269–#287).
**Phase I COMPLETE** — Interactive Canvas Builder (10 issues, 1 milestone: I.1, #288–#297).
**Phase J COMPLETE** — OpenRouter Integration (5 issues, 1 milestone: J.1, #298–#302).
**Phase K COMPLETE** — QA & Documentation (30 issues, 2 milestones: K.1–K.2, #303–#332).
**Phase L COMPLETE** — Canvas Composer (37 issues, 5 milestones: L.1–L.5, #338–#374).
**Phase M COMPLETE** — Agent Runtime & Deployment (22 issues, 4 milestones: M.1–M.4, #375–#396).
**Phase N COMPLETE** — Agent Memory & Data Sources (29 issues, 5 milestones: N.1–N.5, #397–#425).
**Phase O COMPLETE** — Skill Architecture Evolution (35 issues, 5 milestones: O.1–O.5, #426–#460).
**Phase P COMPLETE** — Agent Communication & Artifacts (17 issues, 5 milestones: P.1–P.5, #461–#477).
**Phase Q COMPLETE** — Agent Runtime & Identity (15 issues, 5 milestones: Q.1–Q.5, #478–#492).
**Phase R COMPLETE** — Intelligence & Extensibility (12 issues, 5 milestones: R.1–R.5, #493–#504).

**Strategic pivot (2026-03-15):** Repositioned from SaaS to self-hosted infrastructure software. Cloud tier is a free playground/demo funnel. The product runs on customer infra with local models, local MCP, local data. Revenue model is commercial self-hosted license.

---

## Phase A: Agent Designer

### A.1 — Agent Data Model Expansion

| Issue | Title | Status |
|---|---|---|
| #83 | Add agent loop columns to agents table migration | DONE |
| #84 | Add agent_mcp_server and agent_a2a_agent pivot tables | DONE |
| #85 | Update Agent model with casts, relationships, fillable | DONE |
| #86 | Update AgentSeeder with loop field defaults | DONE |
| #87 | Expand project_agent pivot with override columns | DONE |

**Agent Definition Structure:**
```
AgentDefinition
├── identity        (name, slug, role, icon, model, persona_prompt)
├── goal            (objective_template, success_criteria, max_iterations, timeout)
├── perception      (input_schema, memory_sources, context_strategy)
├── reasoning       (planning_mode, skills, temperature, system_prompt)
├── actions         (tools: MCP servers + A2A agents + custom tools)
├── observation     (eval_criteria, output_schema, loop_condition)
└── orchestration   (parent_agent_id, delegation_rules, can_delegate)
```

### A.2 — Agent Designer API

| Issue | Title | Status |
|---|---|---|
| #88 | Create AgentResource API resource | DONE |
| #89 | Expand AgentController with full CRUD | DONE |
| #90 | Agent tool binding endpoints (MCP + A2A) | DONE |
| #91 | Agent export as JSON/YAML | DONE |

**New endpoints:**
```
POST   /api/agents                                    # create
GET    /api/agents/{agent}                             # show
PUT    /api/agents/{agent}                             # update
DELETE /api/agents/{agent}                             # delete
POST   /api/agents/{agent}/duplicate                   # duplicate
GET    /api/agents/{agent}/export?format=json|yaml     # export
PUT    /api/projects/{p}/agents/{a}/mcp-servers        # bind MCP
PUT    /api/projects/{p}/agents/{a}/a2a-agents         # bind A2A
```

### A.3 — Agent Builder UI

| Issue | Title | Status |
|---|---|---|
| #92 | Agent list view with create/edit/delete | DONE |
| #93 | Agent Builder form with loop sections | DONE |
| #94 | Update API client with agent endpoints | DONE |
| #95 | Agent Builder page routing | DONE |

**Builder sections:** Identity, Goal, Perception, Reasoning, Actions, Observation, Orchestration — each collapsible, with Monaco editors for JSON/prompt fields.

### A.4 — Agent Compose v2

| Issue | Title | Status |
|---|---|---|
| #96 | Structured compose output format | DONE |
| #97 | Structured compose API endpoint | DONE |
| #98 | Update provider drivers for structured agents | DONE |
| #99 | Generic JSON agent definition export format | DONE |

**Key change:** `composeStructured()` returns system_prompt, model, tools (MCP/A2A/custom), skills, loop config, delegation config — not just concatenated text.

### A.5 — Agent Visualization Update

| Issue | Title | Status |
|---|---|---|
| #100 | Expand graph endpoint with agent loop data | DONE |
| #101 | React Flow agent loop visualization | DONE |
| #102 | Agent loop detail panel in visualization | DONE |

### A.6 — Testing & Migration

| Issue | Title | Status |
|---|---|---|
| #103 | Pest tests for expanded Agent model | DONE |
| #104 | Pest tests for AgentComposeService v2 | DONE |
| #105 | API endpoint tests for agent CRUD | DONE |
| #106 | Data migration for existing agents | DONE |
| #107 | Update bundle export/import for expanded agents | DONE |

### Implementation Sequence

```
A.1 (data model) ──► A.2 + A.4 in parallel (API + compose) ──► A.3 (UI) ──► A.5 (viz) ──► A.6 (tests throughout)
```

---

## Phase B: Orchestration

Multi-agent workflow designer with visual DAG builder.

### B.1 — Workflow Data Model

| Issue | Title | Status |
|---|---|---|
| #108 | Create workflows table migration | DONE |
| #109 | Create workflow_steps table migration | DONE |
| #110 | Create workflow_edges table migration | DONE |
| #111 | Create workflow_versions table migration | DONE |
| #112 | Create Eloquent models and relationships | DONE |

**Workflow DAG Structure:**
```
Workflow
├── workflows        (uuid, project_id, name, slug, trigger_type, trigger_config,
│                     entry_step_id, status, context_schema, termination_policy, config)
├── workflow_steps   (uuid, workflow_id, agent_id, type, name, position_x/y, config, sort_order)
│   types: agent, checkpoint, condition, parallel_split, parallel_join, start, end
├── workflow_edges   (workflow_id, source_step_id, target_step_id, condition_expression, label, priority)
└── workflow_versions (workflow_id, version_number, snapshot JSON, note)
```

### B.2 — Workflow API

| Issue | Title | Status |
|---|---|---|
| #113 | Create WorkflowResource API resource | DONE |
| #114 | Create WorkflowController with CRUD | DONE |
| #115 | Workflow step and edge management endpoints | DONE |
| #116 | DAG validation service | DONE |
| #117 | Workflow version management | DONE |

**New endpoints:**
```
POST   /api/projects/{p}/workflows                          # create
GET    /api/projects/{p}/workflows                          # list
GET    /api/projects/{p}/workflows/{w}                      # show (with steps+edges)
PUT    /api/projects/{p}/workflows/{w}                      # update
DELETE /api/projects/{p}/workflows/{w}                      # delete
POST   /api/projects/{p}/workflows/{w}/duplicate            # duplicate
PUT    /api/projects/{p}/workflows/{w}/steps                # bulk upsert steps
PUT    /api/projects/{p}/workflows/{w}/edges                # bulk upsert edges
POST   /api/projects/{p}/workflows/{w}/validate             # DAG validation
GET    /api/projects/{p}/workflows/{w}/versions             # version list
POST   /api/projects/{p}/workflows/{w}/versions             # snapshot current
POST   /api/projects/{p}/workflows/{w}/versions/{v}/restore # restore version
```

### B.3 — Workflow Builder UI

| Issue | Title | Status |
|---|---|---|
| #118 | Workflow list page and routing | DONE |
| #119 | Workflow Builder canvas with React Flow | DONE |
| #120 | Custom workflow node components | DONE |
| #121 | Workflow properties panel | DONE |
| #122 | Workflow save, validate, and status controls | DONE |

**Builder:** Interactive React Flow canvas with drag-to-connect edges, custom nodes for each step type, properties panel for selected node/edge, and toolbar with save/validate/status controls.

### B.4 — Delegation & Context Engine

| Issue | Title | Status |
|---|---|---|
| #123 | WorkflowContextService — shared context bus | DONE |
| #124 | WorkflowConditionEvaluator | DONE |
| #125 | Delegation chain resolution | DONE |
| #126 | Context schema and mapping editor UI | DONE |

**Key services:**
- `WorkflowContextService` — manages shared context bus for workflow execution
- `WorkflowConditionEvaluator` — evaluates edge conditions for routing
- `DelegationChainResolver` — resolves agent handoff chains

### B.5 — Workflow Export

| Issue | Title | Status |
|---|---|---|
| #127 | WorkflowExportService — generic JSON | DONE |
| #128 | LangGraph YAML export driver | DONE |
| #129 | CrewAI config export driver | DONE |
| #130 | Export UI and download | DONE |

**Export formats:** Generic JSON, LangGraph YAML, CrewAI config.

### B.6 — Testing & Integration

| Issue | Title | Status |
|---|---|---|
| #131 | Pest tests for Workflow models and relationships | DONE |
| #132 | Pest tests for WorkflowController API | DONE |
| #133 | Pest tests for validation and context services | DONE |
| #134 | Integration — bundle export/import and visualization | DONE |

### Implementation Sequence

```
B.1 (data model) ──► B.2 + B.4 in parallel (API + context engine) ──► B.3 (UI) ──► B.5 (export) ──► B.6 (tests throughout)
```

---

## Phase C: Design + Runtime

Lightweight agent runtime inside Orkestr — transforming from a design-time configuration tool into a full execution platform.

### C.1 — MCP Client

| Issue | Title | Status |
|---|---|---|
| #135 | MCP protocol message types and transport abstraction | DONE |
| #136 | MCP stdio transport driver | DONE |
| #137 | MCP SSE transport driver | DONE |
| #138 | McpClientService — connect, list tools, invoke | DONE |
| #139 | MCP server lifecycle manager | DONE |
| #140 | MCP tool discovery API endpoint | DONE |
| #141 | Pest tests for MCP client services | DONE |

### C.2 — Agent Execution Engine

| Issue | Title | Status |
|---|---|---|
| #142 | Execution runs data model — runs and steps tables | DONE |
| #143 | AgentExecutionService — the agent loop runner | DONE |
| #144 | Tool call parser and dispatcher | DONE |
| #145 | Agent execution API endpoints | DONE |
| #146 | Execution playground UI — run agents live | DONE |
| #147 | Pest tests for agent execution engine | DONE |

### C.3 — Workflow Execution Engine

| Issue | Title | Status |
|---|---|---|
| #148 | Workflow execution runner — DAG traversal engine | DONE |
| #149 | Workflow run data model and status tracking | DONE |
| #150 | Checkpoint approval API and UI | DONE |
| #151 | Workflow execution API endpoints | DONE |
| #152 | Workflow execution visualization — live DAG status | DONE |
| #153 | Pest tests for workflow execution engine | DONE |

### C.4 — Memory & State Persistence

| Issue | Title | Status |
|---|---|---|
| #154 | Agent memory data model — conversation and working memory | DONE |
| #155 | AgentMemoryService — store, retrieve, summarize | DONE |
| #156 | Memory integration with agent execution loop | DONE |
| #157 | Pest tests for memory services | DONE |

### C.5 — Execution Observability

| Issue | Title | Status |
|---|---|---|
| #158 | Execution trace logging and token cost tracking | DONE |
| #159 | Execution detail UI — trace viewer | DONE |
| #160 | Execution dashboard — runs overview and cost analytics | DONE |
| #161 | Pest tests for observability features | DONE |

### C.6 — Runtime Guardrails

| Issue | Title | Status |
|---|---|---|
| #162 | Budget limits and approval gates | DONE |
| #163 | Tool execution sandboxing and allowlists | DONE |
| #164 | Output content filtering and safety checks | DONE |
| #165 | Pest tests for runtime guardrails | DONE |

### C.7 — A2A Protocol Client

| Issue | Title | Status |
|---|---|---|
| #166 | A2A protocol client — agent card discovery | DONE |
| #167 | A2A task delegation — send task and receive result | DONE |
| #168 | A2A integration with tool dispatcher | DONE |
| #169 | Pest tests for A2A protocol client | DONE |

### Implementation Sequence

```
C.1 (MCP client) ──► C.2 (agent execution) ──► C.3 (workflow execution)
                                                      │
                     C.4 (memory) ◄────────────────────┘
                         │
                         ▼
                     C.5 (observability) + C.6 (guardrails) in parallel
                         │
                         ▼
                     C.7 (A2A client)
```

---

## Completed Phases (1–26)

<details>
<summary>Click to expand completed phases</summary>

### Phase 1: Docker Environment & Project Scaffold — DONE
- [x] #1–#8 — Docker, Filament, React SPA scaffold

### Phase 2: Database Migrations & Models — DONE
- [x] #9–#12 — All core tables and Eloquent models

### Phase 3: File I/O & Manifest Engine — DONE
- [x] #13–#16 — SkillFileParser, AgentisManifestService, ProjectScanJob, 19 tests

### Phase 4: Provider Sync Engine — DONE
- [x] #17, #27–#30 — 6 provider drivers, sync orchestration

### Phase 5: Filament Admin Panel — DONE
- [x] #31–#35 — ProjectResource, LibrarySkillResource, TagResource, Settings, Dashboard

### Phase 6: Skills CRUD API — DONE
- [x] #18–#20, #36–#37 — Controllers, resources, 24 routes

### Phase 7: React SPA Core UI — DONE
- [x] #21–#25 — Layout, Projects, ProjectDetail, SkillEditor, Search

### Phase 8: Live Test Runner — DONE
- [x] #26, #38 — SSE streaming via Anthropic SDK

### Phase 9: Version History & Diff Viewer — DONE
- [x] #39–#41 — Version list, Monaco diff, restore flow

### Phase 10: Global Library & Search — DONE
- [x] #42–#45 — 25 seed skills, library import, FULLTEXT search

### Phase 11: Settings, Polish & QA — DONE
- [x] #46–#51 — Settings, toasts, shortcuts, empty states, navigation guards

### Phase 12: Agent Compose & Export — DONE
- [x] #52–#58 — Agent models, seeder, compose service, provider integration

### Phase 13: Token Estimation & Budget Warnings — DONE
- [x] #62 — Per-skill/agent token counts, color-coded budget warnings

### Phase 14: AI-Assisted Skill Generation — DONE
- [x] #70 — Natural language → skill via Anthropic API

### Phase 15: Skill Playground with Streaming — DONE
- [x] #59 — Multi-turn chat, agent compose, model selection

### Phase 16: Skill Dependencies & Composition — DONE
- [x] #60 — Recursive includes, circular dep detection, resolved bodies

### Phase 17: Git-Backed Skill Versioning — DONE
- [x] #61 — Auto-commit, git log, git diff endpoints

### Phase 18: Prompt Linting — DONE
- [x] #63 — 8 lint rules, inline feedback

### Phase 19: Team/Workspace Sharing — DONE
- [x] #64 — ZIP/JSON bundle export/import with conflict resolution

### Phase 20: Provider Diff Preview — DONE
- [x] #65 — Preview sync diff before writing

### Phase 21: Skill Templates — DONE
- [x] #66 — {{variable}} substitution, per-project values

### Phase 22: Bulk Operations — DONE
- [x] #67 — Multi-select, batch tag/assign/move/delete

### Phase 23: Command Palette — DONE
- [x] #68 — Ctrl+K fuzzy search across skills, projects, pages

### Phase 24: Skill Marketplace — DONE
- [x] #69 — Publish, browse, install, vote

### Phase 25: Webhook/Event System — DONE
- [x] #71 — HMAC-signed webhooks, GitHub push receiver

### Phase 26: Multi-Model Test Runner — DONE
- [x] #72 — OpenAI, Gemini, Ollama support

</details>

---

## Phase D: Production-Ready Agent Teams

> **Motivation:** Address five strategic concerns identified during competitive analysis — ensure Orkestr has a defensible moat against provider-native tooling, code frameworks, and funded competitors.

### D.1 — Multi-Model Routing & Fallbacks

| Issue | Title | Status |
|---|---|---|
| #171 | Per-step model override in workflow nodes | DONE |
| #172 | Model fallback chain configuration | DONE |
| #173 | Cost-optimized model routing | DONE |
| #174 | Provider health monitoring & status | DONE |
| #175 | Multi-model execution trace attribution | DONE |

### D.2 — Agent Schedules & Event Triggers

| Issue | Title | Status |
|---|---|---|
| #176 | Agent schedule data model & migration | DONE |
| #177 | Cron schedule builder UI | DONE |
| #178 | Schedule management API endpoints | DONE |
| #179 | Webhook-triggered agent execution | DONE |
| #180 | Trigger configuration UI | DONE |
| #181 | Scheduled execution queue & worker | DONE |

### D.3 — Organization & Team Completion

| Issue | Title | Status |
|---|---|---|
| #182 | Organization switcher component | DONE |
| #183 | Team invitation flow (API + UI) | DONE |
| #184 | Role enforcement in API controllers | DONE |
| #185 | Workspace settings page | DONE |
| #186 | Organization-scoped data isolation | DONE |
| #187 | Organization API endpoints | DONE |

### D.4 — Agent Autonomy & Permissions

| Issue | Title | Status |
|---|---|---|
| #188 | Agent autonomy levels | DONE |
| #189 | Per-agent budget envelopes | DONE |
| #190 | Agent tool scope configuration | DONE |
| #191 | Human approval gate for tool calls | DONE |
| #192 | Agent data access boundaries | DONE |
| #193 | Agent audit log | DONE |

### D.5 — Agent Performance & Competitive Positioning

| Issue | Title | Status |
|---|---|---|
| #194 | Agent performance dashboard | DONE |
| #195 | Agents-first navigation reframe | DONE |
| #196 | Competitive comparison content | DONE |
| #197 | Agent team overview page | DONE |
| #198 | Landing page: sharpen provider-agnostic positioning | DONE |
| #199 | Onboarding flow: first agent in 3 minutes | DONE |

---

## Phase E: Ship the Self-Hosted Product

> **Motivation:** Orkestr is self-hosted infrastructure software. Phase E transforms the working platform into a deployable, licensable, and trustworthy product that runs on customer infrastructure with local models, local data, and full enterprise controls.

### E.1 — Admin & Security

| Issue | Title | Status |
|---|---|---|
| #201 | SAML/OIDC SSO provider integration | DONE |
| #202 | SSO configuration UI in Filament admin | DONE |
| #203 | Secret scanning in skill prompts | DONE |
| #204 | Skill content policies | DONE |
| #205 | Immutable audit log enhancements | DONE |
| #206 | Organization-wide activity feed | DONE |
| #207 | Admin dashboard stats expansion | DONE |
| #208 | Pest tests for E.1 admin features | DONE |

**Key deliverables:**
- Enterprise SSO (SAML 2.0 + OIDC) with auto-provisioning
- Security: secret scanning in prompts, org-level content policies
- Observability: immutable audit logs with export, real-time activity feed
- Admin dashboard with active user counts, cost tracking, error monitoring

### E.2 — Deployment & Packaging

| Issue | Title | Status |
|---|---|---|
| #243 | Production Docker Compose with health checks, restart policies, and volume management | DONE |
| #244 | One-command deploy script with environment validation | DONE |
| #245 | Self-hosted license key generation and validation system | DONE |
| #246 | First-run setup wizard — API keys, model config, MCP server, first agent | DONE |
| #247 | Instance backup and restore tooling | DONE |
| #248 | Upgrade mechanism — versioned migrations and zero-downtime updates | DONE |
| #249 | Reverse proxy configuration templates (nginx, Caddy, Traefik) | DONE |
| #250 | Self-hosted health diagnostics dashboard | DONE |
| #251 | Pest tests for E.2 deployment features | DONE |

**Key deliverables:**
- Production Docker Compose with health checks, named volumes, restart policies
- `orkestr deploy` — one command to validate environment and start services
- License key system — generate, validate, feature-gate by tier (self-hosted vs enterprise)
- First-run setup wizard — walks through API keys, model config, first MCP server, first agent
- Instance backup/restore — export and import full instance state
- Zero-downtime upgrade path with versioned migrations
- Reverse proxy templates for nginx, Caddy, Traefik
- Health diagnostics dashboard — connectivity checks for models, MCP servers, database

### E.3 — Guardrails & Safety

| Issue | Title | Status |
|---|---|---|
| #259 | Organization-level guardrail policy engine — org-wide defaults for budgets, approval levels, blocked tools | DONE |
| #260 | Configurable guardrail profiles — strict, moderate, permissive presets assignable to agents or projects | DONE |
| #261 | MCP server and A2A endpoint validation — allowlists, approval-on-first-connect, URL verification | DONE |
| #262 | Static security scanner — extend PromptLinter with SecurityRuleSet | DONE |
| #263 | Input sanitization guard — validate and sanitize user input before agent execution loop | DONE |
| #264 | LLM-based content review service — on-demand risk scoring for skills and agent configs | DONE |
| #265 | Agent delegation boundary enforcement — cascading budget and scope limits through delegation chains | DONE |
| #266 | Network enforcement guard — validate and enforce air-gap mode, block unexpected outbound calls | DONE |
| #267 | Guardrail reporting dashboard — trigger history, violation trends, dismissal audit trail | DONE |
| #268 | Pest tests for E.3 guardrail features | DONE |

**Key deliverables:**
- Org-level guardrail policies that cascade: org → project → agent (each level can tighten, not loosen)
- Pre-built guardrail profiles (strict/moderate/permissive) for simplified agent configuration
- MCP/A2A endpoint validation with allowlists and approval-on-first-connect
- Static security scanner: prompt injection, exfiltration, credential harvesting, obfuscation detection
- Input sanitization before agent execution (defense against prompt injection via user input)
- LLM-based content review with structured risk scoring (0-100) for skills and configs
- Delegation boundary enforcement: child agents inherit intersected scope and remaining budget
- Network enforcement for air-gap mode: validate all endpoints are local, block unexpected outbound
- Guardrail reporting dashboard with violation trends and exportable compliance reports

**Existing guards (built in Phase C.6) that E.3 extends:**
- `ToolGuard` — allowlist/blocklist, dangerous input patterns → E.3 adds org-level policies
- `BudgetGuard` — per-run/per-agent/daily budgets → E.3 adds delegation cascading
- `OutputGuard` — PII/secret detection and redaction → E.3 adds org-level policies
- `ApprovalGuard` — 3 autonomy tiers → E.3 adds configurable profiles
- `DataAccessGuard` — project/file/API scope → E.3 adds delegation enforcement

### E.4 — Local Model Support & Air-Gap

| Issue | Title | Status |
|---|---|---|
| #252 | Grok/xAI provider — OpenAI-compatible with x.ai base URL | DONE |
| #253 | Generic OpenAI-compatible endpoint configuration for vLLM, TGI, LM Studio | DONE |
| #254 | Model health check and latency benchmarking per provider | DONE |
| #255 | Air-gap mode — disable all external network calls, validate no outbound traffic | DONE |
| #256 | Local model browser — discover and test available Ollama/vLLM models | DONE |
| #257 | Model performance comparison dashboard — cloud vs local side-by-side | DONE |
| #258 | Pest tests for E.4 local model features | DONE |

**Key deliverables:**
- Grok/xAI provider (OpenAI-compatible API with x.ai base URL)
- Generic OpenAI-compatible endpoint config — vLLM, TGI, LM Studio, any local server
- Per-provider health checks and latency benchmarking
- Air-gap mode — explicit toggle that disables all outbound network, validates isolation
- Local model browser — auto-discover models from Ollama/vLLM, test with sample prompts
- Cloud vs local performance comparison — latency, quality, cost side-by-side

**Note:** Air-gap mode (#255) depends on the network enforcement guard (#266) from E.3.

### E.5 — API & Developer Access

| Issue | Title | Status |
|---|---|---|
| #212 | OpenAPI 3.1 specification generation | DONE |
| #213 | REST API SDK — npm package | DONE |
| #214 | REST API SDK — Composer package | DONE |
| #215 | API token authentication for programmatic access | DONE |
| #216 | CLI tool — orkestr deploy, manage, backup | DONE |
| #217 | Pest tests for E.5 developer experience | DONE |

**Key deliverables:**
- OpenAPI 3.1 spec with Swagger UI at /api/docs
- Published SDKs (@eooo/sdk npm + eooo/sdk Composer) for programmatic access
- Personal API tokens for automation and CI/CD integration
- `orkestr` CLI — deploy, manage agents, backup/restore, health check

### E.6 — Enterprise Readiness

| Issue | Title | Status |
|---|---|---|
| #219 | Skill review and approval workflow | DONE |
| #220 | Skill ownership and CODEOWNERS model | DONE |
| #223 | Change request notifications | DONE |
| #225 | Skill analytics dashboard | DONE |
| #227 | Automated regression testing for skills | DONE |
| #230 | Cross-model performance benchmarking — cloud vs local | DONE |
| #231 | Skill inheritance and extension | DONE |
| #240 | Export reports — PDF and CSV | DONE |
| #241 | Bulk import from GitHub organizations | DONE |
| #224 | Pest tests for E.6 enterprise features | DONE |

**Key deliverables:**
- Review/approval workflow: submit → review → approve/reject with comments
- Skill ownership (CODEOWNERS-style) with auto-review-request
- In-app + email notifications for reviews, comments, mentions
- Per-skill analytics: usage, pass rates, token trends, cost per run
- Regression testing: saved test cases per skill, run on edit, block/warn on failure
- Cross-model benchmarking: compare cloud (Claude/GPT/Gemini/Grok) vs local (Ollama/vLLM)
- Skill inheritance: extends/overrides via frontmatter
- PDF/CSV exports for skill inventories, usage, audit logs
- Bulk import from GitHub organizations

### Implementation Sequence

```
E.1 (admin/security)
  │
  ▼
E.2 (deployment/packaging)
  │
  ▼
E.3 (guardrails/safety)
  │
  ▼
E.4 (local models/air-gap)  ◄── depends on E.3 network enforcement
  │
  ├──────────────────┐
  ▼                  ▼
E.5 (API/CLI)    E.6 (enterprise)
```

**Priority order:** E.1 → E.2 → E.3 → E.4 → E.5 + E.6 in parallel

E.1 is the admin foundation (SSO, security, audit).
E.2 makes the product deployable and licensable — this is the monetization gate.
E.3 makes it trustworthy — guardrails are what let enterprise customers say "yes" to production deployment.
E.4 expands model flexibility — Grok, vLLM, air-gap mode (depends on E.3 network guard).
E.5 and E.6 can run in parallel — developer access and enterprise polish.

---

## Phase F: Launch-Ready (Tier 1)

> **Motivation:** Phase E built all the backend APIs and services. Phase F connects them to the frontend, updates documentation for the self-hosted pivot, and polishes for launch. This is the gap between "it works" and "someone can use it."

### F.1 — Frontend: Settings & Infrastructure UI

Wire up E.4 backend APIs (custom endpoints, API tokens, model health, local models, air-gap) to the React SPA.

| Issue | Title | Status |
|---|---|---|
| #301 | TypeScript types for E.3-E.6 entities (CustomEndpoint, ApiToken, GuardrailPolicy, etc.) | DONE |
| #302 | API client functions for custom endpoints, model health, local models, air-gap | DONE |
| #303 | API Tokens management page (create, list, delete, copy token) | DONE |
| #304 | Custom Endpoints management UI (CRUD, health check, model discovery) | DONE |
| #305 | Model Health dashboard (provider status, latency, benchmarks) | DONE |
| #306 | Local Models browser (Ollama/vLLM discovery, model details) | DONE |
| #307 | Air-Gap mode toggle and status indicator in Settings | DONE |
| #308 | Settings page expansion — Grok API key, custom endpoints link, model health link | DONE |
| #309 | Sidebar navigation updates for new pages | DONE |

### F.2 — Frontend: Guardrails & Security UI

Wire up E.3 guardrail APIs to the React SPA.

| Issue | Title | Status |
|---|---|---|
| #310 | API client functions for guardrails, profiles, violations, security scanner, endpoint approvals | DONE |
| #311 | Guardrail Policies management page (CRUD, org-level) | DONE |
| #312 | Guardrail Profiles page (strict/moderate/permissive presets) | DONE |
| #313 | Guardrail Violations dashboard (violation trends, dismissal workflow) | DONE |
| #314 | Endpoint Approvals page (approve/reject MCP/A2A on first connect) | DONE |
| #315 | Security Scanner integration in Skill Editor (scan button, inline results) | DONE |
| #316 | Content Review trigger in Skill Editor (risk score display) | DONE |
| #317 | Notifications dropdown component (bell icon, unread count, mark as read) | DONE |

### F.3 — Frontend: Enterprise Features UI

Wire up E.6 enterprise APIs to the React SPA.

| Issue | Title | Status |
|---|---|---|
| #318 | API client functions for reviews, ownership, analytics, regression tests, inheritance, reports, GitHub import | DONE |
| #319 | Skill Review workflow UI (submit, approve/reject, comments in Skill Editor) | DONE |
| #320 | Skill Ownership display and assignment in Skill Editor | DONE |
| #321 | Skill Analytics dashboard page (usage, pass rates, token trends, cost per run) | DONE |
| #322 | Regression Test Cases panel in Skill Editor (CRUD, run all, results) | DONE |
| #323 | Cross-Model Benchmark trigger and results display | DONE |
| #324 | Skill Inheritance UI (extends selector, resolved preview, children list) | DONE |
| #325 | Reports export page (skill inventory CSV, usage CSV, audit log CSV) | DONE |
| #326 | GitHub Org Import wizard (discover repos, select skills, import) | DONE |

### F.4 — Documentation Update

Update VitePress docs site for the self-hosted pivot and new features.

| Issue | Title | Status |
|---|---|---|
| #327 | Update getting-started guide for self-hosted installation | DONE |
| #328 | Self-hosted deployment guide (Docker, Docker Compose, env config, reverse proxy) | DONE |
| #329 | API reference update (E.3-E.6 endpoints, authentication methods) | DONE |
| #330 | Architecture overview doc (agent loop, orchestration, component layers) | DONE |
| #331 | Local models & air-gap guide | DONE |
| #332 | Guardrails & security configuration guide | DONE |

### F.5 — Install & Deployment Polish

| Issue | Title | Status |
|---|---|---|
| #333 | One-line install script (`curl ... \| bash`) with env wizard | DONE |
| #334 | Production docker-compose validation and hardening | DONE |
| #335 | First-run setup wizard frontend integration | DONE |

### F.6 — Bug Fixes & Polish

| Issue | Title | Status |
|---|---|---|
| #336 | Fix ProviderSyncTest `alwaysApply: false` assertion | DONE |
| #337 | Update CLAUDE.md with E.3-E.6 endpoints and models | DONE |
| #338 | TypeScript type-check pass (`npx tsc --noEmit`) | DONE |

### Implementation Sequence

```
F.1 (types + API client + settings UI)
  │
  ├──────────────────┐
  ▼                  ▼
F.2 (guardrails UI)  F.3 (enterprise UI)  ← can run in parallel
  │                  │
  └────────┬─────────┘
           ▼
F.4 (documentation) + F.5 (install) + F.6 (polish)  ← can run in parallel
```

**Priority order:** F.1 first (shared types/client needed by F.2 and F.3), then F.2 + F.3 in parallel, then F.4-F.6.

---

## Phase G: Self-Hosted Differentiation (Tier 2)

> **Motivation:** These features are what make Orkestr uniquely valuable on customer infrastructure — beyond what cloud-only tools offer. Build after Tier 1 launches.

### G.1 — VS Code Extension

| Issue | Title | Status |
|---|---|---|
| #401 | VS Code extension scaffold (TypeScript, vsce packaging) | DONE |
| #402 | Skill browser tree view (list/search skills from Orkestr API) | DONE |
| #403 | Skill editor with syntax highlighting and frontmatter validation | DONE |
| #404 | Sync status indicator and push/pull commands | DONE |
| #405 | Skill test runner integration | DONE |

### G.2 — GitHub Action for CI/CD

| Issue | Title | Status |
|---|---|---|
| #406 | GitHub Action: validate skill format in PRs | DONE |
| #407 | GitHub Action: auto-sync skills on merge to main | DONE |
| #408 | Workflow template `.github/workflows/orkestr-sync.yml` | DONE |

### G.3 — Model Pull UI

| Issue | Title | Status |
|---|---|---|
| #409 | One-click Ollama model download from local model browser | DONE |
| #410 | Download progress tracking (SSE or polling) | DONE |
| #411 | Model recommendation engine (best model per task type) | DONE |

### G.4 — Agent Execution Replay

| Issue | Title | Status |
|---|---|---|
| #412 | Full execution trace recording (every tool call, LLM response, decision point) | DONE |
| #413 | Replay UI — step-through execution with timeline scrubber | DONE |
| #414 | Execution diff — compare two runs side-by-side | DONE |

### G.5 — Helm Chart / Kubernetes Operator

| Issue | Title | Status |
|---|---|---|
| #415 | Helm chart with configurable replicas, PVC, ingress, secrets | DONE |
| #416 | Kubernetes health probes and readiness checks | DONE |
| #417 | Horizontal scaling documentation | DONE |

---

## Phase H: Settings Consolidation & Filament Migration

> **Motivation:** Unify all admin functionality into the React SPA Settings page. Eliminate the need for a separate Filament admin panel. Settings becomes the single admin hub with vertical tab navigation.

### H.1 — Settings Hub Scaffold

| Issue | Title | Status |
|---|---|---|
| #269 | Settings hub: vertical tab layout with sub-section routing | DONE |
| #270 | Settings General tab: migrate existing API keys and preferences | DONE |

### H.2 — License & Billing Migration

| Issue | Title | Status |
|---|---|---|
| #271 | Settings License tab: license status, activation, usage | DONE |
| #272 | Remove standalone Billing page and sidebar item | DONE |

### H.3 — Agent & Skill Administration

| Issue | Title | Status |
|---|---|---|
| #273 | Settings Agents tab: default agent definitions CRUD | DONE |
| #274 | Settings Library tab: skill library CRUD | DONE |
| #275 | Settings Tags tab: tag management CRUD | DONE |

### H.4 — User & Org Administration

| Issue | Title | Status |
|---|---|---|
| #276 | Settings Users tab: user management CRUD | DONE |
| #277 | Settings Organizations tab: org management | DONE |
| #278 | Remove standalone Workspace page and sidebar item | DONE |

### H.5 — Security & Policies

| Issue | Title | Status |
|---|---|---|
| #279 | Settings SSO tab: SSO provider management | DONE |
| #280 | Settings Content Policies tab: content policy management | DONE |

### H.6 — Infrastructure & System

| Issue | Title | Status |
|---|---|---|
| #281 | Settings Infrastructure tab: consolidate API tokens, endpoints, model health, local models | DONE |
| #282 | Settings Backups tab: backup and restore management | DONE |
| #283 | Settings Diagnostics tab: system health checks | DONE |

### H.7 — Cleanup & Sidebar Simplification

| Issue | Title | Status |
|---|---|---|
| #284 | Remove Filament admin panel link from sidebar footer | DONE |
| #285 | Simplify sidebar Admin section to single Settings item | DONE |
| #286 | Remove standalone infrastructure pages and routes | DONE |
| #287 | Update CLAUDE.md and PLAN.md for Phase H completion | DONE |

---

## Phase I: Interactive Canvas Builder

> **Motivation:** The Canvas tab is the primary design surface — upgrade from read-only visualization to an interactive WYSIWYG builder with drag-and-drop agents, skill assignment, MCP wiring, and A2A delegation chain configuration.

### I.1 — Interactive Canvas Builder

| Issue | Title | Status |
|---|---|---|
| #288 | Canvas: agent nodes with drag-and-drop from sidebar palette | DONE |
| #289 | Canvas: skill chip attachment — drag skills onto agent nodes | DONE |
| #290 | Canvas: MCP server connection nodes | DONE |
| #291 | Canvas: A2A delegation edges with directional arrows | DONE |
| #292 | Canvas: A2A edge configuration panel | DONE |
| #293 | Canvas: chain visualization with numbered steps | DONE |
| #294 | Canvas: node detail side panel — edit agent/skill/MCP on click | DONE |
| #295 | Canvas: auto-layout algorithm for clean initial positioning | DONE |
| #296 | Canvas: persist node positions per project | DONE |
| #297 | Canvas: minimap, zoom controls, and fullscreen toggle | DONE |

---

## Phase J: OpenRouter Integration

> **Motivation:** Single API key access to 200+ models. Reduces friction for new users — one OpenRouter key replaces 4+ provider keys.

### J.1 — OpenRouter Integration

| Issue | Title | Status |
|---|---|---|
| #298 | OpenRouterProvider: LLM provider implementation | DONE |
| #299 | OpenRouter API key setting and Settings UI | DONE |
| #300 | OpenRouter model discovery endpoint | DONE |
| #301 | OpenRouter Pest tests | DONE |
| #302 | Update CLAUDE.md and PLAN.md for OpenRouter integration | DONE |

---

## Phase K: QA & Documentation

> **Motivation:** Production readiness requires comprehensive testing and documentation. Playwright browser tests + manual QA plan for test coverage. Full user documentation for every feature.

### K.1 — QA Infrastructure & Browser Tests

| Issue | Title | Status |
|---|---|---|
| #303 | Playwright setup: install, config, CI integration | DONE |
| #304 | E2E: auth flows | DONE |
| #305 | E2E: project CRUD and navigation | DONE |
| #306 | E2E: skill editor | DONE |
| #307 | E2E: canvas interactions | DONE |
| #308 | E2E: settings hub | DONE |
| #309 | E2E: sidebar navigation and command palette | DONE |
| #310 | E2E: landing page and setup wizard | DONE |
| #311 | E2E: responsive layout | DONE |
| #312 | QA plan: manual test checklist document | DONE |

### K.2 — Comprehensive User Documentation

| Issue | Title | Status |
|---|---|---|
| #313 | Docs: projects guide | DONE |
| #314 | Docs: skill editor guide | DONE |
| #315 | Docs: agent teams guide | DONE |
| #316 | Docs: canvas builder guide | DONE |
| #317 | Docs: workflows guide | DONE |
| #318 | Docs: execution guide | DONE |
| #319 | Docs: connections guide | DONE |
| #320 | Docs: schedules guide | DONE |
| #321 | Docs: models guide | DONE |
| #322 | Docs: organizations guide | DONE |
| #323 | Docs: settings guide | DONE |
| #324 | Docs: API access guide | DONE |
| #325 | Docs: security guide | DONE |
| #326 | Docs: analytics guide | DONE |
| #327 | Docs: import/export guide | DONE |
| #328 | Docs: troubleshooting guide | DONE |
| #329 | Docs: skill format reference | DONE |
| #330 | Docs: keyboard shortcuts reference | DONE |
| #331 | Docs: expand existing pages | DONE |
| #332 | Docs: update VitePress sidebar | DONE |

### Deferred (Post-Tier 2)

Previously tracked as F.1-F.3, these remain deferred:

- Skill intelligence: #226 (A/B testing), #228 (similarity detection), #229 (dynamic activation)
- Ecosystem: #233-#237 (marketplace), #238 (n8n/Temporal integration)
- Collaboration: #218 (real-time editing), #221 (three-way merge), #222 (inline commenting)

---

## Phase L: Canvas Composer

> **Motivation:** The canvas is currently a visualization tool with shallow editing. For public release, it must be the primary composition surface — where you build the entire agent orchestra. Create agents, assign skills, wire MCP servers, draw delegation chains, and configure every entity's settings, all without leaving the canvas.

**Current state:** Phase I (#288-#297) built the canvas with drag-drop from palette, node positioning, auto-layout, minimap, and a read-only detail panel. But:
- Detail panel only edits agent `custom_instructions` — all other fields are read-only
- Cannot create new entities (agents, MCP, A2A) from the canvas — palette only shows existing ones
- Cannot delete entities or edges from the canvas
- Cannot draw connections by dragging between nodes
- Edge configs (delegation trigger, handoff context) are local state only — not persisted
- MCP and A2A detail panels are skeletal (name + transport, nothing editable)
- Skill detail panel links out to the skill editor — no inline editing

### L.1 — Detail Panel Overhaul

Full-featured entity editors in the right flyout. Every field editable, every change persisted via API.

| Issue | Title | Status |
|---|---|---|
| #338 | Agent detail panel: full editor with all loop fields | DONE |
| #339 | Agent detail panel: skill assignment manager | DONE |
| #340 | Agent detail panel: MCP server binding | DONE |
| #341 | Agent detail panel: A2A agent binding | DONE |
| #342 | Agent detail panel: enable/disable toggle and delete | DONE |
| #343 | Skill detail panel: inline frontmatter editor | DONE |
| #344 | Skill detail panel: embedded Monaco prompt editor | DONE |
| #345 | MCP server detail panel: full editor | DONE |
| #346 | A2A agent detail panel: full editor | DONE |
| #347 | Edge config panel: persist delegation config to backend | DONE |

**Key changes:**
- AgentDetail becomes a tabbed editor: Identity, Reasoning, Tools, Orchestration
- SkillDetail embeds a mini Monaco editor for quick prompt edits
- MCP/A2A detail panels become full CRUD forms
- All panels call real API endpoints on save, then refresh the graph data
- Panel width increases from 400px to 480px to fit forms

### L.2 — Canvas Entity Creation & Deletion

Create new entities directly from the canvas. No more switching to other pages just to add an agent or MCP server.

| Issue | Title | Status |
|---|---|---|
| #348 | Canvas palette: + button to create new entities | DONE |
| #349 | Create agent from canvas flyout | DONE |
| #350 | Create MCP server from canvas flyout | DONE |
| #351 | Create A2A agent from canvas flyout | DONE |
| #352 | Create skill from canvas flyout | DONE |
| #353 | Delete node from canvas with confirmation | DONE |
| #354 | Delete edge from canvas | DONE |
| #355 | Unassign skill from agent via canvas | DONE |

**Key behaviors:**
- "+" button in palette header opens the detail flyout in "create" mode
- Create forms are minimal (name + required fields) — full config happens in the detail panel after creation
- Delete agent shows warning if it has assigned skills or delegation edges
- Deleting an edge between agent↔skill calls `PUT /agents/{id}/skills` to unassign
- Graph data refreshes after every create/delete operation

### L.3 — Connection Drawing

Drag-to-connect between nodes to create relationships. The canvas becomes the wiring surface.

| Issue | Title | Status |
|---|---|---|
| #356 | Drag-to-connect: agent → skill assignment | DONE |
| #357 | Drag-to-connect: agent → MCP server binding | DONE |
| #358 | Drag-to-connect: agent → A2A delegation | DONE |
| #359 | Drag-to-connect: agent → agent delegation | DONE |
| #360 | Connection validation rules | DONE |
| #361 | Connection handles on nodes | DONE |
| #362 | Visual feedback during connection drag | DONE |

**Implementation:**
- React Flow `onConnect` handler with source/target node type checking
- Connection handles positioned on right edge (source) and left edge (target)
- Handle colors match node type: violet (agent), green (skill), pink (MCP), cyan (A2A)
- Invalid connections show red dashed preview and snap back
- Valid connections immediately call the appropriate API and create the styled edge

### L.4 — Canvas UX Polish

Quality-of-life features that make the canvas feel like a real design tool.

| Issue | Title | Status |
|---|---|---|
| #363 | Multi-select: shift+click and box selection | DONE |
| #364 | Context menu: right-click on nodes, edges, and canvas | DONE |
| #365 | Keyboard shortcuts for canvas | DONE |
| #366 | Undo/redo for canvas operations | DONE |
| #367 | Auto-save canvas positions (debounced) | DONE |
| #368 | Empty canvas onboarding state | DONE |
| #369 | Node search and filter in toolbar | DONE |

### L.5 — Backend & Persistence

API changes needed to support canvas-driven composition.

| Issue | Title | Status |
|---|---|---|
| #370 | Edge config model and API endpoints | DONE |
| #371 | Include edge configs in graph endpoint response | DONE |
| #372 | Optimistic graph refresh after canvas mutations | DONE |
| #373 | Agent quick-create API endpoint | DONE |
| #374 | Pest tests for canvas CRUD and edge config persistence | DONE |

### Implementation Sequence

```
L.1 (detail panels) ──► L.2 (create/delete) ──► L.3 (connection drawing)
                                                         │
                              L.5 (backend) ◄────────────┘
                                  │
                                  ▼
                              L.4 (UX polish)
```

**Priority order:** L.1 first (the detail panel is the foundation — every other feature opens it). L.2 next (can't compose without creating). L.3 follows (connections need working panels). L.5 runs alongside as backend support. L.4 last (polish after core works).

---

## Tech Decisions

- Anthropic SDK: `mozex/anthropic-laravel`
- DB_HOST: `127.0.0.1` in .env (local), `mariadb` in .env.example (Docker)
- FULLTEXT index conditionally skips on SQLite for tests
- Remote uses HTTPS
- Session-based auth (`auth:web`) on API routes
- React Flow (`@xyflow/react`) for visualization
- Default seeded user: `admin@admin.com` / `password`

## Commands

```bash
# Local dev
composer dev                    # server + queue + pail + vite

# Docker
make up && make migrate         # start + seed

# Tests
composer test                   # or: make test (Docker)
cd ui && npx tsc --noEmit      # type-check SPA
```
