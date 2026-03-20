# Deep Dives

This section provides technical deep dives into the architecture and internals of each Orkestr subsystem. These are for developers, platform engineers, and architects who want to understand **how** things work under the hood — not just what they do.

::: tip Prerequisites
Read [Orkestr 101](/101/) first if you're not familiar with the core concepts. These deep dives assume you understand what skills, agents, workflows, MCP, A2A, and guardrails are.
:::

## Architecture Deep Dives

| Page | Topic | What You'll Learn |
|---|---|---|
| [Agent Loop Architecture](./agent-loop-architecture) | The execution engine | How the Goal → Perceive → Reason → Act → Observe loop is implemented |
| [MCP Integration](./mcp-integration) | Tool connectivity | Transports, lifecycle management, tool discovery, and security |
| [A2A Protocol](./a2a-protocol) | Agent-to-agent communication | Task delegation, agent cards, budget cascading, delegation chains |
| [Workflow DAG Engine](./workflow-engine) | Multi-agent orchestration | DAG traversal, condition evaluation, parallel execution, context bus |
| [Canvas Architecture](./canvas-architecture) | Visual composition surface | React Flow integration, node types, connection drawing, persistence |
| [Guardrail System](./guardrail-system) | Multi-layer safety | Guard types, policy cascading, profile system, violation tracking |
| [Multi-Model Routing](./multi-model-routing) | Model management | Provider factory, fallback chains, cost optimization, health monitoring |
| [Provider Sync Engine](./provider-sync-engine) | Config generation | Driver architecture, format translation, diff preview, includes resolution |
| [Skill Composition](./skill-composition) | Skill assembly | Includes resolution, template variables, circular detection, max depth |
| [Data Architecture](./data-architecture) | Database and storage | Schema design, relationships, JSON columns, file storage, versioning |
