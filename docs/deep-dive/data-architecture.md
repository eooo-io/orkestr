# Data Architecture

This deep dive covers the database schema, storage patterns, and data flow of the Orkestr platform.

## Database: MariaDB 11.x

All persistent data lives in MariaDB. The schema uses:
- UUIDs for primary keys (where noted)
- JSON columns for flexible structured data
- Foreign key constraints for referential integrity
- FULLTEXT indexes for search

## Core Tables

### Projects

```sql
projects
├── id (bigint, auto-increment)
├── name: varchar
├── slug: varchar (unique)
├── path: varchar — filesystem path to project directory
├── description: text (nullable)
├── settings: json — git_auto_commit, default model, etc.
├── organization_id → organizations
└── timestamps
```

### Skills

```sql
skills
├── id (bigint, auto-increment)
├── project_id → projects
├── name: varchar
├── slug: varchar (unique within project)
├── description: text (nullable)
├── body: longtext — Markdown prompt content
├── model: varchar (nullable) — target model
├── max_tokens: integer (nullable)
├── tags: json — array of tag strings
├── tools: json — tool definitions
├── includes: json — array of skill slugs
├── template_variables: json — variable definitions
├── parent_skill_id → skills (nullable) — inheritance
├── organization_id → organizations
└── timestamps + FULLTEXT(name, description, body)
```

### Skill Versions

```sql
skill_versions
├── id (bigint, auto-increment)
├── skill_id → skills
├── version_number: integer
├── name, description, body: snapshot fields
├── frontmatter: json — complete frontmatter snapshot
├── change_summary: text (nullable)
└── created_at
```

### Agents

```sql
agents
├── id (bigint, auto-increment)
├── name: varchar
├── slug: varchar (unique)
├── role: varchar
├── icon: varchar
├── description: text
├── base_instructions: longtext
├── model: varchar
├── persona_prompt: text
├── objective_template: text
├── success_criteria: json
├── max_iterations: integer (default: 20)
├── timeout_seconds: integer (default: 300)
├── input_schema: json
├── memory_sources: json
├── context_strategy: varchar
├── planning_mode: varchar (structured/reactive/hybrid)
├── temperature: decimal
├── system_prompt: longtext
├── eval_criteria: json
├── output_schema: json
├── loop_condition: varchar
├── autonomy_level: varchar (autonomous/supervised/manual)
├── can_delegate: boolean
├── delegation_rules: json
├── budget_per_run: decimal (nullable)
├── budget_daily: decimal (nullable)
└── timestamps
```

### Pivot Tables

```sql
project_agent
├── project_id → projects
├── agent_id → agents
├── is_enabled: boolean (default: false)
├── custom_instructions: longtext (nullable)
└── timestamps

agent_skill
├── agent_id → agents
├── skill_id → skills
├── project_id → projects
└── timestamps

agent_mcp_server
├── agent_id → agents
├── mcp_server_id → mcp_servers
├── project_id → projects

agent_a2a_agent
├── agent_id → agents
├── a2a_agent_id → a2a_agents
├── project_id → projects
```

## Execution Tables

### Execution Runs

```sql
execution_runs
├── id (UUID)
├── project_id → projects
├── agent_id → agents
├── workflow_step_id → workflow_steps (nullable)
├── parent_run_id → execution_runs (nullable) — delegation chain
├── status: enum (pending, running, completed, failed, paused, cancelled)
├── input: json
├── output: json
├── model_used: varchar
├── total_tokens: integer
├── prompt_tokens: integer
├── completion_tokens: integer
├── total_cost: decimal
├── iterations: integer
├── started_at, completed_at: timestamps
├── error_message: text (nullable)
└── timestamps
```

### Execution Steps

```sql
execution_steps
├── id (UUID)
├── execution_run_id → execution_runs
├── iteration: integer
├── type: enum (perceive, reason, act, observe)
├── input: json
├── output: json
├── tokens_used: integer
├── cost: decimal
├── tool_name: varchar (nullable)
├── tool_input: json (nullable)
├── tool_output: json (nullable)
├── duration_ms: integer
├── guard_results: json
└── created_at
```

## Workflow Tables

```sql
workflows
├── id (UUID)
├── project_id → projects
├── name, slug: varchar
├── trigger_type: enum (manual, cron, webhook, a2a)
├── trigger_config: json
├── entry_step_id → workflow_steps
├── status: enum (draft, active, archived)
├── context_schema: json
├── termination_policy: json
├── config: json
└── timestamps

workflow_steps
├── id (UUID)
├── workflow_id → workflows
├── agent_id → agents (nullable)
├── type: enum (agent, checkpoint, condition, parallel_split, parallel_join, start, end)
├── name: varchar
├── position_x, position_y: integer
├── config: json
├── sort_order: integer
└── timestamps

workflow_edges
├── id (UUID)
├── workflow_id → workflows
├── source_step_id → workflow_steps
├── target_step_id → workflow_steps
├── condition_expression: text (nullable)
├── label: varchar (nullable)
├── priority: integer (default: 0)
└── timestamps

workflow_versions
├── id (UUID)
├── workflow_id → workflows
├── version_number: integer
├── snapshot: json
├── note: text (nullable)
└── created_at
```

## Infrastructure Tables

### MCP Servers

```sql
mcp_servers
├── id (UUID)
├── project_id → projects
├── name: varchar
├── transport: enum (stdio, sse)
├── command: varchar (nullable) — stdio
├── args: json (nullable) — stdio
├── url: varchar (nullable) — sse
├── headers: json (nullable) — sse
├── env: json (nullable)
├── status: enum (active, inactive, pending_approval, rejected)
└── timestamps
```

### A2A Agents

```sql
a2a_agents
├── id (UUID)
├── project_id → projects
├── name: varchar
├── url: varchar
├── agent_card: json
├── protocol_version: varchar
├── status: enum (active, inactive, pending_approval, rejected)
├── auth_config: json (nullable)
└── timestamps
```

### Agent Memory

```sql
agent_memories
├── id (UUID)
├── agent_id → agents
├── project_id → projects (nullable)
├── organization_id → organizations (nullable)
├── type: enum (conversation, working, fact)
├── scope: enum (run, agent, project, organization)
├── content: text
├── metadata: json
├── relevance_score: decimal
├── execution_run_id → execution_runs (nullable)
└── timestamps
```

### Agent Schedules

```sql
agent_schedules
├── id (UUID)
├── agent_id → agents
├── project_id → projects
├── type: enum (cron, webhook)
├── expression: varchar (nullable) — cron expression
├── input: json — default input for triggered runs
├── enabled: boolean
├── last_run_at: timestamp (nullable)
├── next_run_at: timestamp (nullable)
└── timestamps
```

## Security & Organization Tables

```sql
organizations
├── id (bigint)
├── name, slug: varchar
├── settings: json
└── timestamps

organization_user (pivot)
├── organization_id → organizations
├── user_id → users
├── role: enum (owner, admin, editor, viewer, member)
└── timestamps

guardrail_policies — see Guardrail System deep dive
guardrail_profiles — see Guardrail System deep dive
guardrail_violations — see Guardrail System deep dive

content_policies
├── id, organization_id, name, type, rules (json), severity, enabled

sso_providers
├── id, organization_id, name, driver (saml/oidc), config (json), enabled
```

## File Storage

### `.orkestr/` Directory

Skills are stored as `.md` files on the local filesystem:

```
Read/write operations:
├── Disk: Laravel filesystem disk "projects"
├── Root: PROJECTS_HOST_PATH environment variable
├── Path: {project.path}/.orkestr/skills/{slug}.md
├── Format: YAML frontmatter + Markdown body
├── Parsing: SkillFileParser (symfony/yaml)
└── Syncing: bidirectional (DB ↔ filesystem)
```

### Provider Config Files

Generated by provider sync drivers:

```
Output paths (all relative to project.path):
├── .claude/CLAUDE.md
├── .cursor/rules/{slug}.mdc
├── .github/copilot-instructions.md
├── .windsurf/rules/{slug}.md
├── .clinerules
└── .openai/instructions.md
```

## JSON Column Patterns

Several columns use JSON for flexible structured data:

| Column | Structure | Example |
|---|---|---|
| `skills.tools` | Array of tool definitions | `[{"name":"search","inputSchema":{...}}]` |
| `skills.includes` | Array of slugs | `["coding-standards","security"]` |
| `skills.template_variables` | Array of variable defs | `[{"name":"lang","default":"English"}]` |
| `agents.success_criteria` | Array of strings | `["all files reviewed","findings documented"]` |
| `agents.delegation_rules` | Object with rules | `{"max_depth":3,"budget_share":0.4}` |
| `workflows.context_schema` | JSON Schema | `{"type":"object","properties":{...}}` |
| `execution_steps.guard_results` | Array of check results | `[{"guard":"budget","passed":true}]` |

## Search

Full-text search uses MariaDB's FULLTEXT indexing:

```sql
-- Skills search
SELECT * FROM skills
WHERE MATCH(name, description, body) AGAINST('security review' IN NATURAL LANGUAGE MODE)
AND project_id = ?
ORDER BY relevance DESC;

-- Cross-project search
GET /api/search?q=security&tags=owasp&project_id=5&model=claude
```

The search endpoint supports filtering by tags, project, and model in addition to text search.

## Versioning Strategy

Skills and workflows use a **snapshot** versioning strategy:

```
On every save:
1. Increment version_number
2. Store complete snapshot of current state
3. skill_versions.frontmatter = full YAML metadata as JSON
4. skill_versions.body = complete Markdown body

On restore:
1. Load snapshot from the target version
2. Overwrite current skill with snapshot data
3. Create a new version record (so restore is itself versioned)
```

This is a simple, reliable approach — no deltas, no patches, just complete snapshots.
