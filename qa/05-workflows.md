# 05 — Workflow Builder

## Workflow CRUD

### TC-05-001: Create a new workflow
**Priority:** P0
**Preconditions:** Project exists with agents
**Steps:**
1. Navigate to `/projects/:id/workflows`
2. Click "New Workflow"
3. Enter name: "Code Review Pipeline"
4. Select trigger type: manual
5. Save
**Expected:** Workflow created in Draft status. Opens in workflow builder.

### TC-05-002: Delete workflow
**Priority:** P0
**Preconditions:** Workflow exists
**Steps:**
1. Click delete on workflow
2. Confirm
**Expected:** Workflow and all its steps/edges deleted.

### TC-05-003: Duplicate workflow
**Priority:** P1
**Preconditions:** Workflow exists with steps and edges
**Steps:**
1. Click "Duplicate"
**Expected:** New workflow created with all steps and edges copied. Name: "Code Review Pipeline (copy)."

### TC-05-004: Workflow list — status badges
**Priority:** P2
**Preconditions:** Workflows in different statuses (Draft, Active, Archived)
**Steps:**
1. View workflow list
**Expected:** Each workflow shows correct status badge with appropriate color/icon.

### TC-05-005: Workflow list — metadata display
**Priority:** P2
**Preconditions:** Workflows exist
**Steps:**
1. View workflow list
**Expected:** Each shows name, status, trigger type, step count, edge count.

## Visual DAG Builder

### TC-05-006: Add agent step node
**Priority:** P0
**Preconditions:** Workflow builder open, agents exist in project
**Steps:**
1. Add a new node from toolbar
2. Select type: Agent
3. Assign an agent
4. Position on canvas
**Expected:** Agent node appears on canvas with agent name and icon.

### TC-05-007: Add checkpoint node
**Priority:** P1
**Preconditions:** Workflow builder open
**Steps:**
1. Add node type: Checkpoint
2. Configure approval message
**Expected:** Checkpoint node appears. Will pause execution for human approval.

### TC-05-008: Add condition node
**Priority:** P1
**Preconditions:** Workflow builder open
**Steps:**
1. Add node type: Condition
2. Configure condition expression
**Expected:** Condition node appears with branching logic.

### TC-05-009: Connect nodes with edges
**Priority:** P0
**Preconditions:** 2+ nodes on canvas
**Steps:**
1. Drag from source node handle to target node handle
**Expected:** Edge created connecting the two nodes. Visible line on canvas.

### TC-05-010: Conditional edge with expression
**Priority:** P1
**Preconditions:** Condition node exists with outgoing edges
**Steps:**
1. Click on an edge
2. Open properties panel
3. Set condition expression: `output.status === 'approved'`
4. Set label: "Approved"
**Expected:** Edge shows label. Expression saved and used for routing.

### TC-05-011: Delete node
**Priority:** P0
**Preconditions:** Node exists on canvas
**Steps:**
1. Select node
2. Press Delete key or click delete button
**Expected:** Node removed. All connected edges removed.

### TC-05-012: Delete edge
**Priority:** P1
**Preconditions:** Edge exists
**Steps:**
1. Click on edge
2. Delete
**Expected:** Edge removed. Nodes remain.

### TC-05-013: Drag to reposition nodes
**Priority:** P1
**Preconditions:** Nodes on canvas
**Steps:**
1. Drag a node to new position
**Expected:** Node moves. Edges follow. Position saved on workflow save.

### TC-05-014: Canvas zoom and pan
**Priority:** P2
**Preconditions:** Workflow builder open
**Steps:**
1. Scroll to zoom in/out
2. Click and drag empty canvas to pan
**Expected:** Smooth zoom and pan. Nodes remain at correct relative positions.

### TC-05-015: Properties panel — edit selected node
**Priority:** P1
**Preconditions:** Node selected
**Steps:**
1. Click on an agent node
2. Properties panel opens on right
3. Change agent assignment, add config
4. Save workflow
**Expected:** Properties saved. Node reflects changes.

## DAG Validation

### TC-05-016: Validate — valid DAG
**Priority:** P0
**Preconditions:** Workflow has start → agent → end nodes, all connected
**Steps:**
1. Click "Validate"
**Expected:** "Workflow is valid." All checks pass.

### TC-05-017: Validate — disconnected nodes
**Priority:** P1
**Preconditions:** Workflow has an unconnected node
**Steps:**
1. Add node but don't connect it
2. Click "Validate"
**Expected:** Validation error: "Node 'X' is not connected to the workflow."

### TC-05-018: Validate — cycle detection
**Priority:** P0
**Preconditions:** Workflow has A → B → C → A cycle
**Steps:**
1. Create circular edges
2. Click "Validate"
**Expected:** Validation error: "Cycle detected." DAGs cannot have cycles.

### TC-05-019: Validate — no entry point
**Priority:** P1
**Preconditions:** Workflow has no start node
**Steps:**
1. Delete start node
2. Validate
**Expected:** Error: "Workflow must have an entry point."

## Versioning

### TC-05-020: Create workflow version snapshot
**Priority:** P1
**Preconditions:** Workflow exists with steps and edges
**Steps:**
1. Click "Save Version" or version snapshot button
2. Enter note: "Added approval checkpoint"
**Expected:** Version snapshot created with full workflow state (steps, edges, config).

### TC-05-021: View version history
**Priority:** P2
**Preconditions:** Workflow has 3+ versions
**Steps:**
1. Open version history
**Expected:** Version list with number, timestamp, note for each.

### TC-05-022: Restore workflow version
**Priority:** P1
**Preconditions:** Workflow has older version
**Steps:**
1. Select older version
2. Click "Restore"
3. Confirm
**Expected:** Workflow reverted to that version. All steps/edges restored. New version created.

## Export

### TC-05-023: Export workflow — generic JSON
**Priority:** P2
**Preconditions:** Valid workflow
**Steps:**
1. Click Export → Generic JSON
**Expected:** JSON file downloaded with workflow definition, steps, edges, agent references.

### TC-05-024: Export workflow — LangGraph YAML
**Priority:** P2
**Preconditions:** Valid workflow
**Steps:**
1. Click Export → LangGraph YAML
**Expected:** Valid LangGraph-compatible YAML file downloaded.

### TC-05-025: Export workflow — CrewAI config
**Priority:** P2
**Preconditions:** Valid workflow
**Steps:**
1. Click Export → CrewAI
**Expected:** Valid CrewAI configuration file downloaded.

### TC-05-026: Workflow status transitions
**Priority:** P1
**Preconditions:** Workflow in Draft
**Steps:**
1. Set status to Active
2. Set status to Archived
3. Set status back to Draft
**Expected:** Status transitions work. Active workflows can be executed. Archived cannot.
