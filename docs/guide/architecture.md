# Architecture

Orkestr is built as a three-layer architecture. Each layer builds on the one below, moving from individual building blocks to fully orchestrated agent teams.

## Three-Layer Architecture

```
+-----------------------------------------------+
|          Orchestration Layer                    |
|   Workflows, Agent Teams, Goal Decomposition   |
+-----------------------------------------------+
|            Agent Layer                          |
|   Agent Loop, Execution, Memory, Tools         |
+-----------------------------------------------+
|          Component Layer                        |
|   Skills, Providers, Models, Templates         |
+-----------------------------------------------+
```

### Component Layer

The foundation. Individual skills (prompt + config), provider sync drivers, model connections, and template resolution.

- **Skills** -- YAML frontmatter + Markdown body, stored in `.agentis/skills/`
- **Provider Sync** -- 6 drivers (Claude, Cursor, Copilot, Windsurf, Cline, OpenAI) that transform skills into provider-native config files
- **LLM Providers** -- Multi-model access via `LLMProviderFactory`, routing by model prefix to Anthropic, OpenAI, Gemini, Grok, or Ollama
- **Templates** -- `{{variable}}` substitution resolved at compose/sync time
- **Includes** -- recursive skill composition with circular dependency detection (max depth 5)

### Agent Layer

Individual agents that can perceive, reason, and act. Each agent has a role, assigned skills, and execution capabilities.

- **Agent Configuration** -- 9+ pre-built agent roles (code reviewer, architect, etc.) with per-project customization
- **Agent Compose** -- merges base instructions + custom instructions + assigned skills into a single deployable prompt
- **Execution Loop** -- goal-driven agent execution with tool use and observation
- **Memory** -- per-agent context that persists across turns

### Orchestration Layer

Multi-agent coordination. Workflows chain agents together, decompose goals, and manage execution across teams.

- **Workflow Builder** -- visual editor for multi-step agent pipelines
- **Goal Decomposition** -- breaks complex objectives into sub-tasks assigned to appropriate agents
- **Team Coordination** -- manages handoffs, shared context, and conflict resolution between agents
- **Execution Monitoring** -- real-time SSE streaming of workflow progress

## The Agent Loop

Every agent execution follows the same cycle:

```
         +-------+
         | Goal  |
         +---+---+
             |
     +-------v--------+
     |   Perceive      |  Read context, inputs, previous results
     +-------+---------+
             |
     +-------v--------+
     |   Reason        |  LLM call with system prompt + skills + context
     +-------+---------+
             |
     +-------v--------+
     |   Act           |  Execute tools, generate output
     +-------+---------+
             |
     +-------v--------+
     |   Observe       |  Evaluate results, update memory
     +-------+---------+
             |
      Goal met? -----> Done
             |
             No
             |
     +-------v--------+
     |   Loop          |  Back to Perceive with updated context
     +--+--------------+
```

The loop continues until the goal is satisfied, a maximum iteration count is reached, or the agent determines it cannot make further progress.

## Key Services

### LLMProviderFactory

Routes model requests to the correct provider based on model name prefix:

| Prefix | Provider |
|---|---|
| `claude-` | Anthropic (via `mozex/anthropic-laravel`) |
| `gpt-`, `o` | OpenAI |
| `gemini-` | Google Gemini |
| `grok-` | xAI Grok |
| (other) | Ollama / custom endpoints |

### AgentExecutionService

Manages the agent loop -- receives a goal, runs the perceive-reason-act-observe cycle, manages tool calls, and returns results. Streams progress via SSE.

### WorkflowRunner

Executes multi-agent workflows. Takes a workflow definition (nodes + edges), resolves execution order, runs agents in sequence or parallel, manages data flow between steps, and handles errors/retries.

### ProviderSyncService

Orchestrates writing skills and composed agent instructions to provider-specific config files. Each provider has a driver implementing `ProviderDriverInterface`:

| Provider | Output | Format |
|---|---|---|
| Claude | `.claude/CLAUDE.md` | All skills under H2 headings |
| Cursor | `.cursor/rules/{slug}.mdc` | One MDC file per skill |
| Copilot | `.github/copilot-instructions.md` | All skills concatenated |
| Windsurf | `.windsurf/rules/{slug}.md` | One file per skill |
| Cline | `.clinerules` | Single flat file |
| OpenAI | `.openai/instructions.md` | All skills concatenated |

## Self-Hosted Deployment Model

Orkestr runs entirely on your infrastructure:

```
+------------------+     +------------------+     +------------------+
|  Browser (SPA)   |---->|  Laravel API     |---->|  MariaDB         |
|  React + Vite    |     |  PHP 8.4         |     |  11.x            |
+------------------+     +--------+---------+     +------------------+
                                  |
                    +-------------+-------------+
                    |             |              |
              +-----v----+ +-----v----+  +------v-----+
              | Anthropic | | OpenAI   |  | Ollama     |
              | Gemini    | | Grok     |  | vLLM / TGI |
              +-----------+ +----------+  +------------+
```

- The React SPA communicates with the Laravel API via session-based authentication
- The API layer handles all LLM provider communication, skill management, and agent orchestration
- MariaDB stores projects, skills, agents, organizations, and configuration
- LLM providers can be cloud APIs, local Ollama, or custom endpoints on your network
- No data leaves your infrastructure unless you explicitly configure cloud provider API keys
