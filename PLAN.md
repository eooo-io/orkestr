# Agentis Studio — Implementation Plan

> This file tracks implementation progress across sessions.
> Refer to `CLAUDE.md` for architecture details.

---

## Roadmap Overview

Agentis Studio is evolving from a skill/config sync tool into a full agent configuration, orchestration, and runtime platform. The roadmap has four phases:

```
Phase A — Agent Designer  (COMPLETE)
  Design agents as full loop definitions (Goal → Perceive → Reason → Act → Observe).
  Export to Claude Agent SDK, LangGraph, CrewAI, and generic JSON.

Phase B — Orchestration  (COMPLETE)
  Multi-agent workflows as DAGs. Visual workflow builder. Human-in-the-loop checkpoints.
  Delegation chains, handoff conditions, shared context.

Phase C — Design + Runtime  (COMPLETE)
  Lightweight agent runtime inside Agentis. Execute agent loops with real tool calls.
  Playground evolves into execution environment. Memory persistence. Traces & cost tracking.

Phase D — Production-Ready Agent Teams  (IN PROGRESS)
  Multi-model routing & fallback chains. Agent schedules & event triggers.
  Organization & team management. Agent autonomy levels & permissions.
  Performance dashboards. Competitive positioning.
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
**Phase D IN PROGRESS** — Production-Ready Agent Teams (29 issues across 5 milestones).

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

Lightweight agent runtime inside Agentis Studio — transforming from a design-time configuration tool into a full execution platform.

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

> **Motivation:** Address five strategic concerns identified during competitive analysis — ensure Agentis Studio has a defensible moat against provider-native tooling, code frameworks, and funded competitors.

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
| #182 | Organization switcher component | TODO |
| #183 | Team invitation flow (API + UI) | TODO |
| #184 | Role enforcement in API controllers | TODO |
| #185 | Workspace settings page | TODO |
| #186 | Organization-scoped data isolation | TODO |
| #187 | Organization API endpoints | TODO |

### D.4 — Agent Autonomy & Permissions

| Issue | Title | Status |
|---|---|---|
| #188 | Agent autonomy levels | TODO |
| #189 | Per-agent budget envelopes | TODO |
| #190 | Agent tool scope configuration | TODO |
| #191 | Human approval gate for tool calls | TODO |
| #192 | Agent data access boundaries | TODO |
| #193 | Agent audit log | TODO |

### D.5 — Agent Performance & Competitive Positioning

| Issue | Title | Status |
|---|---|---|
| #194 | Agent performance dashboard | TODO |
| #195 | Agents-first navigation reframe | TODO |
| #196 | Competitive comparison content | TODO |
| #197 | Agent team overview page | TODO |
| #198 | Landing page: sharpen provider-agnostic positioning | TODO |
| #199 | Onboarding flow: first agent in 3 minutes | TODO |

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
