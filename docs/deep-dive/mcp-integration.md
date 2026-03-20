# MCP Integration

This deep dive covers how Orkestr connects to and manages MCP (Model Context Protocol) servers — the tool layer that gives agents the ability to interact with the real world.

## Architecture Overview

```
┌────────────────────────────────────────────────┐
│                  Orkestr                        │
│                                                 │
│  AgentExecutionService                          │
│       │                                         │
│       ▼                                         │
│  McpClientService                               │
│       │                                         │
│       ├──► McpStdioTransport ──► Process A      │
│       ├──► McpStdioTransport ──► Process B      │
│       └──► McpSseTransport  ──► HTTP Server C   │
│                                                 │
└────────────────────────────────────────────────┘
```

## MCP Protocol Messages

Orkestr implements the MCP protocol with these message types:

### Initialize

Establishes the connection and negotiates capabilities:

```json
→ { "method": "initialize", "params": { "protocolVersion": "2024-11-05", "capabilities": {} } }
← { "result": { "protocolVersion": "2024-11-05", "serverInfo": { "name": "filesystem", "version": "1.0" } } }
```

### List Tools

Discovers available tools from the server:

```json
→ { "method": "tools/list" }
← { "result": { "tools": [
      { "name": "readFile", "description": "Read a file", "inputSchema": { "type": "object", "properties": { "path": { "type": "string" } } } },
      { "name": "writeFile", "description": "Write a file", "inputSchema": { ... } }
   ] } }
```

### Call Tool

Invokes a specific tool:

```json
→ { "method": "tools/call", "params": { "name": "readFile", "arguments": { "path": "/src/auth.ts" } } }
← { "result": { "content": [{ "type": "text", "text": "// file contents..." }] } }
```

## Transport Layer

### Stdio Transport

For local MCP servers that run as child processes:

```
Lifecycle:
1. Spawn process: `npx -y @anthropic/mcp-filesystem /path/to/project`
2. Send messages via stdin (JSON-RPC, newline-delimited)
3. Read responses from stdout
4. Stderr captured for logging/debugging
5. Process terminated when execution ends

Advantages:
- No network overhead
- Inherits OS-level file permissions
- Process isolation per execution

Configuration:
  transport: stdio
  command: npx -y @anthropic/mcp-filesystem
  args: ["/path/to/project"]
  env: { "NODE_ENV": "production" }
```

### SSE Transport

For remote or shared MCP servers accessible via HTTP:

```
Lifecycle:
1. Open HTTP connection to server URL
2. Send requests via POST
3. Receive responses via Server-Sent Events stream
4. Connection kept alive for duration of execution
5. Closed when execution ends

Advantages:
- Access remote servers across the network
- Shared servers serve multiple agents
- Stateful connections for long-running tools

Configuration:
  transport: sse
  url: https://mcp-server.internal:3000
  headers: { "Authorization": "Bearer token" }
```

## McpClientService

The central service managing all MCP connections. Key responsibilities:

### Connection Pool

```
McpClientService
├── connections: Map<serverId, Transport>
├── connect(server): establish transport
├── disconnect(serverId): close transport
├── listTools(serverId): discover tools → cached
├── invokeTool(serverId, toolName, args): call tool
└── healthCheck(serverId): verify connection
```

Tool schemas are cached after initial discovery to avoid repeated list calls.

### Tool Discovery

When an agent binds to an MCP server, Orkestr discovers the available tools:

```
1. Connect to server
2. Send tools/list
3. Parse response: tool names, descriptions, input schemas
4. Convert to the format expected by the LLM provider:
   - Anthropic: tool_use format
   - OpenAI: function calling format
   - Others: provider-specific adaptation
5. Cache the tool list for this server
```

This discovery result is what gets passed to the LLM during the Reason stage — the model sees the available tools and can decide which to call.

### Tool Invocation

```
invokeTool(serverId, toolName, arguments):
  1. Validate arguments against tool's inputSchema (JSON Schema validation)
  2. Run through ToolGuard (allowlist/blocklist check)
  3. Run through BudgetGuard (cost check)
  4. Run through ApprovalGuard (autonomy level check)
  5. If all guards pass:
     a. Send tools/call message to MCP server
     b. Await response
     c. Parse result content
     d. Run through OutputGuard (PII/secret scan on result)
     e. Record in execution trace
     f. Return to agent loop
```

## Server Registration

MCP servers are registered per-project and bound to specific agents:

### Data Model

```
mcp_servers
├── id (UUID)
├── project_id → projects
├── name: "Project Filesystem"
├── transport: "stdio" | "sse"
├── command: "npx -y @anthropic/mcp-filesystem"  (stdio)
├── args: ["/path/to/project"]                    (stdio)
├── url: "https://server:3000"                     (sse)
├── headers: { "Authorization": "..." }            (sse)
├── env: { "KEY": "value" }
└── status: "active" | "inactive"

agent_mcp_server (pivot)
├── agent_id → agents
├── mcp_server_id → mcp_servers
└── project_id → projects
```

### Endpoint Approval

When an MCP server is first connected (or its URL/command changes), it goes through the **endpoint approval** flow:

1. Server is registered with status `pending_approval`
2. Admin receives a notification
3. Admin reviews the server details (URL, command, capabilities)
4. Admin approves or rejects
5. If approved: status changes to `active`, agents can use it
6. If rejected: status changes to `rejected`, connection refused

This prevents agents from connecting to unauthorized tool servers, especially important in multi-user environments.

## Air-Gap Enforcement

In air-gap mode, the MCP integration enforces:

1. **No remote URLs** — SSE transport servers must resolve to local/private IPs
2. **No internet-fetching commands** — Stdio commands are validated against a blocklist
3. **Network monitor** — Any unexpected outbound connection from an MCP server triggers an alert

## Error Handling

| Scenario | Behavior |
|---|---|
| Server fails to start (stdio) | Retry once, then mark as unavailable for this run |
| Connection lost mid-execution | Attempt reconnect, replay pending request |
| Tool returns error | Pass error to agent loop — agent can reason about it |
| Tool timeout | Return timeout error after configurable threshold |
| Invalid arguments | Return validation error before sending to server |

## Performance

| Operation | Typical Latency |
|---|---|
| Stdio process startup | 200-500ms |
| SSE connection establishment | 50-200ms |
| Tool discovery (list) | 10-50ms |
| Tool invocation (simple) | 10-100ms |
| Tool invocation (complex, e.g., database query) | 100ms-5s |
