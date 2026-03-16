#!/usr/bin/env bash
# Phase L: Canvas Composer — GitHub Milestones & Issues
# Run: gh auth login && bash scripts/create-phase-l-issues.sh
#
# Prerequisites: gh CLI installed and authenticated

set -euo pipefail

REPO="eooo-io/agentis-studio"

echo "=== Creating Phase L Milestones ==="

gh milestone create --repo "$REPO" --title "L.1 — Detail Panel Overhaul" \
  --description "Full-featured entity editors in the right flyout. Every field editable, every change persisted via API."

gh milestone create --repo "$REPO" --title "L.2 — Canvas Entity Creation & Deletion" \
  --description "Create new entities directly from the canvas. No more switching to other pages."

gh milestone create --repo "$REPO" --title "L.3 — Connection Drawing" \
  --description "Drag-to-connect between nodes to create relationships. The canvas becomes the wiring surface."

gh milestone create --repo "$REPO" --title "L.4 — Canvas UX Polish" \
  --description "Multi-select, undo/redo, context menus, keyboard shortcuts, auto-save."

gh milestone create --repo "$REPO" --title "L.5 — Backend & Persistence" \
  --description "API changes to support canvas-driven composition. Edge config storage, optimistic refresh."

echo ""
echo "=== Creating L.1 Issues (Detail Panel Overhaul) ==="

gh issue create --repo "$REPO" --milestone "L.1 — Detail Panel Overhaul" \
  --title "Agent detail panel: full editor with all loop fields" \
  --label "enhancement,canvas" \
  --body "$(cat <<'BODY'
## Description
Replace the read-only agent metadata grid and instructions textarea with a full tabbed editor in the detail flyout.

## Requirements
- **Identity tab:** name, slug, role, icon picker, persona fields (avatar, bio, personality)
- **Reasoning tab:** model dropdown, planning mode, context strategy, loop condition, max iterations, temperature, timeout
- **Autonomy tab:** autonomy level (supervised/semi/autonomous), budget envelope, tool scope
- **Save button** calls `PUT /api/agents/{id}` with full payload
- **Cancel** reverts to last saved state
- Panel width: 480px (up from 400px)

## Depends on
None — this is the foundation issue.

## Files to modify
- `ui/src/components/visualization/NodeDetailPanel.tsx` — rewrite `AgentDetail`
- `ui/src/api/client.ts` — add `updateAgent()` if missing
BODY
)"

gh issue create --repo "$REPO" --milestone "L.1 — Detail Panel Overhaul" \
  --title "Agent detail panel: skill assignment manager" \
  --label "enhancement,canvas" \
  --body "$(cat <<'BODY'
## Description
Add a skill assignment section to the agent detail flyout with add/remove capabilities.

## Requirements
- Searchable dropdown to add skills from the project
- List of assigned skills with remove (X) button on each
- On change: call `PUT /api/projects/{p}/agents/{a}/skills` with updated skill IDs
- Show token count per skill and total
- Drag reorder (optional, nice-to-have)
- Refresh graph edges after change

## Files to modify
- `ui/src/components/visualization/NodeDetailPanel.tsx`
BODY
)"

gh issue create --repo "$REPO" --milestone "L.1 — Detail Panel Overhaul" \
  --title "Agent detail panel: MCP server binding" \
  --label "enhancement,canvas" \
  --body "$(cat <<'BODY'
## Description
Add MCP server binding section to agent detail panel.

## Requirements
- Dropdown to bind existing MCP servers to the agent
- Remove button to unbind
- Calls `PUT /api/projects/{p}/agents/{a}/mcp-servers`
- Show transport type per server
- Refresh graph edges after change

## Files to modify
- `ui/src/components/visualization/NodeDetailPanel.tsx`
BODY
)"

gh issue create --repo "$REPO" --milestone "L.1 — Detail Panel Overhaul" \
  --title "Agent detail panel: A2A agent binding" \
  --label "enhancement,canvas" \
  --body "$(cat <<'BODY'
## Description
Add A2A agent binding section to agent detail panel.

## Requirements
- Dropdown to bind existing A2A agents
- Remove button to unbind
- Calls `PUT /api/projects/{p}/agents/{a}/a2a-agents`
- Show URL per A2A agent
- Refresh graph edges after change

## Files to modify
- `ui/src/components/visualization/NodeDetailPanel.tsx`
BODY
)"

gh issue create --repo "$REPO" --milestone "L.1 — Detail Panel Overhaul" \
  --title "Agent detail panel: enable/disable toggle and delete" \
  --label "enhancement,canvas" \
  --body "$(cat <<'BODY'
## Description
Add enable/disable toggle and delete button to agent detail panel.

## Requirements
- Toggle switch: calls `PUT /api/projects/{p}/agents/{a}/toggle`
- Delete button with confirmation dialog
- Delete calls `DELETE /api/agents/{id}`
- After delete: remove node from canvas, close panel, refresh graph
- Disabled agents show grayed-out node on canvas

## Files to modify
- `ui/src/components/visualization/NodeDetailPanel.tsx`
- `ui/src/components/visualization/FlowGraph.tsx` (handle deletion)
BODY
)"

gh issue create --repo "$REPO" --milestone "L.1 — Detail Panel Overhaul" \
  --title "Skill detail panel: inline frontmatter editor" \
  --label "enhancement,canvas" \
  --body "$(cat <<'BODY'
## Description
Replace read-only skill metadata with editable form fields.

## Requirements
- Editable fields: name, description, model (dropdown), max_tokens, tags (multi-select)
- Save button calls `PUT /api/skills/{id}`
- Refresh graph node data after save

## Files to modify
- `ui/src/components/visualization/NodeDetailPanel.tsx` — rewrite `SkillDetail`
BODY
)"

gh issue create --repo "$REPO" --milestone "L.1 — Detail Panel Overhaul" \
  --title "Skill detail panel: embedded Monaco prompt editor" \
  --label "enhancement,canvas" \
  --body "$(cat <<'BODY'
## Description
Embed a compact Monaco Editor in the skill detail flyout for quick prompt editing without navigating away.

## Requirements
- Monaco Editor instance (~300px height, resizable)
- Loads skill body content
- Save button calls `PUT /api/skills/{id}` with updated body
- Syntax highlighting for Markdown
- "Open Full Editor" link to navigate to the dedicated skill editor page

## Files to modify
- `ui/src/components/visualization/NodeDetailPanel.tsx`

## Notes
Monaco is already a project dependency — just instantiate in the panel.
BODY
)"

gh issue create --repo "$REPO" --milestone "L.1 — Detail Panel Overhaul" \
  --title "MCP server detail panel: full editor" \
  --label "enhancement,canvas" \
  --body "$(cat <<'BODY'
## Description
Replace the skeletal MCP detail (just name + transport) with a full editor.

## Requirements
- Editable: name, transport type (stdio/sse dropdown), command, args (JSON), URL, env vars (key-value pairs)
- Save button calls `PUT /api/mcp-servers/{id}`
- Ping button calls `POST /api/projects/{p}/mcp-servers/{id}/ping` and shows result
- Delete button with confirmation
- Refresh graph after save/delete

## Files to modify
- `ui/src/components/visualization/NodeDetailPanel.tsx` — rewrite `McpDetail`
BODY
)"

gh issue create --repo "$REPO" --milestone "L.1 — Detail Panel Overhaul" \
  --title "A2A agent detail panel: full editor" \
  --label "enhancement,canvas" \
  --body "$(cat <<'BODY'
## Description
Replace skeletal A2A detail with a full editor.

## Requirements
- Editable: name, URL, description, capabilities (JSON), auth config
- Save button calls `PUT /api/a2a-agents/{id}`
- Delete button with confirmation
- Refresh graph after save/delete

## Files to modify
- `ui/src/components/visualization/NodeDetailPanel.tsx` — rewrite `A2ADetail`
BODY
)"

gh issue create --repo "$REPO" --milestone "L.1 — Detail Panel Overhaul" \
  --title "Edge config panel: persist delegation config to backend" \
  --label "enhancement,canvas,backend" \
  --body "$(cat <<'BODY'
## Description
Edge config (delegation trigger, handoff context, return behavior) is currently local state only. Persist to backend.

## Requirements
- New DB table or JSON column: `delegation_configs` keyed by source_agent + target_agent + project
- API endpoints: `GET/PUT /api/projects/{p}/delegation-configs`
- EdgeConfigPanel saves via API on blur/save button
- Load delegation configs when graph loads
- Include configs in graph endpoint response

## Files to modify
- `ui/src/components/visualization/EdgeConfigPanel.tsx`
- `app/Http/Controllers/VisualizationController.php`
- New migration for delegation configs
- `routes/api.php`
BODY
)"

echo ""
echo "=== Creating L.2 Issues (Canvas Entity Creation & Deletion) ==="

gh issue create --repo "$REPO" --milestone "L.2 — Canvas Entity Creation & Deletion" \
  --title "Canvas palette: '+' button to create new entities" \
  --label "enhancement,canvas" \
  --body "$(cat <<'BODY'
## Description
Add a "+" button to each palette section header (Agents, Skills, MCP Servers, A2A Agents) that opens the detail flyout in create mode.

## Requirements
- "+" icon button next to each section title in the palette
- Clicking opens the detail flyout with empty form fields
- Form is pre-populated with sensible defaults
- After successful creation via API, new node appears on canvas and in palette

## Files to modify
- `ui/src/components/visualization/FlowGraph.tsx` — palette section headers
BODY
)"

gh issue create --repo "$REPO" --milestone "L.2 — Canvas Entity Creation & Deletion" \
  --title "Create agent from canvas flyout" \
  --label "enhancement,canvas" \
  --body "$(cat <<'BODY'
## Description
Create a new agent directly from the canvas detail flyout.

## Requirements
- Opens agent detail panel in "create" mode (empty form)
- Minimal required fields: name, role, model
- Calls `POST /api/agents` to create
- After creation: adds agent node to canvas at default position, opens it in edit mode
- Refreshes palette to remove from "available" list

## Depends on
- #418 (agent detail panel full editor)
- #428 (palette + button)
BODY
)"

gh issue create --repo "$REPO" --milestone "L.2 — Canvas Entity Creation & Deletion" \
  --title "Create MCP server from canvas flyout" \
  --label "enhancement,canvas" \
  --body "$(cat <<'BODY'
## Description
Create a new MCP server directly from the canvas.

## Requirements
- Opens MCP detail panel in "create" mode
- Required fields: name, transport, command or URL
- Calls `POST /api/projects/{p}/mcp-servers`
- After creation: adds MCP node to canvas, refreshes palette

## Depends on
- #425 (MCP detail panel full editor)
- #428 (palette + button)
BODY
)"

gh issue create --repo "$REPO" --milestone "L.2 — Canvas Entity Creation & Deletion" \
  --title "Create A2A agent from canvas flyout" \
  --label "enhancement,canvas" \
  --body "$(cat <<'BODY'
## Description
Create a new A2A agent directly from the canvas.

## Requirements
- Opens A2A detail panel in "create" mode
- Required fields: name, URL
- Calls `POST /api/projects/{p}/a2a-agents`
- After creation: adds A2A node to canvas, refreshes palette

## Depends on
- #426 (A2A detail panel full editor)
- #428 (palette + button)
BODY
)"

gh issue create --repo "$REPO" --milestone "L.2 — Canvas Entity Creation & Deletion" \
  --title "Create skill from canvas flyout" \
  --label "enhancement,canvas" \
  --body "$(cat <<'BODY'
## Description
Create a new skill directly from the canvas.

## Requirements
- Opens skill detail panel in "create" mode
- Required fields: name (slug auto-generated)
- Optional: description, model, tags
- Calls `POST /api/projects/{p}/skills`
- After creation: adds skill node, opens in edit mode with Monaco editor

## Depends on
- #423 (skill detail panel frontmatter editor)
- #428 (palette + button)
BODY
)"

gh issue create --repo "$REPO" --milestone "L.2 — Canvas Entity Creation & Deletion" \
  --title "Delete node from canvas with confirmation" \
  --label "enhancement,canvas" \
  --body "$(cat <<'BODY'
## Description
Delete entities from the canvas via detail panel or context menu.

## Requirements
- Delete button in every detail panel (already in #422 for agents, add to MCP/A2A/skill)
- Right-click context menu with "Delete" option
- Confirmation dialog with entity name and warning about connected edges
- Calls appropriate DELETE endpoint
- Removes node and all connected edges from canvas
- Refreshes graph data

## Files to modify
- `ui/src/components/visualization/NodeDetailPanel.tsx`
- `ui/src/components/visualization/FlowGraph.tsx`
BODY
)"

gh issue create --repo "$REPO" --milestone "L.2 — Canvas Entity Creation & Deletion" \
  --title "Delete edge from canvas" \
  --label "enhancement,canvas" \
  --body "$(cat <<'BODY'
## Description
Delete edges (connections) from the canvas.

## Requirements
- Click edge to select, then press Delete key
- Or right-click edge → "Remove connection"
- For agent↔skill edges: calls `PUT /api/projects/{p}/agents/{a}/skills` with skill removed
- For agent↔MCP edges: calls `PUT /api/projects/{p}/agents/{a}/mcp-servers` with server removed
- For delegation edges: removes delegation config
- Visual feedback: selected edge gets a highlight

## Files to modify
- `ui/src/components/visualization/FlowGraph.tsx`
BODY
)"

gh issue create --repo "$REPO" --milestone "L.2 — Canvas Entity Creation & Deletion" \
  --title "Unassign skill from agent via canvas" \
  --label "enhancement,canvas" \
  --body "$(cat <<'BODY'
## Description
Remove a skill assignment from an agent without deleting the skill itself.

## Requirements
- Remove (X) button on each skill in the agent detail panel's skill list
- Clicking removes the skill from the assignment array
- Calls `PUT /api/projects/{p}/agents/{a}/skills` with updated list
- Removes the edge from canvas
- Skill node remains on canvas (just disconnected)

## Depends on
- #419 (skill assignment manager in agent detail)
BODY
)"

echo ""
echo "=== Creating L.3 Issues (Connection Drawing) ==="

gh issue create --repo "$REPO" --milestone "L.3 — Connection Drawing" \
  --title "Drag-to-connect: agent → skill assignment" \
  --label "enhancement,canvas" \
  --body "$(cat <<'BODY'
## Description
Draw a connection from an agent node to a skill node to create a skill assignment.

## Requirements
- Drag from agent source handle to skill target handle
- On connect: call `PUT /api/projects/{p}/agents/{a}/skills` to add the skill
- Create styled edge (purple solid, matching existing skill assignment edges)
- Prevent duplicate edges (if already assigned)

## Files to modify
- `ui/src/components/visualization/FlowGraph.tsx` — `onConnect` handler
- `ui/src/components/visualization/FlowNodes.tsx` — add connection handles
BODY
)"

gh issue create --repo "$REPO" --milestone "L.3 — Connection Drawing" \
  --title "Drag-to-connect: agent → MCP server binding" \
  --label "enhancement,canvas" \
  --body "$(cat <<'BODY'
## Description
Draw a connection from an agent to an MCP server to bind the tool server.

## Requirements
- Drag from agent handle to MCP handle
- On connect: call `PUT /api/projects/{p}/agents/{a}/mcp-servers`
- Create styled edge (pink dashed, matching existing MCP edges)
- Prevent duplicates
BODY
)"

gh issue create --repo "$REPO" --milestone "L.3 — Connection Drawing" \
  --title "Drag-to-connect: agent → A2A delegation" \
  --label "enhancement,canvas" \
  --body "$(cat <<'BODY'
## Description
Draw a connection from an agent to an A2A agent to create a delegation edge.

## Requirements
- Drag from agent handle to A2A handle
- On connect: call `PUT /api/projects/{p}/agents/{a}/a2a-agents`
- Create styled edge (cyan dashed animated, matching existing delegation edges)
- Opens edge config panel after creation
BODY
)"

gh issue create --repo "$REPO" --milestone "L.3 — Connection Drawing" \
  --title "Drag-to-connect: agent → agent delegation" \
  --label "enhancement,canvas" \
  --body "$(cat <<'BODY'
## Description
Draw a connection between two agent nodes to create a delegation relationship.

## Requirements
- Drag from agent source handle to another agent target handle
- Creates delegation edge with animated dashed style
- Opens edge config panel for trigger/handoff/return configuration
- Updates chain visualization automatically
BODY
)"

gh issue create --repo "$REPO" --milestone "L.3 — Connection Drawing" \
  --title "Connection validation rules" \
  --label "enhancement,canvas" \
  --body "$(cat <<'BODY'
## Description
Prevent invalid edge creation with clear visual feedback.

## Rules
- skill → skill: BLOCKED (no skill-to-skill edges)
- MCP → anything: BLOCKED (MCP is always a target)
- A2A → anything: BLOCKED (A2A is always a target, except as delegation source if bidirectional)
- provider → anything: BLOCKED (providers are read-only visualization nodes)
- Duplicate edges: BLOCKED
- Self-loops: BLOCKED

## Visual feedback
- Valid target: handle glows green during drag
- Invalid target: handle shows red, cursor changes to not-allowed
- Drop on invalid target: connection snaps back, no API call

## Files to modify
- `ui/src/components/visualization/FlowGraph.tsx` — `isValidConnection` callback
BODY
)"

gh issue create --repo "$REPO" --milestone "L.3 — Connection Drawing" \
  --title "Connection handles on nodes" \
  --label "enhancement,canvas" \
  --body "$(cat <<'BODY'
## Description
Show connection anchor points on nodes during hover/interaction.

## Requirements
- Source handles on right edge of agent nodes (violet)
- Target handles on left edge of skill (green), MCP (pink), A2A (cyan), agent (violet) nodes
- Handles hidden by default, visible on node hover
- Handle size: 10px circle
- Handles match the edge color for that connection type

## Files to modify
- `ui/src/components/visualization/FlowNodes.tsx` — add Handle components
BODY
)"

gh issue create --repo "$REPO" --milestone "L.3 — Connection Drawing" \
  --title "Visual feedback during connection drag" \
  --label "enhancement,canvas" \
  --body "$(cat <<'BODY'
## Description
Show a styled preview line while dragging a connection.

## Requirements
- Dashed line from source handle to cursor during drag
- Line color matches expected edge type (purple for skill, pink for MCP, cyan for A2A/delegation)
- Green glow on valid targets
- Red indicator on invalid targets
- Use React Flow connectionLineStyle and connectionLineComponent

## Files to modify
- `ui/src/components/visualization/FlowGraph.tsx`
BODY
)"

echo ""
echo "=== Creating L.4 Issues (Canvas UX Polish) ==="

gh issue create --repo "$REPO" --milestone "L.4 — Canvas UX Polish" \
  --title "Multi-select: shift+click and box selection" \
  --label "enhancement,canvas" \
  --body "$(cat <<'BODY'
## Description
Select multiple nodes for bulk operations.

## Requirements
- Shift+click to add/remove nodes from selection
- Box selection by clicking and dragging on empty canvas area
- Selected nodes highlighted with border
- Move all selected nodes together
- Delete key removes all selected nodes (with confirmation if >1)
- Escape clears selection

## Files to modify
- `ui/src/components/visualization/FlowGraph.tsx`
BODY
)"

gh issue create --repo "$REPO" --milestone "L.4 — Canvas UX Polish" \
  --title "Context menu: right-click on nodes, edges, and canvas" \
  --label "enhancement,canvas" \
  --body "$(cat <<'BODY'
## Description
Right-click context menu with contextual actions.

## Node context menu
- Edit (opens detail panel)
- Duplicate
- Delete
- Disable/Enable (agents only)

## Edge context menu
- Configure (opens edge config panel — delegation edges only)
- Remove connection

## Canvas context menu
- Create Agent
- Create Skill
- Create MCP Server
- Create A2A Agent
- Auto Layout
- Fit to View

## Files to modify
- `ui/src/components/visualization/FlowGraph.tsx`
- New: `ui/src/components/visualization/CanvasContextMenu.tsx`
BODY
)"

gh issue create --repo "$REPO" --milestone "L.4 — Canvas UX Polish" \
  --title "Keyboard shortcuts for canvas" \
  --label "enhancement,canvas" \
  --body "$(cat <<'BODY'
## Description
Keyboard shortcuts for common canvas operations.

## Shortcuts
- `Delete` / `Backspace` — Delete selected nodes/edges
- `Cmd+Z` / `Ctrl+Z` — Undo
- `Cmd+Shift+Z` / `Ctrl+Shift+Z` — Redo
- `Cmd+A` / `Ctrl+A` — Select all nodes
- `Escape` — Deselect all / close panel
- `L` — Auto-layout
- `F` — Fit to view
- `+` / `-` — Zoom in/out

## Files to modify
- `ui/src/components/visualization/FlowGraph.tsx` — keydown handler
BODY
)"

gh issue create --repo "$REPO" --milestone "L.4 — Canvas UX Polish" \
  --title "Undo/redo for canvas operations" \
  --label "enhancement,canvas" \
  --body "$(cat <<'BODY'
## Description
Track canvas operations in an undo stack.

## Tracked operations
- Create node
- Delete node
- Create edge (connection)
- Delete edge
- Move nodes (batch position change)
- Assign/unassign skill

## Requirements
- Undo stack (max 50 operations)
- Each operation stores: type, payload, and reverse action
- Undo calls the reverse API operation
- Redo replays the forward operation
- Stack clears on navigation away from canvas

## Files to modify
- New: `ui/src/hooks/useCanvasHistory.ts`
- `ui/src/components/visualization/FlowGraph.tsx`
BODY
)"

gh issue create --repo "$REPO" --milestone "L.4 — Canvas UX Polish" \
  --title "Auto-save canvas positions (debounced)" \
  --label "enhancement,canvas" \
  --body "$(cat <<'BODY'
## Description
Automatically save node positions after dragging, instead of only on explicit save.

## Requirements
- Debounce 500ms after last node move
- Call `PUT /api/projects/{p}/canvas-layout` with updated positions
- Show subtle "saved" indicator (brief toast or checkmark in toolbar)
- Don't save during bulk operations (wait until done)

## Files to modify
- `ui/src/components/visualization/FlowGraph.tsx`
BODY
)"

gh issue create --repo "$REPO" --milestone "L.4 — Canvas UX Polish" \
  --title "Empty canvas onboarding state" \
  --label "enhancement,canvas" \
  --body "$(cat <<'BODY'
## Description
When canvas has no nodes, show a helpful onboarding prompt.

## Requirements
- Centered content: "Start building your agent team"
- "Create your first agent" button (opens agent create flyout)
- Brief description of what the canvas does
- Disappears as soon as first node is added

## Files to modify
- `ui/src/components/visualization/FlowGraph.tsx`
BODY
)"

gh issue create --repo "$REPO" --milestone "L.4 — Canvas UX Polish" \
  --title "Node search and filter in toolbar" \
  --label "enhancement,canvas" \
  --body "$(cat <<'BODY'
## Description
Filter/search visible nodes from the canvas toolbar.

## Requirements
- Search input in toolbar: filters nodes by name
- Type filter checkboxes: Agents, Skills, MCP, A2A (all on by default)
- Filtered-out nodes fade to 10% opacity (not removed, just dimmed)
- Matching nodes highlighted
- Clear button resets all filters

## Files to modify
- `ui/src/components/visualization/FlowGraph.tsx` — toolbar
BODY
)"

echo ""
echo "=== Creating L.5 Issues (Backend & Persistence) ==="

gh issue create --repo "$REPO" --milestone "L.5 — Backend & Persistence" \
  --title "Edge config model and API endpoints" \
  --label "enhancement,canvas,backend" \
  --body "$(cat <<'BODY'
## Description
Persist delegation edge configurations to the database.

## Migration
```sql
CREATE TABLE delegation_configs (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    project_id BIGINT UNSIGNED NOT NULL,
    source_agent_id BIGINT UNSIGNED NOT NULL,
    target_agent_id BIGINT UNSIGNED NULL,
    target_a2a_agent_id BIGINT UNSIGNED NULL,
    trigger_condition TEXT NULL,
    pass_conversation_history BOOLEAN DEFAULT TRUE,
    pass_agent_memory BOOLEAN DEFAULT FALSE,
    pass_available_tools BOOLEAN DEFAULT FALSE,
    custom_context JSON NULL,
    return_behavior ENUM('report_back', 'fire_and_forget', 'chain_forward') DEFAULT 'report_back',
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE(project_id, source_agent_id, target_agent_id),
    UNIQUE(project_id, source_agent_id, target_a2a_agent_id)
);
```

## API Endpoints
- `GET /api/projects/{p}/delegation-configs` — list all for project
- `PUT /api/projects/{p}/delegation-configs` — upsert a config
- `DELETE /api/delegation-configs/{id}` — delete

## Files to create
- Migration
- `app/Models/DelegationConfig.php`
- `app/Http/Controllers/DelegationConfigController.php`
- `routes/api.php` — register endpoints
BODY
)"

gh issue create --repo "$REPO" --milestone "L.5 — Backend & Persistence" \
  --title "Include edge configs in graph endpoint response" \
  --label "enhancement,canvas,backend" \
  --body "$(cat <<'BODY'
## Description
Extend `GET /api/projects/{p}/graph` to include delegation configs.

## Requirements
- Add `delegation_configs` array to `ProjectGraphData` response
- Each config includes: source_agent_id, target_agent_id or target_a2a_agent_id, trigger, handoff flags, return behavior
- Frontend loads configs into `edgeConfigs` Map on graph init (replacing local-only state)

## Files to modify
- `app/Http/Controllers/VisualizationController.php`
- `ui/src/types/index.ts` — extend `ProjectGraphData` type
- `ui/src/components/visualization/FlowGraph.tsx` — load configs on init
BODY
)"

gh issue create --repo "$REPO" --milestone "L.5 — Backend & Persistence" \
  --title "Optimistic graph refresh after canvas mutations" \
  --label "enhancement,canvas" \
  --body "$(cat <<'BODY'
## Description
After creating/deleting/connecting on canvas, merge changes locally before full API refetch.

## Requirements
- After API call succeeds: immediately update local nodes/edges state
- Trigger background refetch of full graph data
- On refetch complete: reconcile with local state (merge positions, update counts)
- If API call fails: revert local changes and show error toast
- Prevents UI flickering between mutation and refetch

## Files to modify
- `ui/src/components/visualization/FlowGraph.tsx`
BODY
)"

gh issue create --repo "$REPO" --milestone "L.5 — Backend & Persistence" \
  --title "Agent quick-create API endpoint" \
  --label "enhancement,canvas,backend" \
  --body "$(cat <<'BODY'
## Description
Minimal agent creation endpoint for canvas use (name + model, defaults for everything else).

## Requirements
- `POST /api/projects/{p}/agents/quick-create`
- Required: `name`
- Optional: `model`, `role`
- Auto-generates slug from name
- Sets sensible defaults for all other fields
- Returns full agent object
- Auto-enables the agent for the project

## Why
The existing `POST /api/agents` may require more fields. Canvas needs a fast path for "add agent, configure later."

## Files to modify
- `app/Http/Controllers/AgentController.php`
- `routes/api.php`
BODY
)"

gh issue create --repo "$REPO" --milestone "L.5 — Backend & Persistence" \
  --title "Pest tests for canvas CRUD and edge config persistence" \
  --label "testing,canvas,backend" \
  --body "$(cat <<'BODY'
## Description
Test coverage for all new canvas backend functionality.

## Test cases
- Delegation config CRUD (create, read, update, delete)
- Delegation config uniqueness constraints
- Graph endpoint includes delegation configs
- Agent quick-create with minimal fields
- Agent quick-create auto-generates slug
- Canvas layout persistence (existing, verify still works)

## Files to create
- `tests/Feature/DelegationConfigTest.php`
- `tests/Feature/AgentQuickCreateTest.php`
BODY
)"

echo ""
echo "=== Done! ==="
echo "Created 5 milestones and 37 issues for Phase L: Canvas Composer"
