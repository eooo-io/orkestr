# What are Tools & MCP?

## The One-Sentence Answer

Tools are the abilities agents use to interact with the real world, and MCP (Model Context Protocol) is the standard protocol that connects agents to those tools.

## The Analogy: Hands and Instruments

An AI model without tools is like a brain in a jar — incredibly smart, but it can't *do* anything. It can only think and talk. Give it tools, and now it can:

- Read files from your codebase
- Query a database
- Call an API
- Run a shell command
- Search the internet
- Write to a document

Tools are the bridge between "thinking" and "doing."

## What is MCP?

MCP stands for **Model Context Protocol**. It's an open standard (created by Anthropic) that defines how AI agents connect to external tools and data sources.

Think of MCP like USB:

| USB | MCP |
|---|---|
| A standard port that any device can plug into | A standard protocol that any tool server can plug into |
| Keyboard, mouse, camera — all use the same port | File system, database, API — all use the same protocol |
| Your computer doesn't care what brand the device is | Your agent doesn't care how the tool is implemented |

Before MCP, every AI framework had its own way of defining tools. MCP standardizes this so a tool server written for one agent framework works with any other.

## How MCP Works in Orkestr

```
┌─────────────┐     MCP Protocol     ┌─────────────────┐
│   Agent      │◄────────────────────►│  MCP Server      │
│              │  1. List tools       │  (e.g., filesystem) │
│  "I need to  │  2. Call a tool      │                   │
│   read a     │  3. Get result       │  - readFile()     │
│   file"      │                      │  - writeFile()    │
│              │                      │  - listDir()      │
└─────────────┘                      └─────────────────┘
```

1. **Discovery** — The agent asks the MCP server: "What tools do you have?"
2. **The server responds** — "I have `readFile`, `writeFile`, and `listDir`"
3. **The agent calls a tool** — "Please `readFile` at path `/src/auth.ts`"
4. **The server executes and returns** — Here are the file contents

## MCP Transports

MCP servers can connect to Orkestr via two transport methods:

### Stdio (Standard I/O)

The MCP server runs as a local process. Orkestr launches it and communicates via stdin/stdout.

```
Orkestr ──stdin──► MCP Server Process ──stdout──► Orkestr
```

Best for: Local tools, file system access, command execution. Fast and secure because everything stays on the same machine.

### SSE (Server-Sent Events)

The MCP server runs as an HTTP service. Orkestr connects to it over the network.

```
Orkestr ──HTTP──► MCP Server (remote or local) ──SSE──► Orkestr
```

Best for: Remote services, shared tool servers, cloud APIs. Works across machines and networks.

## Types of MCP Servers

MCP servers can provide almost any capability:

| MCP Server | What It Provides | Example Use |
|---|---|---|
| **Filesystem** | Read/write files, list directories | Agent reads source code |
| **Database** | Query and modify databases | Agent checks schema, runs queries |
| **GitHub** | PR management, issues, code search | Agent reviews PRs, creates issues |
| **Shell** | Execute commands | Agent runs tests, builds projects |
| **Web Scraper** | Fetch and parse web pages | Agent researches documentation |
| **Slack** | Send/read messages | Agent posts status updates |
| **Jira** | Issue management | Agent creates and updates tickets |
| **Custom** | Anything you build | Your internal APIs and tools |

## Adding MCP Servers in Orkestr

You register MCP servers in Orkestr and then bind them to specific agents:

### Step 1: Register the Server

In the Canvas or Settings, add a new MCP server:

```
Name: Project Filesystem
Transport: stdio
Command: npx -y @anthropic/mcp-filesystem /path/to/project
```

### Step 2: Bind to an Agent

On the Canvas, drag a connection from an agent node to the MCP server node. Or in the agent configuration, add it to the agent's tool bindings.

### Step 3: The Agent Can Now Use It

When the agent executes, it can call any tool provided by that MCP server. Orkestr handles the connection lifecycle — starting the server, sending requests, and parsing responses.

## Tool Calls in the Agent Loop

Here's how tools fit into the agent's execution:

```
Reason: "I need to check the auth.ts file for SQL injection"
    │
    ▼
Act: tool_call → filesystem.readFile({ path: "src/auth.ts" })
    │
    ▼
MCP Server: reads the file, returns contents
    │
    ▼
Observe: "Found SQL concatenation on line 15. This is a vulnerability."
    │
    ▼
Reason: "I should also check if there's input validation upstream"
    │
    ▼
Act: tool_call → filesystem.readFile({ path: "src/middleware/validate.ts" })
    ...
```

Each tool call is recorded in the execution trace with:
- Tool name and server
- Input parameters
- Output result
- Latency
- Whether it required human approval (based on autonomy level)

## Tool Guardrails

Not all tools should be available to all agents, and not all tool calls should proceed without review:

### Allowlists and Blocklists

Organization-level policies can restrict which tools agents can use:

```
Allowed tools: filesystem.readFile, github.createComment
Blocked tools: filesystem.deleteFile, shell.execute
```

### Approval Gates

Based on the agent's autonomy level, certain tool calls pause for human approval:

| Autonomy | What Needs Approval |
|---|---|
| Autonomous | Nothing — agent acts freely |
| Supervised | Expensive calls (>$0.10) and destructive operations |
| Manual | Every tool call |

### Dangerous Input Detection

Orkestr scans tool call inputs for suspicious patterns — command injection, path traversal, etc. — and blocks or flags them.

## MCP vs. Custom Tools

Orkestr supports two ways to give agents tools:

| Approach | When to Use |
|---|---|
| **MCP Servers** | Standard tools that follow the MCP protocol. Reusable, shareable, ecosystem-compatible. |
| **Custom Tools** | Tool definitions embedded in the agent config (JSON Schema). Simpler but not reusable across agents. |

Most teams use MCP servers because they're portable and can be shared across agents and projects.

## Key Takeaway

Tools are what make agents *agents* instead of chatbots. MCP is the standardized protocol that connects agents to tools. Orkestr manages the full lifecycle — discovery, connection, execution, guardrails, and observability — so your agents can safely interact with the real world.

---

**Next:** [What is A2A?](./what-is-a2a) →
