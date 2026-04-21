# Changelog

## v1.1.0 — 2026 Roadmap (Phases 0–6)

Seven phases shipped across seven PRs. Harness engineering (0–3) turned the skill editor into a quality-tracking surface; the org-design layer (4–6) made runs bounded, agents social, and learning compound. 26 new migrations, 106 new Pest tests.

### Phase 0 — Quick wins ([PR #549](https://github.com/eooo-io/orkestr/pull/549))

- **Inline gotcha strip** above Monaco in the skill editor shows open-gotcha counts + top critical titles without switching tabs
- **Progressive-disclosure lint rule** — `PromptLinter::lintSkill(Skill)` overload checking `summary`, `description`, and `body` structure. Three new rules: `missing_summary`, `missing_description`, `no_progressive_disclosure`

### Phase 1 — Model staleness tracking ([PR #550](https://github.com/eooo-io/orkestr/pull/550))

- New columns: `skills.tuned_for_model`, `last_validated_model`, `last_validated_at`, `last_validated_eval_run_id`; `skill_versions.tuned_for_model` freezes intent per snapshot
- **`SkillStalenessService`** returns `{is_stale, reason, tuned_for_model, last_validated_model, last_validated_at, suggested_action}` with reasons `ok`, `needs_tuning`, `needs_revalidation`, `model_deprecated`
- **`StalenessBanner`** surfaces state above Monaco; **"Tuned for model"** dropdown in the frontmatter form
- **Version history** shows per-version tuned-for model badges
- Endpoints: `GET|PUT /api/skills/{skill}/staleness`

### Phase 2 — Compose preview + sharing ([PR #551](https://github.com/eooo-io/orkestr/pull/551))

- `AgentComposeService::compose()` gains `?string $modelOverride`; response includes `target_model`, `model_context_window`, and `skill_breakdown` with per-skill char offsets (`starts_at_char`/`ends_at_char`) for hover highlighting
- **`ComposeShareLink`** with UUID, expiry (7-day default), snapshot-by-default, secret-scan gate via `PromptLinter`
- **Public `GET /api/share/compose/{uuid}`** route with `throttle:30,1`, 410/404 handling, access counting
- New SPA page **`/share/compose/:uuid`** (no sidebar, no auth); share button + modal in `AgentComposePreview`

### Phase 3 — Eval regression gates ([PR #552](https://github.com/eooo-io/orkestr/pull/552))

- **`ScorerInterface`** with `KeywordScorer` (deterministic) + opt-in `LlmJudgeScorer`; resolved per-suite via `skill_eval_suites.scorer` column
- **`RunEvalSuiteJob`** (`ShouldQueue`, 900s timeout) replaces the synchronous run path; `useEvalRunStatus(runId)` polling hook
- `skill_eval_runs` linked to `skill_version_id` + `baseline_run_id` + `delta_score`
- **`skill_eval_gates`** table + **`SkillEvalGateService`** — `evaluateSkillSave`, `findBaselineFor` (most recent run for `(suite, model)`), `computeDelta`, `canSync`
- `SkillController::update` returns `gate_decision`; `ProviderSyncService::syncProject` raises `EvalGateBlockedException` (409) on failing deltas
- **`RegressionGateBanner`** + **`RegressionDeltaModal`** + **`GateConfigPanel`** in `EvalPanel`; Zustand `pendingEvalGates` slice survives navigation
- **⚠️ `QUEUE_CONNECTION=sync` is no longer safe after this phase**

### Phase 4 — Runtime safety guardrails ([PR #553](https://github.com/eooo-io/orkestr/pull/553))

- **`ExecutionGuardrailService`** — loop detection (`xxh128((agent, tool, input))` signature, 6-step window, 3-repeat threshold), turn caps (org-level `max_agent_turns_per_run`, default 40), per-run token + cost budgets
- Budget precedence: **per-run override → per-agent → org default**
- New halt reasons: `loop_detected`, `turn_cap_exceeded`, `budget_token_exceeded`, `budget_cost_exceeded` — status `halted_guardrail`, owner notification via `agent.halt`
- `POST /api/projects/{p}/agents/{a}/execute` accepts `token_budget` + `cost_budget_usd` overrides
- Halt banner in `ExecutionPlayground` with human-readable reason

### Phase 5 — Agent social layer ([PR #554](https://github.com/eooo-io/orkestr/pull/554))

- `agents.owner_user_id` (backfilled from creator), `reputation_score`, `reputation_last_computed_at`
- **`AgentReputationService`** formula: baseline 50 + success-rate − halt-rate − failure-rate + review signal. Scheduled nightly at 03:00
- **`/agents/:id/profile`** page with owner, reputation, specialization, recent runs
- **`POST /api/agents/route`** — "Who should I ask about X?" routing via skill + past-run overlap
- **`/agents/directory`** page with routing search
- `execution_runs.visibility` (`private`/`team`/`org`) + `forked_from_run_id` for the **work feed** at `/work-feed` with **fork** action
- **`project_role_assignments`** (IC / DRI / coach) + `RoleMap` component in project Team tab

### Phase 6 — Compound learning ([PR #555](https://github.com/eooo-io/orkestr/pull/555))

- **`CapabilityDiscoveryService`** — ranks unused MCP servers, peer skills, library starters. Per-user dismissals with expiry via `capability_suggestion_dismissals`
- **`PatternExtractionService`** + **`ExtractMemoryPatternsJob`** (daily 03:30) — scans `agent_memories` for recurring feedback, opens `skill_update_proposals` when canonical text repeats ≥3 times in 30 days
- **`SkillPropagationService`** + **`SuggestSkillPropagationsJob`** (daily 04:00) — cross-project suggestions for high-performing skills with model-family compatibility gating
- New SPA pages: **`/skill-proposals`** (inbox) and **`/skill-propagations`** (org-wide)
- Endpoints for accept/reject/dismiss, `/api/skills/{id}/lineage` for provenance

## v1.0.0

### Infrastructure

- Docker Compose environment with PHP 8.4, Nginx, MariaDB 11, and Node.js containers
- Makefile with targets for build, up, down, migrate, test, and shell access
- Environment configuration with `.env.example`

### Database and Models

- Migrations for projects, project_providers, skills, skill_versions, tags, skill_tag, library_skills, app_settings, agents, project_agent, agent_skill, marketplace_skills, webhooks, webhook_deliveries, and skill_variables
- Eloquent models with UUID primary keys, JSON casts, auto-generated slugs, and relationship methods

### File I/O and Manifest Engine

- `SkillFileParser` for reading/writing YAML frontmatter + Markdown body files
- `ManifestService` for scanning, scaffolding, and managing `.orkestr/` directories
- `ProjectScanJob` for upserting skills from disk into the database

### Provider Sync

- 6 provider drivers: Claude, Cursor, GitHub Copilot, Windsurf, Cline, OpenAI
- `ProviderSyncService` orchestrating all enabled drivers per project
- Dry-run mode with `generate()` for diff preview
- Resolved includes and template variables in sync output
- Composed agent output included in provider files

### Admin UI (React SPA)

- Project management with provider checkboxes and Scan/Sync actions
- Library skill management with category filter and tag management
- Tag management with color picker and skill count
- Settings page for API keys, default model, and provider reference
- Dashboard with stats overview

### Skills API

- Full CRUD with file I/O (create, read, update, delete, duplicate)
- Auto-versioning on every save
- Lint endpoint with 8 prompt quality rules
- AI-assisted skill generation via Claude
- Bulk operations: tag, assign, delete, move

### React SPA

- Project list with card grid and sync buttons
- Project detail with skill grid, scan/sync/add, grid/list toggle
- Skill Editor with Monaco editor, frontmatter form, action bar
- Unsaved changes guard with beforeunload and navigation prompt
- Loading skeletons and empty states throughout

### Skill Composition

- `includes` system for composable prompts with recursive resolution
- Circular dependency detection and max depth of 5
- `template_variables` with `{{variable}}` placeholders and per-project values
- Template resolution at compose/sync time

### Prompt Linting

- 8 lint rules: vague instructions, weak constraints, conflicting directives, missing output format, excessive length, role confusion, missing examples, redundancy
- Lint tab in Skill Editor with color-coded issue cards

### Version History

- Auto-versioning on every skill save
- Version list with timestamps
- Monaco Diff Editor for comparing any two versions
- One-click version restore

### Agent System

- 9 pre-built agents: Orchestrator, PM, Architect, QA, Design, Code Review, Infrastructure, CI/CD, Security
- Per-project enable/disable, custom instructions, and skill assignment
- Agent Compose merging base + custom + skill bodies
- Token budget preview with progress bar

### Testing

- Multi-model test runner with SSE streaming (Anthropic, OpenAI, Gemini, Ollama)
- Playground with multi-turn conversation, model selection, and system prompt picker
- Per-turn stats: elapsed time, input/output tokens
- Stop mid-stream and copy result

### Token Estimation

- Live token count in the frontmatter form
- Model-specific context limits with color-coded warnings (75%/90% thresholds)
- Token estimates on skill cards and in agent compose preview

### Organization and Discovery

- Command palette with fuzzy search across skills, projects, pages, and actions
- Cross-project FULLTEXT search with tag, project, and model filters
- Color-coded tags with global management

### Library

- 25 pre-seeded library skills across 6 categories
- Library import with slug collision handling
- Category browsing and full-text search

### Sharing

- Bundle export as ZIP or JSON (skills + agents + metadata)
- Bundle import with conflict resolution: skip, overwrite, rename

### Integrations

- Outbound webhooks for skill.created, skill.updated, skill.deleted, project.synced
- HMAC-SHA256 payload signing
- Delivery logs and test endpoint
- GitHub inbound webhook for push-triggered project scans

### Git Integration

- Per-project git_auto_commit setting
- Auto-commit skill files after save
- Git log and diff API endpoints

### Skills.sh Import

- Discover skills in any GitHub repository via the GitHub API
- Preview skill content before importing (batch up to 30)
- Import to library or directly to a project
- Category auto-derivation from repository path structure
