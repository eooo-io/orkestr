# 04 — Agent Builder

## Agent CRUD

### TC-04-001: Create a new agent
**Priority:** P0
**Preconditions:** Logged in, on `/agents`
**Steps:**
1. Click "New Agent"
2. Fill in Identity: name "Code Reviewer", role "reviewer", description
3. Set Goal: objective template, success criteria, max iterations: 10, timeout: 120
4. Set Reasoning: planning mode "ReAct", temperature: 0.3
5. Save
**Expected:** Agent created. Appears in agent list. Toast confirms.

### TC-04-002: Edit agent — all sections
**Priority:** P0
**Preconditions:** Agent exists
**Steps:**
1. Open agent (`/agents/:id`)
2. Modify each section: Identity, Goal, Perception, Reasoning, Observation, Orchestration
3. Save
**Expected:** All changes persisted. No data loss across sections.

### TC-04-003: Delete agent
**Priority:** P0
**Preconditions:** Agent exists, not bound to any workflow
**Steps:**
1. Click Delete
2. Confirm
**Expected:** Agent deleted. Removed from agent list.

### TC-04-004: Delete agent — bound to workflow
**Priority:** P1
**Preconditions:** Agent is used as a step in a workflow
**Steps:**
1. Try to delete agent
**Expected:** Warning: "This agent is used in N workflow(s)." Either blocks deletion or cascades with explicit confirmation.

### TC-04-005: Duplicate agent
**Priority:** P1
**Preconditions:** Agent exists with full configuration
**Steps:**
1. Click "Duplicate"
**Expected:** New agent created with "(copy)" suffix. All config copied including goal, perception, reasoning, tools.

### TC-04-006: Export agent — JSON format
**Priority:** P2
**Preconditions:** Agent exists
**Steps:**
1. Click Export → JSON
**Expected:** JSON file downloaded with complete agent definition. Valid JSON.

### TC-04-007: Export agent — YAML format
**Priority:** P2
**Preconditions:** Agent exists
**Steps:**
1. Click Export → YAML
**Expected:** YAML file downloaded with complete agent definition. Valid YAML.

## Agent Builder Form

### TC-04-008: Identity section — icon selector
**Priority:** P3
**Preconditions:** Agent builder open
**Steps:**
1. Click icon selector
2. Choose an icon
**Expected:** Icon displayed on agent card and in lists.

### TC-04-009: Goal section — max iterations validation
**Priority:** P2
**Preconditions:** Agent builder open
**Steps:**
1. Set max iterations to 0
2. Set max iterations to -1
3. Set max iterations to 1000
**Expected:** 0 and negative rejected. Reasonable upper bound enforced or warned.

### TC-04-010: Goal section — timeout validation
**Priority:** P2
**Preconditions:** Agent builder open
**Steps:**
1. Set timeout to 0
2. Set timeout to 3600 (1 hour)
**Expected:** Reasonable bounds enforced.

### TC-04-011: Perception section — context strategy options
**Priority:** P1
**Preconditions:** Agent builder open
**Steps:**
1. Select each context strategy: Full, Summary, Sliding Window, RAG
**Expected:** Each option selectable. Relevant sub-fields appear (e.g., window size for Sliding Window).

### TC-04-012: Reasoning section — planning mode options
**Priority:** P1
**Preconditions:** Agent builder open
**Steps:**
1. Select each planning mode: None, Act, Plan-then-Act, ReAct
**Expected:** Each selectable. Saved correctly.

### TC-04-013: Reasoning section — persona prompt with Monaco
**Priority:** P2
**Preconditions:** Agent builder open
**Steps:**
1. Enter multi-line persona prompt in Monaco editor field
2. Save
**Expected:** Full prompt preserved including formatting, newlines, special characters.

### TC-04-014: Orchestration section — parent agent selection
**Priority:** P2
**Preconditions:** Multiple agents exist
**Steps:**
1. Open Orchestration section
2. Select parent agent
3. Toggle "Can delegate" on
4. Enter delegation rules
5. Save
**Expected:** Hierarchy established. Parent-child relationship persisted.

### TC-04-015: Orchestration — prevent self as parent
**Priority:** P2
**Preconditions:** Agent builder open
**Steps:**
1. Try to select current agent as its own parent
**Expected:** Prevented. Validation error or agent not shown in parent dropdown.

### TC-04-016: Actions section — custom tools editor
**Priority:** P2
**Preconditions:** Agent builder open
**Steps:**
1. Open Actions section
2. Add custom tool JSON
3. Save
**Expected:** Tools JSON validated and stored.

## Project Agent Binding

### TC-04-017: Assign agent to project
**Priority:** P0
**Preconditions:** Project and global agent exist
**Steps:**
1. On Project Detail → Agents tab
2. Add agent to project
**Expected:** Agent appears in project's agent list. Can be used in project workflows.

### TC-04-018: Toggle agent active/inactive in project
**Priority:** P1
**Preconditions:** Agent bound to project
**Steps:**
1. Toggle agent off
2. Sync project
**Expected:** Disabled agent's skills not included in sync output.

### TC-04-019: Override agent instructions per project
**Priority:** P1
**Preconditions:** Agent bound to project
**Steps:**
1. Click configure on project agent
2. Enter project-specific instructions
3. Save
**Expected:** Override instructions used for this project only. Global agent unchanged.

### TC-04-020: Assign skills to project agent
**Priority:** P1
**Preconditions:** Agent bound to project, skills exist
**Steps:**
1. Open agent config modal
2. Select skills to assign
3. Save
**Expected:** Skills assigned. Agent compose includes these skills.

### TC-04-021: Bind MCP servers to project agent
**Priority:** P1
**Preconditions:** Agent bound to project, MCP servers configured
**Steps:**
1. Open agent config modal
2. Select MCP servers to bind
3. Save
**Expected:** MCP servers bound. Agent has access to those tools during execution.

### TC-04-022: Bind A2A agents to project agent
**Priority:** P2
**Preconditions:** Agent bound to project, A2A agents configured
**Steps:**
1. Bind A2A agents
2. Save
**Expected:** A2A agents bound. Agent can delegate to them during execution.

## Agent Composition

### TC-04-023: View agent compose preview
**Priority:** P1
**Preconditions:** Agent has skills and instructions
**Steps:**
1. Open compose preview for agent
**Expected:** Shows composed system prompt with all skills, tools, instructions merged. Token count displayed.

### TC-04-024: Structured compose output
**Priority:** P2
**Preconditions:** Agent fully configured
**Steps:**
1. Fetch structured compose via API
**Expected:** Returns system_prompt, model, tools array (MCP + A2A + custom), skills, loop config, delegation config. All fields populated.
