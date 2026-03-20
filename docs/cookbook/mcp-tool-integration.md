# MCP Tool Integration

**Goal:** Connect MCP servers to your agents so they can read files, call APIs, and interact with external systems.

**Time:** 20 minutes

## Ingredients

- A running Orkestr instance with a project and agents
- Node.js installed (for running MCP servers)
- A codebase that agents should be able to read

## Steps

### 1. Add a Filesystem MCP Server

The filesystem MCP server gives agents the ability to read and write files.

On the Canvas, click **+** in the MCP Server palette and create:

- **Name:** "Project Filesystem"
- **Transport:** stdio
- **Command:** `npx`
- **Args:** `["-y", "@modelcontextprotocol/server-filesystem", "/path/to/your/project"]`

Save. Orkestr will start the MCP server process and discover its tools:
- `readFile` — Read a file's contents
- `writeFile` — Write content to a file
- `listDirectory` — List files in a directory
- `createDirectory` — Create a new directory

### 2. Bind the MCP Server to Agents

On the Canvas, draw a connection from your Security Agent → Project Filesystem MCP Server.

Now the Security Agent can read files from your project during execution.

Repeat for any other agents that need file access (Code Review, QA, etc.).

### 3. Add a GitHub MCP Server

If your code is on GitHub, add a GitHub MCP server:

- **Name:** "GitHub"
- **Transport:** stdio
- **Command:** `npx`
- **Args:** `["-y", "@modelcontextprotocol/server-github"]`
- **Environment:** `{ "GITHUB_TOKEN": "ghp_your_token_here" }`

This provides tools like:
- `searchCode` — Search across repositories
- `getFileContents` — Read specific files
- `createIssue` — Create GitHub issues
- `createPullRequestComment` — Comment on PRs

### 4. Add a Database MCP Server (Optional)

For agents that need database access:

- **Name:** "Database"
- **Transport:** stdio
- **Command:** `npx`
- **Args:** `["-y", "@modelcontextprotocol/server-postgres", "postgresql://user:pass@localhost:5432/mydb"]`

::: warning
Database MCP servers give agents direct database access. Use guardrails to restrict to read-only operations and specific tables.
:::

### 5. Configure Tool Guardrails

Go to your project's guardrail settings and set tool policies:

```
Allowed tools:
├── filesystem.readFile ✓
├── filesystem.listDirectory ✓
├── github.searchCode ✓
├── github.getFileContents ✓
├── github.createPullRequestComment ✓

Blocked tools:
├── filesystem.writeFile ✗ (read-only for now)
├── filesystem.deleteFile ✗
├── database.execute ✗ (use database.query instead)
```

### 6. Test the Integration

Open the Playground, select an agent with MCP tools bound, and ask it to do something that requires tool use:

```
Read the file at src/auth/login.ts and check for SQL injection vulnerabilities.
```

The agent should:
1. Call `filesystem.readFile` with the path
2. Receive the file contents
3. Analyze the code
4. Report any findings

You'll see the tool call in the execution trace.

### 7. Wire It All Up on the Canvas

Your canvas should now show the full picture:

```
┌──────────────┐     ┌──────────────┐
│ 🤖 Security  │─────│ 📁 Filesystem│
│ Agent        │     │ MCP Server   │
│ Skills: 3    │     └──────────────┘
└──────┬───────┘
       │              ┌──────────────┐
       └──────────────│ 🐙 GitHub   │
                      │ MCP Server   │
                      └──────────────┘
```

## Result

Your agents can now:
- Read files from your codebase
- Search code on GitHub
- Comment on pull requests
- (Optionally) Query databases

All tool calls are traced, guarded, and auditable.

## Common MCP Servers

| Server | Package | What It Does |
|---|---|---|
| Filesystem | `@modelcontextprotocol/server-filesystem` | Read/write local files |
| GitHub | `@modelcontextprotocol/server-github` | GitHub API access |
| PostgreSQL | `@modelcontextprotocol/server-postgres` | Database queries |
| Brave Search | `@modelcontextprotocol/server-brave-search` | Web search |
| Slack | `@modelcontextprotocol/server-slack` | Slack messaging |
| Memory | `@modelcontextprotocol/server-memory` | Key-value storage |

For more, see the [MCP server directory](https://github.com/modelcontextprotocol/servers).
