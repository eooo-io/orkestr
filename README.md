<div align="center">

```
 ██████╗ ██████╗ ██╗  ██╗███████╗███████╗████████╗██████╗
██╔═══██╗██╔══██╗██║ ██╔╝██╔════╝██╔════╝╚══██╔══╝██╔══██╗
██║   ██║██████╔╝█████╔╝ █████╗  ███████╗   ██║   ██████╔╝
██║   ██║██╔══██╗██╔═██╗ ██╔══╝  ╚════██║   ██║   ██╔══██╗
╚██████╔╝██║  ██║██║  ██╗███████╗███████║   ██║   ██║  ██║
 ╚═════╝ ╚═╝  ╚═╝╚═╝  ╚═╝╚══════╝╚══════╝   ╚═╝   ╚═╝  ╚═╝
```

**Provider-agnostic, deployment-flexible agent runtime.**
**Compose, orchestrate, and operate multi-agent systems on your own infrastructure.**

[![MIT License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

</div>

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.4-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.4">
  <img src="https://img.shields.io/badge/Laravel-12.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel 12">
  <img src="https://img.shields.io/badge/React-19-61DAFB?style=for-the-badge&logo=react&logoColor=black" alt="React">
  <img src="https://img.shields.io/badge/TypeScript-5.x-3178C6?style=for-the-badge&logo=typescript&logoColor=white" alt="TypeScript">
  <img src="https://img.shields.io/badge/Tailwind_CSS-4.x-06B6D4?style=for-the-badge&logo=tailwindcss&logoColor=white" alt="Tailwind CSS 4">
  <img src="https://img.shields.io/badge/Docker-Compose-2496ED?style=for-the-badge&logo=docker&logoColor=white" alt="Docker">
</p>

---

## What is Orkestr?

Orkestr is a self-hosted agent runtime for designing AI agents, wiring them into multi-agent workflows, and executing them with real tool calls — all from a visual UI. No Python framework required.

It connects to any LLM provider (Anthropic, OpenAI, Gemini, Grok, OpenRouter, Ollama) and any tool server via MCP and A2A protocols. Your models, your data, your infrastructure.

### Core ideas

- **Agent-first** — Agents are complete loop definitions: Goal → Perceive → Reason → Act → Observe
- **Provider-agnostic** — Mix cloud and local models in the same project. Go fully air-gapped with Ollama
- **Visual orchestration** — DAG workflow builder with conditional branching, parallel execution, and human-in-the-loop checkpoints
- **Real execution** — Not just config. Agents run live with real MCP tool calls, cost tracking, and safety guardrails
- **Skill system** — Reusable prompt+config modules composed into agent instructions. Recursive includes, template variables, version history
- **Sovereign by default** — Self-hosted, air-gap capable, no SaaS dependency. You control the security perimeter

---

## Features

### Agent Design & Execution
- **Agent Builder** — Visual form-based agent configuration (identity, goals, reasoning, tools, autonomy)
- **18 Pre-built Agents** — Semi-autonomous org archetypes with personas and specializations
- **Execution Engine** — Run agent loops with real MCP tool calls, memory persistence, and execution traces
- **Multi-turn Playground** — Interactive chat with any agent, any model, streaming responses

### Orchestration
- **Workflow Builder** — Drag-and-drop DAG editor with React Flow
- **Step Types** — Start, end, agent, checkpoint, condition, parallel split/join
- **Delegation Chains** — Agent-to-agent handoffs with shared context via A2A protocol
- **Export** — LangGraph YAML, CrewAI config, generic JSON

### Multi-Model Support
- **7 LLM Providers** — Anthropic, OpenAI, Gemini, Grok (xAI), OpenRouter (200+ models), custom OpenAI-compatible, Ollama
- **Per-agent model assignment** — Route complex tasks to Claude, routine work to local Llama
- **Fallback chains** — Automatic failover with health monitoring and cost-optimized routing
- **Air-gap mode** — Zero external network calls, all local inference

### Skill Management
- **Monaco Editor** — Full code editor with YAML frontmatter + Markdown
- **Version History** — Every save creates a snapshot with diff viewer and one-click restore
- **Skill Composition** — `includes` for recursive prompt composition with circular dependency detection
- **Template Variables** — `{{variable}}` placeholders resolved at compose/sync time
- **Prompt Linter** — 8 quality rules for prompt analysis
- **AI Generation** — Describe what you want, get a complete skill

### Safety & Guardrails
- **Budget limits** — Per-run, per-agent, and daily token/cost budgets with enforcement
- **Tool allowlists** — Control which MCP tools each agent can call
- **Output guards** — PII detection, credential redaction, content filtering
- **Approval gates** — Human-in-the-loop for sensitive tool calls (supervised / semi-autonomous / autonomous)
- **Guardrail profiles** — Strict, moderate, permissive presets cascading from org → project → agent

### Organization & Collaboration
- **Multi-tenant** — Organizations with role-based access (owner, admin, editor, viewer)
- **Bundle export/import** — ZIP/JSON with conflict resolution
- **Skill marketplace** — Community skill sharing with ratings
- **Webhook system** — Outbound HMAC-signed webhooks + GitHub push receiver
- **Command palette** — Cmd+K fuzzy search across everything

### Observability
- **Execution traces** — Full log of every step, tool call, and LLM response
- **Execution replay** — Step-through with timeline scrubber and auto-advance
- **Cost analytics** — Per-agent, per-run token usage and cost tracking
- **Performance dashboard** — Success rates, latency, model comparison

---

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                     React SPA (Vite + TypeScript)            │
│  Agent Builder · Workflow Builder · Playground · Canvas       │
│  Skill Editor · Marketplace · Search · Settings              │
└──────────────────────────┬──────────────────────────────────┘
                           │ REST API + SSE
┌──────────────────────────┴──────────────────────────────────┐
│                    Laravel 12 Backend (PHP 8.4)               │
│  Agent Execution Engine · Workflow Runner · MCP Client        │
│  Guardrails · Memory · Schedules · Audit                     │
├───────────────────┬──────────────┬──────────────────────────┤
│  Multi-Model LLM  │  MCP + A2A   │  Orchestration Engine    │
│  Anthropic·OpenAI │  Tool Calls  │  DAG Execution           │
│  Gemini·Grok·     │  stdio / SSE │  Checkpoints             │
│  OpenRouter·Ollama│              │  Delegation              │
└────────┬──────────┴──────┬───────┴───────────┬──────────────┘
         │                 │                   │
    MariaDB 11       .agentis/ files      MCP Servers
```

---

## Quick Start

### With Docker (recommended)

```bash
git clone https://github.com/eooo-io/agentis-studio.git
cd agentis-studio

cp .env.example .env
# Edit .env — add your API keys (ANTHROPIC_API_KEY, etc.)

make build
make up
make migrate

# Start the React SPA
cd ui && npm install && npm run dev
```

### Without Docker

```bash
composer install
cp .env.example .env
php artisan key:generate

# Set DB_HOST=127.0.0.1 and DB credentials in .env
php artisan migrate --seed

cd ui && npm install && cd ..
composer dev   # starts server, queue, pail, vite
```

### Access Points

| Interface | URL |
|---|---|
| React SPA | http://localhost:5173 |
| Filament Admin | http://localhost:8000/admin |
| API | http://localhost:8000/api |
| Documentation | http://localhost:5174 |

Default login: `admin@admin.com` / `password`

---

## Tech Stack

| Layer | Technology |
|---|---|
| Runtime | PHP 8.4 |
| Framework | Laravel 12.x |
| Admin UI | Filament 3.x + Livewire 4.x |
| Frontend SPA | React 19 + Vite + TypeScript |
| Styling | Tailwind CSS v4 + shadcn/ui |
| Code Editor | Monaco Editor |
| Canvas | React Flow (@xyflow/react) |
| State Management | Zustand |
| Database | MariaDB 11.x |
| LLM Providers | Anthropic, OpenAI, Gemini, Grok, OpenRouter, Ollama |
| Container | Docker + Docker Compose |
| Testing | Pest PHP + Playwright |

---

## Development Commands

```bash
# Docker
make up          # docker compose up -d
make down        # docker compose down
make build       # docker compose build --no-cache
make migrate     # php artisan migrate --seed
make fresh       # php artisan migrate:fresh --seed
make test        # php artisan test
make shell       # bash into php container
make logs        # docker compose logs -f

# Local dev
composer dev     # runs server, queue, pail, vite concurrently
composer test    # clears config + runs tests

# Type checking
cd ui && npx tsc --noEmit

# Documentation
cd docs && npm run dev    # VitePress dev server
cd docs && npm run build  # Build static site
```

---

## Project Structure

```
orkestr/
├── app/
│   ├── Http/Controllers/   # API controllers (consumed by React SPA)
│   ├── Models/             # Eloquent models
│   ├── Services/           # Business logic
│   │   ├── Execution/      # Agent & workflow execution engine
│   │   ├── LLM/            # Multi-model provider layer (7 providers)
│   │   ├── Guards/          # Budget, tool, approval, output guards
│   │   ├── Mcp/            # MCP protocol client
│   │   └── A2a/            # Agent-to-agent delegation
│   └── Jobs/
├── database/migrations/    # 59 migrations
├── routes/api.php          # REST API
├── ui/                     # React + Vite + TypeScript SPA
│   └── src/
│       ├── pages/          # 30+ pages
│       ├── components/     # Reusable UI components
│       ├── store/          # Zustand store
│       └── api/            # Axios client
├── docs/                   # VitePress documentation site
├── docker-compose.yml
└── Makefile
```

---

## Documentation

Full documentation available at the [Orkestr docs site](https://eooo-io.github.io/agentis-studio/):

- **[101](https://eooo-io.github.io/agentis-studio/101/)** — What Orkestr is and how it works
- **[Guide](https://eooo-io.github.io/agentis-studio/guide/getting-started)** — Setup, configuration, and usage
- **[Deep Dives](https://eooo-io.github.io/agentis-studio/deep-dive/)** — Architecture internals and design philosophy
- **[Cookbook](https://eooo-io.github.io/agentis-studio/cookbook/)** — Practical recipes and patterns
- **[Reference](https://eooo-io.github.io/agentis-studio/reference/skill-format)** — API, formats, and settings

---

## Contributing

Contributions are welcome. Please open an issue first to discuss what you'd like to change.

---

## License

[MIT](LICENSE)
