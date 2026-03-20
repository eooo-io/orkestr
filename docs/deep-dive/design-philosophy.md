# Design Philosophy

This document articulates the architectural thesis behind Orkestr — the irreducible kernel, the design constraints that shaped it, and the principles that every implementation decision must satisfy.

::: tip Who This Is For
This is not a feature walkthrough. It's the architectural manifesto. Read the [101](/101/) or [Architecture](/guide/architecture) pages first if you want to understand what Orkestr does. This page explains **why** it's built the way it is.
:::

## The Thesis

**The runtime should not dictate the intelligence topology.**

Model choice is per-agent. Execution environment is swappable. Orchestration is configuration-driven. Infrastructure can range from a laptop with Ollama to a fleet of cloud API keys. The system remains useful even when the internet, SaaS APIs, or vendor favor disappear.

Put differently: Orkestr is an **operating substrate for sovereign agent systems** — sovereign models, sovereign infrastructure, sovereign topology, sovereign data boundaries.

That claim is only meaningful if the architecture earns it.

## The Irreducible Kernel

Strip away the UI, the marketplace, the analytics dashboards, the convenience features. What remains — the thing without which nothing else functions — is the kernel:

```
┌─────────────────────────────────────────────────────┐
│                  Irreducible Kernel                  │
│                                                     │
│  1. Declarative Agent Graph                         │
│     Agent definitions as data, not code.            │
│     YAML + Markdown. Portable. Diffable. Composable.│
│                                                     │
│  2. Execution Engine                                │
│     The Perceive → Reason → Act → Observe loop.     │
│     Runs a single agent to completion.              │
│                                                     │
│  3. Capability Binding                              │
│     Skills (prompt modules), Tools (MCP), Agents    │
│     (A2A). Three types of capability, one binding   │
│     contract per type.                              │
│                                                     │
│  4. Model Abstraction                               │
│     LLMProviderInterface — four methods.            │
│     chat(), stream(), listModels(), healthCheck().   │
│     Everything else is a provider implementation.   │
│                                                     │
│  5. State Contract                                  │
│     Agent memory (conversation / working / long-    │
│     term). Execution traces. Version snapshots.     │
│     The system knows what happened and can replay   │
│     it.                                             │
│                                                     │
│  6. Control Hooks                                   │
│     Budget guards, tool guards, approval gates,     │
│     output guards. The system can halt, pause, or   │
│     constrain any agent at any point in the loop.   │
│                                                     │
└─────────────────────────────────────────────────────┘
```

Everything else — the workflow builder, the canvas, the marketplace, the provider sync, the analytics — is secondary. Important, but secondary. If the kernel is clean, those features grow naturally. If the kernel is muddled, they become ceremonial middleware.

### Why These Six?

Each kernel component exists because removing it breaks a fundamental promise:

| Component | What Breaks Without It |
|---|---|
| Declarative Agent Graph | Agents require code surgery to modify. No portability, no diffing, no config-driven orchestration. |
| Execution Engine | Agents can be defined but not run. The platform becomes a config editor. |
| Capability Binding | Agents have no skills, no tools, no delegation. They're prompt-in, text-out. |
| Model Abstraction | Agents are locked to one provider. No fallbacks, no local models, no sovereignty. |
| State Contract | No memory, no replay, no version history. Every execution is amnesic and unauditable. |
| Control Hooks | No budgets, no guardrails, no approval gates. Autonomous agents with no governance. |

## Five Architectural Principles

### 1. Provider Agnosticism Is a Contract, Not an Adapter Collection

The common approach to multi-provider support: write adapters. Wrap each vendor's API. Expose a lowest-common-denominator interface. Hope the abstractions don't leak.

Orkestr takes a different position. Provider agnosticism is a **contract** — a minimal interface that providers must satisfy, with no hidden assumptions about any single vendor's semantics.

```php
interface LLMProviderInterface
{
    public function chat(string $model, array $messages, array $options = []): array;
    public function stream(string $model, array $messages, array $options = []): Generator;
    public function listModels(): array;
    public function healthCheck(): array;
}
```

Four methods. That's the contract. Everything specific to Anthropic's Messages API, OpenAI's Chat Completions, Gemini's Generate Content, or Ollama's local API lives inside the provider implementation. The contract doesn't leak vendor semantics upward.

**What this enables:**

- **Agent-level model assignment.** Each agent chooses its own model. The Security Auditor can run on `claude-sonnet-4-6`. The Code Reviewer can run on `gpt-4o`. The Infrastructure Agent can run on a local `llama3.2` via Ollama. No system-wide model lock-in.

- **Fallback chains.** An agent specifies a primary model and ordered fallbacks. If Anthropic is down, try OpenAI. If OpenAI is down, fall back to local Ollama. The execution engine handles this transparently — the agent definition doesn't change.

- **Cost-optimized routing.** Different models for different complexity levels. Use a capable model for planning, a cheaper one for summarization. The abstraction makes this a configuration choice, not an architecture change.

- **Zero vendor lock-in.** Switch every agent from Claude to GPT by changing a string in the agent config. No code changes. No migration. The contract guarantees behavioral equivalence at the interface level.

**What this does NOT do:**

It doesn't pretend all models are identical. Tool calling semantics differ. Context windows differ. Pricing differs. The contract abstracts the **interaction pattern** (send messages, get responses, stream tokens), not the **capabilities**. Agent authors are expected to understand their model choices.

### 2. Deployment Elasticity Without Architectural Schizophrenia

"Run anywhere" is an easy promise and a hard architecture.

The naive approach: build for the cloud, then bolt on local support as an afterthought. Or build for local, then struggle with scale. Either way, the architecture splits — one code path for cloud, another for local, both fighting each other.

Orkestr avoids this by making the deployment topology a **runtime configuration**, not an architectural branch.

**The same system handles all four tiers:**

| Tier | Infrastructure | What Changes |
|---|---|---|
| Cloud-only | No GPU. Cloud API keys only. | Model config points to Anthropic/OpenAI/Gemini. Nothing local. |
| Hybrid light | CPU or Apple Silicon. Small local models. | Some agents use local Ollama. Others use cloud. Mixed fallback chains. |
| Hybrid heavy | Dedicated GPU(s). 30–70B models. | Most agents local. Cloud for peaks or specialized models. |
| Air-gapped | No internet. Everything local. | All models via Ollama/vLLM. Air-gap mode enabled. Zero external calls. |

**Why this works without the architecture fracturing:**

1. **Model routing is prefix-based.** `claude-sonnet-4-6` goes to Anthropic. `llama3.2` goes to Ollama. The routing happens at the factory level. The execution engine, the workflow runner, the memory system — none of them know or care where the model lives.

2. **No cloud-only dependencies in the kernel.** The execution engine doesn't call home. The workflow DAG resolver is pure computation. Memory is local MariaDB (or PostgreSQL+pgvector for embeddings). There is no SaaS dependency in the critical path.

3. **MCP servers are local-first.** The stdio transport spawns a local process. The SSE transport connects to a URL — which can be `localhost`. Remote MCP is supported but not assumed.

4. **Air-gap mode is a feature flag, not a fork.** Enable it and the system disables all external network calls. Models must be local. API key checks are skipped. The same codebase, same database, same UI — just with the network boundary clamped shut.

**The test:** Can you take a project configured for cloud APIs and, without changing the project structure, the workflow definitions, or the agent configurations (beyond model names), run it fully local? Yes. That's what deployment elasticity means.

### 3. Composition as a First-Class Primitive

Composition is not a feature in Orkestr. It's the **organizing principle** that shapes the entire design.

Composition operates at three levels:

**Level 1: Skill Composition**

Skills include other skills. The `includes` field in YAML frontmatter references other skill slugs. The `SkillCompositionService` resolves includes recursively (max depth 5) with circular dependency detection.

```yaml
---
includes: [base-instructions, security-rules, output-format]
---
```

This is not concatenation. It's directed composition — the resolution order matters, duplicates are eliminated, and the final output is a single coherent prompt assembled from reusable modules.

**Level 2: Agent Composition**

Each agent is composed from three layers:

```
Base Instructions (the agent role's persona — e.g., "You are a security auditor")
    ↓
+ Custom Instructions (project-specific additions — e.g., "Our stack uses Laravel + React")
    ↓
+ Assigned Skills (resolved skill bodies, includes expanded, templates substituted)
    ↓
= Final System Prompt (single deployable unit)
```

The `AgentComposeService` performs this merge. The result is a complete prompt that can be inspected, diffed, and deployed — without needing to understand the composition pipeline to use it.

**Level 3: Workflow Composition**

Workflows compose agents into execution graphs. Each workflow step wraps an agent (or a checkpoint, or a condition). The workflow DAG composes:

- Agent outputs → next agent inputs (context bus)
- Parallel branches → join synchronization
- Conditional edges → runtime evaluation
- Checkpoint pauses → human decision → resumed or aborted

**Why composition must be first-class:**

Because the alternative is monolithic prompts. And monolithic prompts don't scale — they can't be shared across projects, can't be tested independently, can't be versioned granularly, and can't be recombined for new use cases without copy-paste.

Making composition a primitive means every piece of the system — a skill, an agent, a workflow — is inherently built from smaller, reusable parts. This isn't just engineering hygiene. It's what makes the marketplace possible (share skills, not monoliths), what makes agent teams manageable (assign skills per agent, not one giant prompt per team), and what makes provider sync work (translate composed output into six formats).

### 4. Cheap-Hardware Realism as a Design Constraint

If your agent platform only works well on expensive hardware, you've built a toy for companies that can afford toys. That excludes most of the world.

Orkestr treats hardware constraints as a **design input**, not an afterthought.

**What this means concretely:**

- **The platform itself is lightweight.** PHP 8.4 + MariaDB + a React SPA. No Kubernetes required. No Redis required. No Elasticsearch. No message queue for basic operation. The Docker Compose stack runs on a 4-core, 8GB machine.

- **Model routing accounts for hardware.** Fallback chains aren't just for reliability — they're for cost. An agent can try a cloud model first and fall back to a smaller local model when the cloud is unavailable or too expensive. The system doesn't assume unlimited API budget.

- **Execution is synchronous-first, async-capable.** The agent loop runs in a single request cycle with SSE streaming. No background worker cluster required for basic execution. Laravel's queue system is available for scheduled agents and heavy workflows, but it's not required to get started.

- **Token estimation is character-based.** Not a transformer tokenizer. Not a vendor-specific BPE implementation. `~1 token per 4 characters`. This is intentionally approximate because the alternative (loading tokenizer models) adds memory overhead and vendor coupling for marginal accuracy gains.

- **Memory is relational-first.** Agent memory uses MariaDB by default. pgvector is optional — for teams that want semantic search on memories. But the system works perfectly well with exact-match lookups on a relational database. You don't need a vector database to run agents.

- **Air-gap mode assumes constrained resources.** When running fully local, the system uses Ollama — which can run 7B models on a MacBook Air with 8GB RAM. The architecture doesn't assume you have an A100. It assumes you might have a Mac Mini.

**The design test:** Can a solo developer, on a $600 laptop, with no cloud API keys, run Orkestr with local models and get meaningful agent execution? Yes. It won't be as fast or capable as a cloud-backed deployment, but it will work — same UI, same workflow builder, same execution traces. That's not a degraded experience; it's the same experience at a different performance tier.

### 5. Orchestration That Earns the Word

Orchestration means more than "run agent A, then agent B." If that's all you need, a bash script suffices.

Real orchestration requires:

**DAG-based execution, not linear chaining.**

Workflows in Orkestr are directed acyclic graphs. Steps can branch, run in parallel, rejoin, and branch again. The `WorkflowExecutionService` resolves the DAG, evaluates conditional edges at runtime, and manages the context bus that passes data between steps.

```
Start → [Security Scan] ──────────────────────→ [Report]
                         ├→ [Code Review] ──────→ [Report]
                         └→ [Architecture Review] → [Report]
```

Three agents running in parallel. Results joined at the report step. This is not sequential invocation with extra steps. It's concurrent execution with synchronization.

**Checkpoints for human-in-the-loop.**

Autonomous execution is powerful. Unchecked autonomous execution is dangerous. Checkpoints are workflow steps that pause execution and wait for human approval. The workflow resumes when approved, aborts when rejected.

This isn't just a safety feature. It's an orchestration primitive. A deployment pipeline might run automated tests in parallel, then checkpoint before production deployment. A code review workflow might checkpoint after the review agent flags issues, before the fix agent applies changes.

**Conditional routing based on runtime state.**

Workflow edges can carry conditions evaluated against the context bus. The DAG isn't static — it adapts based on what agents produce:

```
[Analyze] → (severity == "critical") → [Emergency Fix]
          → (severity == "low")      → [Backlog]
          → (else)                   → [Standard Review]
```

**A2A delegation for cross-boundary orchestration.**

Agents can delegate tasks to other agents — including remote agents on different Orkestr instances or any A2A-compatible endpoint. This isn't RPC. It's task delegation with context handoff, session support, and configurable return behaviors (report back, fire and forget, chain forward).

**Budget enforcement as an orchestration constraint.**

Budget guards aren't just cost controls. They're orchestration-level constraints that affect execution flow. When a budget is exhausted, the agent halts — not crashes, halts. The workflow can handle this as a condition and route to a cheaper model or a human fallback.

**The orchestration test:** Can you build a workflow where three agents run in parallel, their results are conditionally routed to different follow-up agents, a human checkpoint gates the critical path, and the whole thing runs within a budget constraint — without writing code? Yes. That's what the workflow builder does. Configuration-driven orchestration that manages real complexity, not demo-ware that sequences two agents and calls it a pipeline.

## What This Means in Practice

These five principles aren't aspirational. They're constraints that the implementation satisfies today:

| Principle | Implementation |
|---|---|
| Provider agnosticism | `LLMProviderInterface` — 4 methods, 7 providers, per-agent model assignment, fallback chains |
| Deployment elasticity | Same codebase runs cloud-only, hybrid, or air-gapped. Model routing is config, not architecture. |
| Composition as primitive | Skills compose into agents. Agents compose into workflows. Three levels, one pattern. |
| Cheap-hardware realism | Runs on 4-core/8GB. No mandatory infrastructure beyond PHP + MariaDB. Local models via Ollama. |
| Orchestration depth | DAG workflows with parallel execution, conditional routing, checkpoints, A2A delegation, budget constraints. |

The kernel is tight. The principles are enforced. Everything else is surface area built on top of a clean core.

## The Sovereignty Argument

Why does any of this matter?

Because the current generation of agent platforms assumes:

- You'll use their cloud
- You'll use their models
- You'll accept their data handling
- You'll depend on their uptime
- You'll pay their prices

Orkestr assumes the opposite:

- **Sovereign models.** Use any model from any provider, including models running on your own hardware.
- **Sovereign infrastructure.** Self-hosted. Your servers. Your network. Your security perimeter.
- **Sovereign topology.** Design agent teams, workflows, and delegation chains that fit your organization — not a platform's opinionated framework.
- **Sovereign data.** Nothing leaves your infrastructure unless you explicitly configure it to. Air-gap mode makes this a hard guarantee.
- **Sovereign deployment.** Move from cloud to local to hybrid without changing your agent definitions, your workflows, or your operational tooling.

This is the axis that matters. Not "which models do you support?" but "who controls the system?"

The answer should be: you do.
