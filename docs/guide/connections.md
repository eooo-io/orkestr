# Connections

The Connections tab in the project detail view provides a unified interface for managing everything your agents can talk to -- MCP servers for tools, A2A agents for delegation, and OpenClaw for shared configuration.

All three sections are collapsible accordions. Expand the one you need, configure it, and collapse it to keep the view clean.

## MCP Servers

[Model Context Protocol](https://modelcontextprotocol.io/) (MCP) is how agents access external tools. An MCP server exposes a set of tools (functions) that agents can call during execution -- file operations, database queries, API calls, or any custom capability you build.

### Adding an MCP Server

Navigate to your project's **Connections** tab and expand the **MCP Servers** section. Click **Add MCP Server** and fill in:

| Field | Description |
|---|---|
| **Name** | Display name (e.g., "Filesystem Tools") |
| **Transport** | `stdio` or `sse` |
| **Command** | For stdio: the command to start the server (e.g., `npx @modelcontextprotocol/server-filesystem /path`) |
| **URL** | For SSE: the server's SSE endpoint URL |
| **Environment** | Optional environment variables passed to the server process |

```
POST /api/projects/{id}/mcp-servers
```

```json
{
  "name": "Filesystem Tools",
  "transport": "stdio",
  "command": "npx @modelcontextprotocol/server-filesystem /home/user/projects",
  "env": {
    "NODE_ENV": "production"
  }
}
```

### Transport Types

**stdio** -- Orkestr spawns the MCP server as a child process and communicates over stdin/stdout. Best for local tools and self-hosted deployments where the server runs on the same machine.

**SSE** -- Orkestr connects to a remote MCP server over HTTP using Server-Sent Events. Best for shared infrastructure, cloud-hosted tool servers, or MCP servers running on a different machine.

::: tip
Most community MCP servers use stdio transport. If you are building your own tool server for a team, SSE transport lets multiple Orkestr instances share a single server.
:::

### Managing Servers

Each MCP server card shows its name, transport type, and the tools it exposes. You can:

- **Edit** -- update name, command, URL, or environment variables
- **Delete** -- remove the server from the project
- **View tools** -- see the list of tools the server provides

```
PUT /api/mcp-servers/{id}
DELETE /api/mcp-servers/{id}
```

### What Agents Can Access

When an agent runs, it has access to tools from all MCP servers configured in its project. The agent's `tools` field in its configuration can further restrict which tools it is allowed to call.

::: warning
MCP servers execute real actions on your infrastructure. Use guardrail policies and agent autonomy levels to control which agents can call which tools. See [Guardrails](./guardrails) for details.
:::

## A2A Agents

The [Agent-to-Agent protocol](https://google.github.io/A2A/) (A2A) enables agents to delegate tasks to other agents -- either within the same Orkestr instance or on remote servers.

### Adding an A2A Agent

Expand the **A2A Agents** section and click **Add A2A Agent**:

| Field | Description |
|---|---|
| **Name** | Display name for the remote agent |
| **URL** | The A2A endpoint URL |
| **Protocol** | Protocol version (default: `a2a/1.0`) |
| **Description** | What this agent does (shown to delegating agents) |
| **Capabilities** | List of capabilities this agent advertises |
| **Auth Header** | Optional authentication header for the remote endpoint |

```
POST /api/projects/{id}/a2a-agents
```

```json
{
  "name": "Research Agent",
  "url": "https://agents.internal.company.com/research",
  "protocol": "a2a/1.0",
  "description": "Performs deep research on topics and returns structured summaries",
  "capabilities": ["research", "summarization"],
  "auth_header": "Bearer sk-internal-..."
}
```

### How Delegation Works

When an agent in Orkestr has `can_delegate` enabled and delegation rules configured, it can hand off sub-tasks to A2A agents. The delegating agent:

1. Selects a target agent based on the task and the target's advertised capabilities
2. Sends the task payload to the target's A2A endpoint
3. Receives the result and incorporates it into its own execution

A2A agents appear as delegation targets in the Agent Designer's delegation rules configuration.

### Managing A2A Agents

```
PUT /api/a2a-agents/{id}     # Update configuration
DELETE /api/a2a-agents/{id}   # Remove from project
```

::: tip
A2A is protocol-agnostic -- the remote agent does not need to be running Orkestr. Any server that implements the A2A protocol can be a delegation target.
:::

## OpenClaw

OpenClaw is a shared agent configuration format that standardizes how agent definitions are exchanged between tools and platforms. The OpenClaw section lets you view and edit your project's OpenClaw configuration.

### Viewing and Editing

```
GET /api/projects/{id}/openclaw    # Get current config
PUT /api/projects/{id}/openclaw    # Update config
```

The OpenClaw configuration defines project-level agent settings in a portable format. This is useful when you want to share agent configurations across multiple platforms or export them for use outside Orkestr.

### What OpenClaw Includes

The configuration covers:

- Agent definitions and their relationships
- Skill assignments per agent
- MCP server references
- Delegation chains between agents
- Shared context and variable definitions

::: tip
Think of OpenClaw as the `.agentis/` directory for agent orchestration -- a single source of truth for how your agents are configured, portable across tools.
:::
