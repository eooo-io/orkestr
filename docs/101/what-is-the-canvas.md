# What is the Canvas?

## The One-Sentence Answer

The Canvas is a visual, interactive design surface where you build your entire agent system — agents, skills, tools, and connections — by dragging, dropping, and wiring things together.

## The Analogy: A Whiteboard with Sticky Notes

Imagine planning a project on a whiteboard. You write team members on sticky notes, draw arrows showing who reports to whom, and pin reference documents next to the people who need them.

The Canvas is a digital version of that whiteboard — but it's *live*. The sticky notes are agents, the reference documents are skills, the arrows are real connections, and you can press "run" to see it all execute.

## What You See on the Canvas

The Canvas displays your agent system as a node-and-edge graph:

```
┌──────────────────────────────────────────────────────┐
│  Canvas                                    🔍 ⊞ ⊟   │
│                                                      │
│  ┌─────────────┐                                     │
│  │ 🤖 Orchestr.│──────────────►┌─────────────┐      │
│  │              │               │ 🤖 Security │      │
│  │  Skills: 2   │──────┐       │              │      │
│  └─────────────┘      │       │  Skills: 3   │      │
│         │              │       └──────┬───────┘      │
│         │              │              │               │
│         ▼              ▼              ▼               │
│  ┌─────────────┐ ┌──────────┐ ┌──────────────┐      │
│  │ 🤖 QA       │ │ 🔧 MCP  │ │ 🔧 MCP       │      │
│  │              │ │ GitHub   │ │ Filesystem   │      │
│  │  Skills: 4   │ └──────────┘ └──────────────┘      │
│  └─────────────┘                                     │
│                                                      │
└──────────────────────────────────────────────────────┘
```

### Node Types

| Node | Color | What It Represents |
|---|---|---|
| **Agent** | Violet | An AI agent with its full definition |
| **Skill** | Green | A skill (shown as chips attached to agents) |
| **MCP Server** | Pink | A tool server that agents can use |
| **A2A Agent** | Cyan | An external agent available for delegation |

### Edge Types

| Connection | What It Means |
|---|---|
| Agent → Skill | This agent uses this skill |
| Agent → MCP Server | This agent can use these tools |
| Agent → Agent | Delegation relationship |
| Agent → A2A Agent | Can delegate to this external agent |

## What You Can Do on the Canvas

### Design

- **Drag and drop** agents, skills, MCP servers, and A2A agents from the sidebar palette
- **Create new entities** directly on the canvas (click "+" in the palette)
- **Draw connections** by dragging from one node to another
- **Edit any entity** by clicking its node — a detail panel slides out

### Compose

- **Assign skills to agents** by drawing a connection or using the detail panel
- **Wire MCP tools to agents** by connecting tool server nodes
- **Set up delegation chains** by connecting agent to agent
- **Configure edge settings** — delegation triggers, handoff context, conditions

### Execute

- **Run a single agent** from its detail panel
- **Run the entire workflow** from the toolbar
- **Watch execution live** — nodes light up with status indicators
- **View results** in the output drawer

### Manage

- **Multi-select** with Shift+click or box selection
- **Right-click context menus** for quick actions
- **Undo/redo** for all canvas operations
- **Auto-layout** to clean up messy arrangements
- **Search and filter** nodes from the toolbar
- **Minimap** for navigation in large canvases

## The Detail Panel

Click any node and a detail panel slides out on the right side. This is a full editor:

### Agent Detail Panel (Tabbed)

- **Identity** — Name, role, icon, persona, model
- **Reasoning** — Skills, planning mode, temperature
- **Tools** — MCP server bindings, A2A bindings, custom tools
- **Orchestration** — Autonomy level, budget, delegation rules

### Skill Detail Panel

- **Frontmatter editor** — All metadata fields
- **Mini Monaco editor** — Edit the prompt body inline
- No need to leave the canvas to tweak a skill

### MCP Server Detail Panel

- **Connection settings** — Transport (stdio/SSE), command, URL
- **Tool list** — Discovered tools from the server
- **Health status** — Connection state

### A2A Agent Detail Panel

- **Endpoint** — URL and protocol version
- **Capabilities** — Discovered from the agent card
- **Status** — Connection health

## Canvas Keyboard Shortcuts

| Shortcut | Action |
|---|---|
| `Delete` / `Backspace` | Delete selected node(s) |
| `Ctrl+Z` / `Cmd+Z` | Undo |
| `Ctrl+Shift+Z` / `Cmd+Shift+Z` | Redo |
| `Ctrl+A` / `Cmd+A` | Select all |
| `Escape` | Deselect / close panel |
| `+` / `-` | Zoom in / out |
| `Ctrl+0` | Fit view |
| `Space` + drag | Pan |

## Building from Scratch vs. Starting with Templates

### Starting from Scratch

1. Open the Canvas for your project
2. Click "+" in the Agent palette to create a new agent
3. Fill in the agent's identity and goal in the detail panel
4. Drag skills from the palette onto the agent
5. Add MCP servers for tool access
6. Draw delegation connections between agents
7. Click "Run" to test

### Starting with Pre-Built Agents

1. Open the Canvas
2. Enable pre-built agents (Security, QA, Architect, etc.) from the palette
3. They appear on the canvas with their default configurations
4. Customize them — add project-specific skills, change models, adjust autonomy
5. Wire them together with delegation edges
6. Add checkpoints for human review

## Key Takeaway

The Canvas is where design meets execution. It's a WYSIWYG builder for agent systems — you see what you build, you build what you see, and you can run it right there. Everything you can do in config files and API calls, you can do visually on the Canvas.

---

**Next:** [What is Execution?](./what-is-execution) →
