# Orkestr by eooo.ai — Feature Inventory

> Last updated: **2026-03-15**
> 619 Pest tests passing | TypeScript clean | Phases 1-26, A-J complete

---

## Component Layer (Phases 1-26)

The foundation — skills, provider sync, search, and the React SPA.

| Feature | Description |
|---------|-------------|
| **Skill CRUD** | Create, read, update, delete skills with YAML frontmatter + Markdown body |
| **Monaco Editor** | Full code editor for skill authoring with syntax highlighting |
| **YAML Frontmatter Parser** | Parse and validate skill metadata (id, name, model, tags, tools, includes) |
| **Provider Sync** | One-click sync skills to Claude, Cursor, Copilot, Windsurf, Cline, OpenAI config formats |
| **Sync Preview** | Preview what provider sync will write before committing |
| **6 Provider Drivers** | ClaudeDriver (.claude/CLAUDE.md), CursorDriver (.cursor/rules/), CopilotDriver (.github/copilot-instructions.md), WindsurfDriver (.windsurf/rules/), ClineDriver (.clinerules), OpenAIDriver (.openai/instructions.md) |
| **Skill Includes** | Recursive skill composition via `includes` field — max depth 5, circular dependency detection |
| **Template Variables** | `{{variable}}` placeholders resolved at compose/sync time with defaults |
| **Prompt Linter** | 8 quality rules for prompt analysis and improvement suggestions |
| **Skill Library** | Global reusable skill library (25 seeded skills) with category/tag filtering |
| **Library Import** | Import skills from the global library into any project |
| **Skills.sh Import** | Discover and import skills from GitHub repositories via skills.sh |
| **AI Skill Generation** | Generate skills from natural language descriptions using LLM |
| **Version History** | Automatic version snapshots on every skill save |
| **Version Diff Viewer** | Side-by-side diff comparison between skill versions |
| **Version Restore** | Restore any previous skill version |
| **Bulk Operations** | Bulk tag, assign, delete, move skills across projects |
| **Tag Management** | Create, assign, and filter skills by tags |
| **Cross-Project Search** | Full-text search across all skills, projects, and tags |
| **Bundle Export/Import** | Export projects as ZIP/JSON bundles, import with conflict resolution |
| **Git Integration** | Auto-commit on sync, git log, git diff per project |
| **Project Scanning** | Scan filesystem for `.orkestr/` skill files and import |
| **Command Palette** | Cmd+K global search and navigation |
| **Dark/Light/System Theme** | Three-mode theme switcher with system detection |
| **React SPA** | Full single-page application with React, Vite, TypeScript, shadcn/ui |
| **Zustand State Management** | Global app state with persistent project context |
| **Responsive Layout** | Mobile-friendly sidebar with hamburger menu |

## Phase A — Agent Designer (25 issues)

Full agent loop definitions with export capabilities.

| Feature | Description |
|---------|-------------|
| **Agent Data Model** | Agents with name, slug, role, system prompt, model, tools, autonomy level, planning mode |
| **Agent CRUD API** | Full REST API for agent management |
| **Agent Builder UI** | Form-based agent configuration (no code required) |
| **Agent Compose** | Merge base agent + custom instructions + assigned skills into a single prompt |
| **Skill Assignment** | Assign skills to agents per-project with drag-and-drop |
| **Agent Export** | Export to Claude Agent SDK, LangGraph, CrewAI, and generic JSON formats |
| **Agent Visualization** | Graph view of agent-skill-tool relationships |
| **9 Seeded Agents** | Default agent definitions (code reviewer, researcher, writer, etc.) |

## Phase B — Orchestration (27 issues)

Multi-agent workflows as directed acyclic graphs.

| Feature | Description |
|---------|-------------|
| **Workflow Data Model** | Workflows with steps, edges, versions, and execution tracking |
| **Visual Workflow Builder** | Drag-and-drop DAG editor with React Flow |
| **Step Types** | Start, end, agent, checkpoint, condition, parallel split/join |
| **Conditional Branching** | Route execution based on agent output or conditions |
| **Parallel Execution** | Split workflows into concurrent branches, rejoin at sync points |
| **Human-in-the-Loop** | Checkpoint steps that pause for human approval |
| **Delegation Chains** | Agent-to-agent delegation with handoff conditions |
| **Context Bus** | Shared context passing between workflow steps |
| **Workflow Versioning** | Version history for workflow definitions |
| **DAG Validation** | Detect cycles, missing connections, unreachable nodes |

## Phase C — Design + Runtime (35 issues)

Live agent execution inside Orkestr.

| Feature | Description |
|---------|-------------|
| **Agent Execution Engine** | Execute agent loops with real tool calls via MCP |
| **Execution Playground** | Interactive agent execution environment |
| **SSE Streaming** | Server-sent events for real-time execution output |
| **Multi-Turn Chat Playground** | Conversational testing with message history |
| **Tool Call Execution** | Real MCP tool calls during agent runs |
| **Agent Memory** | Working memory and conversation history persisting across runs |
| **Execution Traces** | Full trace of every step, tool call, and decision |
| **Cost Tracking** | Per-execution token usage, cost calculation by model |
| **MCP Server Management** | Configure MCP servers per project (stdio/SSE transport) |
| **A2A Agent Protocol** | Agent-to-agent communication and delegation |

## Phase D — Production-Ready Agent Teams (29 issues)

Multi-model routing, schedules, organizations, and performance.

| Feature | Description |
|---------|-------------|
| **Multi-Model Routing** | LLMProviderFactory routes by model prefix to correct provider |
| **Anthropic Provider** | Claude Opus 4.6, Sonnet 4.6, Haiku 4.5 with streaming + tool use |
| **OpenAI Provider** | GPT-5.4, o3 with streaming |
| **Gemini Provider** | Gemini 3.1 Pro, Gemini 3 Flash with streaming |
| **Grok Provider** | Grok 3 family with streaming |
| **Ollama Provider** | Local model inference with auto-detection |
| **Agent Schedules** | Cron-based and event-triggered agent execution |
| **Organization Management** | Multi-tenant organizations with role-based access |
| **Role-Based Access** | Owner, admin, editor, viewer, member roles |
| **Agent Autonomy Levels** | Supervised, semi-autonomous, autonomous execution modes |
| **Agent Permissions** | Per-agent tool allowlists and budget limits |
| **Performance Dashboard** | Latency, cost, throughput metrics |
| **Agents Dashboard** | Overview of all agents with status and activity |
| **Competitive Comparison** | Landing page comparing Orkestr to alternatives |

## Phase E — Ship the Self-Hosted Product (58 issues)

Enterprise-ready self-hosted deployment.

| Feature | Description |
|---------|-------------|
| **E.1: SSO/SAML** | SSO provider management (SAML2, OIDC) with test capability |
| **E.1: Audit Logs** | Full audit trail of user actions |
| **E.1: Content Policies** | Organization-level content filtering policies |
| **E.1: GitHub OAuth** | Sign in with GitHub |
| **E.1: Apple Sign In** | Sign in with Apple |
| **E.2: Docker Hardening** | Production-ready Docker Compose configuration |
| **E.2: License Keys** | License activation and validation system |
| **E.2: Setup Wizard** | First-run setup with API key config, model selection, demo project |
| **E.2: Backup/Restore** | Create, download, and restore database backups |
| **E.2: Health Diagnostics** | System health checks (database, cache, queue, storage) |
| **E.3: Guardrail Policies** | Organization → Project → Agent cascading safety policies |
| **E.3: Guardrail Profiles** | Pre-built profiles (strict, moderate, permissive) |
| **E.3: Violation Tracking** | Track and report guardrail violations |
| **E.3: Security Scanner** | Scan skills for security risks with severity scoring |
| **E.3: Content Review** | Review workflow for skill and agent content |
| **E.3: Endpoint Approvals** | Approval workflow for custom endpoints and MCP servers |
| **E.4: Grok (xAI) Support** | Full Grok model family integration |
| **E.4: Custom Endpoints** | vLLM, TGI, LM Studio — any OpenAI-compatible endpoint |
| **E.4: Air-Gap Mode** | Block all external network calls, local models only |
| **E.4: Model Health** | Provider health monitoring and benchmarking |
| **E.4: Local Model Browser** | Browse Ollama and custom endpoint models |
| **E.5: OpenAPI Spec** | Auto-generated OpenAPI 3.1 specification |
| **E.5: TypeScript SDK** | Auto-generated SOLID-compliant TypeScript client |
| **E.5: PHP SDK** | Auto-generated PSR-12 compliant PHP client |
| **E.5: Python SDK** | Auto-generated PEP 8 compliant Python client |
| **E.5: API Tokens** | Bearer token authentication with scoped abilities and expiration |
| **E.5: CLI Tools** | `orkestr:deploy` and `orkestr:manage` Artisan commands |
| **E.5: Swagger UI** | Interactive API documentation at /api/docs |
| **E.6: Skill Reviews** | Submit/approve/reject workflow for skill changes |
| **E.6: Skill Ownership** | Owner and codeowner tracking per skill |
| **E.6: Skill Analytics** | Usage trends, top skills, period-based filtering |
| **E.6: Regression Tests** | Per-skill test cases with run-all and pass/fail tracking |
| **E.6: Cross-Model Benchmark** | Benchmark a skill across multiple models simultaneously |
| **E.6: Skill Inheritance** | Parent-child skill relationships with resolved preview |
| **E.6: Notifications** | In-app notification system with read/unread tracking |
| **E.6: Reports** | CSV export for skills, usage, and audit data |
| **E.6: GitHub Org Import** | Discover and import skills from GitHub organizations |

## Phase F — Launch-Ready / Tier 1 (38 issues)

Frontend catch-up, documentation, and install polish.

| Feature | Description |
|---------|-------------|
| **F.1: Settings UI** | API key management, air-gap toggle, SDK downloads, infrastructure quick links |
| **F.1: Infrastructure Pages** | API Tokens, Custom Endpoints, Model Health, Local Models pages |
| **F.2: Guardrails UI** | Policies, profiles, violations management in three-tab layout |
| **F.2: Security Scanner UI** | Trigger scans and view risk-scored findings |
| **F.2: Notifications UI** | Notification list with mark-as-read |
| **F.3: Skill Editor Panels** | Security, Review, Regression Tests, Inheritance panels in 7-tab editor |
| **F.3: Analytics UI** | Top skills table, trends, period filtering |
| **F.3: Reports UI** | Three CSV export cards (skills, usage, audit) |
| **F.3: GitHub Import UI** | Four-step wizard for org discovery and import |
| **F.4: Documentation Site** | VitePress docs with getting-started, deployment, architecture, local models, guardrails guides |
| **F.5: Install Script** | One-line install (`curl ... \| bash`) with prereq checks, env setup, migration |
| **F.5: Production Validator** | `scripts/validate-production.sh` checks .env, docker-compose, services |
| **F.5: Setup Wizard UI** | Four-step onboarding (API keys → model → demo project → done) |
| **F.6: Bug Fixes** | CursorDriver alwaysApply fix, CLAUDE.md updates |

## Phase G — Self-Hosted Differentiation / Tier 2 (17 issues)

Features unique to self-hosted deployment.

| Feature | Description |
|---------|-------------|
| **G.1: VS Code Extension** | Skill browser tree view, frontmatter validation, sync status, test runner with CodeLens |
| **G.2: GitHub Action** | Validate skill format in PRs, auto-sync on merge to main, reusable workflow templates |
| **G.3: Model Pull UI** | One-click Ollama model download with SSE progress streaming |
| **G.3: Model Recommendations** | Task-type based model recommendations (chat, code, summarization, etc.) |
| **G.4: Execution Replay** | Full trace recording with step-through timeline scrubber |
| **G.4: Execution Diff** | Side-by-side comparison of two execution runs |
| **G.5: Helm Chart** | Full Kubernetes deployment with configurable replicas, PVC, ingress, secrets, HPA |
| **G.5: Health Probes** | Liveness, readiness, and startup probes for Kubernetes |
| **G.5: Scaling Guide** | Horizontal scaling documentation with resource profiles |

## Phase H — Settings Consolidation (19 issues)

Unified admin hub replacing Filament backend.

| Feature | Description |
|---------|-------------|
| **H.1: Settings Hub** | 12-tab vertical navigation with 4 sections (Settings, Administration, Access, System) |
| **H.2: License Tab** | License status, activation, usage — replaces standalone Billing page |
| **H.3: Agents Tab** | Default agent definition CRUD (migrated from Filament) |
| **H.3: Library Tab** | Skill library CRUD with backend endpoints (migrated from Filament) |
| **H.3: Tags Tab** | Tag management (migrated from Filament) |
| **H.4: Users Tab** | User management with role-based access (new UserManagementController) |
| **H.4: Organizations Tab** | Org management — absorbed standalone Workspace page |
| **H.5: SSO Tab** | SSO provider management with test capability |
| **H.5: Content Policies Tab** | Content policy management with JSON rules editor |
| **H.6: Infrastructure Tab** | Consolidated API tokens, custom endpoints, model health, local models |
| **H.6: Backups Tab** | Create, download, restore backups |
| **H.6: Diagnostics Tab** | System health checks with pass/warning/fail status |
| **H.7: Sidebar Restructure** | Design / Operate / Govern / Admin navigation sections |

## Phase I — Interactive Canvas Builder (10 issues)

WYSIWYG visual builder for agent orchestration.

| Feature | Description |
|---------|-------------|
| **I.1: Drag-and-Drop Palette** | Sidebar palette with draggable agents, skills, MCP servers |
| **I.1: Skill Attachment** | Drag skills onto agent nodes to assign, with visual feedback |
| **I.1: MCP Connection Nodes** | Drag MCP servers onto canvas, draw edges to agents |
| **I.1: A2A Delegation Edges** | Animated directional arrows between agents for delegation chains |
| **I.1: Edge Configuration** | Click delegation edge to configure trigger, handoff context, return behavior |
| **I.1: Chain Visualization** | Numbered step badges on multi-hop chains, hover highlights full chain |
| **I.1: Node Detail Panel** | Click any node for slide-out edit panel (agent config, skill details, MCP info) |
| **I.1: Auto-Layout** | Dagre-based directed graph layout with connectivity optimization |
| **I.1: Position Persistence** | Save/load node positions per project via canvas_layout JSON column |
| **I.1: Canvas Controls** | Minimap, zoom +/-, fit-to-view, fullscreen toggle, snap-to-grid |

## Phase J — OpenRouter Integration (5 issues)

Single API key access to 200+ models.

| Feature | Description |
|---------|-------------|
| **J.1: OpenRouterProvider** | Full LLM provider with streaming, chat+tools, model name routing |
| **J.1: Model Discovery** | Cached model listing with pricing and context length from OpenRouter API |
| **J.1: Settings Integration** | API key field in Settings > General, configured status badge |
| **J.1: Factory Routing** | `openrouter:` prefix routes through LLMProviderFactory |
| **J.1: Landing Page** | OpenRouter icon in integrations bar, updated feature copy and FAQ |

---

## Cross-Cutting Capabilities

| Capability | Details |
|------------|---------|
| **Authentication** | Session-based (auth:web), GitHub OAuth, Apple Sign In, API tokens (Bearer) |
| **Multi-Tenancy** | Organizations with role-based access (owner/admin/editor/viewer/member) |
| **Plan Gating** | Free, Pro, Teams plans with feature and usage limit enforcement |
| **Stripe Billing** | Subscriptions, plan changes, invoices, Stripe Connect for marketplace sellers |
| **Multi-Model** | 7 LLM providers: Anthropic, OpenAI, Gemini, Grok, OpenRouter, Ollama, Custom endpoints |
| **Provider Sync** | 6 AI tool integrations: Claude, Cursor, Copilot, Windsurf, Cline, OpenAI |
| **Protocols** | MCP (Model Context Protocol) for tools, A2A (Agent-to-Agent) for delegation |
| **Testing** | 619 Pest PHP tests, TypeScript strict mode |
| **Documentation** | VitePress site with deployment, architecture, local models, guardrails, hardware guides |
| **Developer Tools** | VS Code extension, GitHub Action, 3 auto-generated SDKs (TS/PHP/Python), OpenAPI spec, CLI |
| **Deployment** | Docker Compose, Helm chart for Kubernetes, one-line install script, production validator |

---

## By the Numbers

| Metric | Count |
|--------|-------|
| Phases completed | 13 (1-26, A-J) |
| GitHub issues resolved | 250+ |
| Pest tests | 619 |
| API endpoints | 150+ |
| React pages | 25+ |
| Database tables | 35+ |
| LLM providers | 7 |
| Provider sync drivers | 6 |
| Custom React Flow node types | 6 |
| Seeded agents | 9 |
| Seeded library skills | 25 |
