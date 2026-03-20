# Canvas Architecture

This deep dive covers the interactive canvas — the WYSIWYG visual composition surface built with React Flow.

## Tech Stack

```
Canvas Layer:
├── @xyflow/react (React Flow v12) — graph rendering engine
├── React 18 — component framework
├── Zustand — canvas state management
├── TypeScript — type safety
└── Tailwind CSS v4 — styling
```

## Node System

### Node Types

Each entity type has a custom React Flow node component:

| Node | Component | Appearance | Handles |
|---|---|---|---|
| Agent | `AgentNode` | Violet card with name, role, skill count | Source (right), Target (left) |
| Skill | `SkillNode` | Green chip attached to parent agent | Target (left) |
| MCP Server | `McpNode` | Pink card with name, transport | Target (left) |
| A2A Agent | `A2aNode` | Cyan card with name, endpoint | Target (left) |

### Node Data Structure

```typescript
interface CanvasNode {
  id: string;
  type: 'agent' | 'skill' | 'mcp' | 'a2a';
  position: { x: number; y: number };
  data: {
    entityId: number;
    name: string;
    role?: string;           // agents
    skillCount?: number;     // agents
    transport?: string;      // mcp
    status?: string;         // mcp, a2a
    parentAgent?: string;    // skills
  };
}
```

### Connection Handles

Handles are the anchor points for drawing edges:

```
Agent node:
├── Right edge: source handle (violet) — connect TO other entities
└── Left edge: target handle (violet) — receive connections FROM others

MCP/A2A nodes:
└── Left edge: target handle (pink/cyan) — receive connections FROM agents
```

Handle colors match node types for visual clarity during connection drawing.

## Edge System

### Edge Types

| Connection | Meaning | Style |
|---|---|---|
| Agent → Skill | Skill assignment | Solid green line |
| Agent → MCP | Tool binding | Dashed pink line |
| Agent → A2A | Delegation | Solid cyan line with arrow |
| Agent → Agent | Internal delegation | Solid violet line with arrow |

### Connection Drawing

Drawing connections uses React Flow's `onConnect` handler:

```typescript
const onConnect = (connection: Connection) => {
  const sourceNode = getNode(connection.source);
  const targetNode = getNode(connection.target);

  // Validate connection type
  if (!isValidConnection(sourceNode, targetNode)) {
    toast.error('Invalid connection');
    return;
  }

  // Create the connection via API
  switch (getConnectionType(sourceNode, targetNode)) {
    case 'agent-skill':
      assignSkillToAgent(sourceNode.data.entityId, targetNode.data.entityId);
      break;
    case 'agent-mcp':
      bindMcpToAgent(sourceNode.data.entityId, targetNode.data.entityId);
      break;
    case 'agent-a2a':
      bindA2aToAgent(sourceNode.data.entityId, targetNode.data.entityId);
      break;
    case 'agent-agent':
      createDelegation(sourceNode.data.entityId, targetNode.data.entityId);
      break;
  }
};
```

### Connection Validation

Not all connections are valid:

```
Valid:
├── Agent → Skill ✓
├── Agent → MCP Server ✓
├── Agent → A2A Agent ✓
├── Agent → Agent ✓ (delegation)

Invalid:
├── Skill → Skill ✗
├── MCP → MCP ✗
├── Skill → Agent ✗ (wrong direction)
├── Agent → itself ✗ (self-delegation)
└── Duplicate connections ✗
```

Invalid connections show a red dashed preview during drag and snap back when released.

## Detail Panel

The right-side flyout panel opens when clicking any node:

### Panel Routing

```typescript
const DetailPanel = ({ node }: { node: CanvasNode }) => {
  switch (node.type) {
    case 'agent':
      return <AgentDetailPanel agentId={node.data.entityId} />;
    case 'skill':
      return <SkillDetailPanel skillId={node.data.entityId} />;
    case 'mcp':
      return <McpDetailPanel serverId={node.data.entityId} />;
    case 'a2a':
      return <A2aDetailPanel agentId={node.data.entityId} />;
  }
};
```

### Agent Detail Panel (Tabbed)

```
Tabs:
├── Identity — name, role, icon, model, persona (Monaco editor)
├── Reasoning — skills assignment, temperature, planning mode
├── Tools — MCP bindings, A2A bindings, custom tools
└── Orchestration — autonomy level, budget, delegation rules

Every change calls the API and refreshes the graph data.
```

### Skill Detail Panel

```
Sections:
├── Frontmatter form — all metadata fields
└── Mini Monaco editor — prompt body (reduced height)

Changes save to the skill API and refresh the parent agent node.
```

## Canvas CRUD

### Creating Entities

The "+" button in the palette header opens the detail panel in "create" mode:

```
Create flow:
1. User clicks "+" on Agent palette header
2. Detail panel opens with empty form
3. User fills in name + required fields
4. On save: POST /api/agents → create agent
5. New node appears on canvas at default position
6. Graph data refreshes
```

### Deleting Entities

Right-click context menu or Delete key:

```
Delete flow:
1. Confirmation dialog (warns if agent has skills/edges)
2. DELETE /api/agents/{id}
3. Node and all connected edges removed from canvas
4. Graph data refreshes
```

### Deleting Edges

Click edge → Delete key or context menu:

```
Edge delete:
├── Agent → Skill: calls PUT /agents/{id}/skills to unassign
├── Agent → MCP: calls PUT /agents/{id}/mcp-servers to unbind
├── Agent → A2A: calls PUT /agents/{id}/a2a-agents to unbind
└── Agent → Agent: removes delegation config
```

## Auto-Layout

The auto-layout algorithm arranges nodes cleanly:

```
Algorithm:
1. Sort agents by delegation relationships (root → leaves)
2. Position agents in columns:
   - Column 0: orchestrator/root agents
   - Column 1: first-level delegates
   - Column N: Nth-level delegates
3. Position skills below/beside their parent agents
4. Position MCP/A2A nodes to the right of their connected agents
5. Minimize edge crossings
6. Apply consistent spacing (horizontal: 250px, vertical: 100px)
```

## State Management

Canvas state is managed via Zustand store:

```typescript
interface CanvasStore {
  nodes: Node[];
  edges: Edge[];
  selectedNode: string | null;
  detailPanelOpen: boolean;
  undoStack: CanvasState[];
  redoStack: CanvasState[];

  // Actions
  setNodes: (nodes: Node[]) => void;
  setEdges: (edges: Edge[]) => void;
  selectNode: (id: string) => void;
  undo: () => void;
  redo: () => void;
}
```

### Undo/Redo

Every canvas mutation (node move, create, delete, connect) pushes the previous state to the undo stack:

```
Before action: push current state to undoStack, clear redoStack
Undo: pop undoStack, push current to redoStack, apply popped state
Redo: pop redoStack, push current to undoStack, apply popped state
```

### Auto-Save

Node positions are saved to the backend with debouncing:

```
On node drag end:
  └── debounce(500ms) → PATCH /api/projects/{id}/graph/positions
```

## Data Loading

The canvas loads data from the graph endpoint:

```
GET /api/projects/{id}/graph

Response:
{
  "agents": [...],
  "skills": [...],
  "mcp_servers": [...],
  "a2a_agents": [...],
  "edges": [...],
  "positions": { "agent-1": { x: 100, y: 200 }, ... }
}
```

This is transformed into React Flow nodes and edges format by the canvas component.

## Keyboard Shortcuts

| Key | Action |
|---|---|
| `Delete` / `Backspace` | Delete selected nodes/edges |
| `Ctrl+Z` | Undo |
| `Ctrl+Shift+Z` | Redo |
| `Ctrl+A` | Select all |
| `Escape` | Deselect, close panel |
| `+` / `-` | Zoom |
| `Ctrl+0` | Fit to view |
| `Space` + drag | Pan |
| `Shift` + click | Add to selection |
| `Shift` + drag | Box select |
