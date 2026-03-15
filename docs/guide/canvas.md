# Canvas

The Canvas is a WYSIWYG visual builder that shows how agents, skills, MCP servers, and A2A connections relate to each other within a project. It is the default tab on the project detail page and is built with React Flow.

## Overview

The canvas displays four types of nodes organized in lanes:

| Lane | Node Type | Color |
|---|---|---|
| Agents | Agent nodes with name, role, and assigned skill chips | Violet |
| Skills | Skill nodes with name and tag badges | Emerald |
| Integrations | Provider sync targets, MCP servers, and A2A agents | Amber / Pink / Cyan |

Edges between nodes represent relationships: skill assignment, include dependencies, provider sync targets, and agent-to-agent delegation.

## The Sidebar Palette

The left side of the canvas contains a collapsible palette organized into three sections:

- **Agents** -- all 9 pre-built agents, draggable onto the canvas
- **Skills** -- all skills in the project, draggable onto the canvas
- **MCP** -- configured MCP servers, draggable onto the canvas

Each item in the palette has a grip handle. Drag it onto the canvas to add or connect it.

## Drag-and-Drop

### Adding Nodes

Drag an item from the sidebar palette onto the canvas to position it. The node appears at the drop location and is immediately part of the graph.

### Skill-onto-Agent Attachment

Drag a skill node onto an agent node to assign that skill to the agent. The canvas shows visual feedback during the drag -- the agent node highlights when a skill hovers over it. On drop, the skill is assigned and an edge appears connecting them.

::: tip
Skill assignment via drag-and-drop is persisted immediately via the API. You do not need to separately save.
:::

### MCP Connection Edges

Drag an MCP server node near an agent node, then draw an edge between them by clicking the source handle on one node and dragging to the target handle on the other. This represents the agent's access to that MCP server's tools.

## A2A Delegation Edges

Draw an edge between two agent nodes to create a delegation relationship. Delegation edges are visually distinct:

- **Directional arrows** with animated dashes flowing from source to target
- **Cyan color** to distinguish them from skill assignment (violet) and sync (amber) edges
- **Step number badges** on multi-hop chains

### Edge Configuration Panel

Click any delegation edge to open the configuration panel on the right side. The panel lets you configure:

**Delegation Trigger** -- A natural-language description of when this delegation should happen (e.g., "When the task requires infrastructure changes").

**Handoff Context** -- What data to pass to the target agent:

- Pass conversation history
- Pass agent memory
- Pass available tools
- Custom context (JSON)

**Return Behavior** -- What happens after the target agent completes:

| Behavior | Description |
|---|---|
| **Report Back** | Target returns results to the source agent |
| **Fire & Forget** | No response expected -- the target runs independently |
| **Chain Forward** | Target passes results to the next agent in the chain |

## Chain Visualization

When multiple delegation edges form a multi-hop chain (e.g., Orchestrator delegates to Architect, who delegates to Infrastructure), the canvas visualizes this as a numbered chain:

- Each edge in the chain gets a **step number badge** (1, 2, 3...)
- Hovering over any node in the chain **highlights the entire chain**
- The chain direction follows the delegation flow

This makes it easy to trace complex delegation paths across multiple agents.

## Node Detail Panel

Click any node on the canvas to open a slide-out detail panel on the right. The panel shows:

- **Agent nodes** -- name, role, model, autonomy level, assigned skills, and a link to the agent config
- **Skill nodes** -- name, description, tags, model, and a link to the skill editor
- **MCP nodes** -- server name, transport type, URL/command, and configured tools
- **Provider nodes** -- provider name, sync status, and output path

## Canvas Controls

The canvas toolbar provides:

| Control | Action |
|---|---|
| **Auto-Layout** | Rearrange all nodes using a directed graph layout algorithm (Dagre-style) |
| **Zoom In / Out** | Adjust the zoom level |
| **Fit to View** | Zoom and pan to fit all nodes in the viewport |
| **Fullscreen** | Expand the canvas to fill the entire browser window |
| **Minimap** | A small overview map in the corner showing all nodes and the current viewport |

::: tip
The auto-layout button is useful after adding multiple nodes. It arranges agents on the left, skills in the middle, and integrations on the right, with the most-connected nodes positioned first.
:::

## Position Persistence

Node positions are saved per project via a `canvas_layout` JSON column. When you reopen a project, nodes appear where you left them. If you rearrange nodes manually, the new positions are persisted automatically.

## Legend

The canvas includes a legend at the bottom showing edge types:

| Edge Style | Meaning |
|---|---|
| Solid violet line | Skill assigned to agent |
| Dashed emerald line | Skill includes another skill |
| Faded amber line | Agent syncs to provider |
| Dashed cyan line | Agent delegates to agent (A2A) |

## Next Steps

- [Agent Teams](./agent-teams) -- configure agents and autonomy levels
- [Workflows](./workflows) -- DAG-based multi-agent orchestration
- [Provider Sync](./provider-sync) -- how the canvas maps to provider files
