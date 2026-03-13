# 07 — Integrations

## MCP (Model Context Protocol)

### TC-07-001: Add MCP server
**Priority:** P0
**Preconditions:** Project exists, on MCP tab
**Steps:**
1. Click "Add MCP Server"
2. Enter name, transport type (stdio or SSE), command/URL
3. Save
**Expected:** MCP server added to project. Appears in list.

### TC-07-002: MCP server — test connection (ping)
**Priority:** P1
**Preconditions:** MCP server configured
**Steps:**
1. Click "Ping" or "Test Connection"
**Expected:** Success: "Connected. Server responding." Failure: clear error with reason.

### TC-07-003: MCP server — discover tools
**Priority:** P0
**Preconditions:** MCP server connected
**Steps:**
1. Click "Discover Tools" or view tools list
**Expected:** Available tools listed with name, description, input schema.

### TC-07-004: MCP server — remove
**Priority:** P1
**Preconditions:** MCP server exists
**Steps:**
1. Delete MCP server
**Expected:** Server removed. Agents that had it bound show it as unbound.

### TC-07-005: MCP server — stdio transport
**Priority:** P1
**Preconditions:** Local MCP server available via stdio
**Steps:**
1. Configure with command: `npx @modelcontextprotocol/server-filesystem /tmp`
2. Test connection
3. List tools
**Expected:** Connection via stdio works. Tools discovered.

### TC-07-006: MCP server — SSE transport
**Priority:** P1
**Preconditions:** Remote MCP server available via SSE
**Steps:**
1. Configure with SSE URL
2. Test connection
**Expected:** Connection via SSE works. Tools discovered.

### TC-07-007: MCP server — connection failure handling
**Priority:** P1
**Preconditions:** MCP server configured with invalid command/URL
**Steps:**
1. Test connection
**Expected:** Clear error: "Connection failed: [reason]." No crash. No hanging.

### TC-07-008: MCP server — lifecycle management
**Priority:** P2
**Preconditions:** Stdio MCP server configured
**Steps:**
1. Start server (if manual lifecycle)
2. Execute an agent that uses its tools
3. Stop server
**Expected:** Server starts/stops cleanly. No zombie processes.

## A2A (Agent-to-Agent Protocol)

### TC-07-009: Add A2A agent
**Priority:** P1
**Preconditions:** Project exists, on A2A tab
**Steps:**
1. Click "Add A2A Agent"
2. Enter agent URL (remote agent card endpoint)
3. Save
**Expected:** Agent card fetched. A2A agent added with name, description, capabilities.

### TC-07-010: A2A — discover agent card
**Priority:** P1
**Preconditions:** Remote A2A agent running
**Steps:**
1. Enter agent URL
2. Click discover
**Expected:** Agent card displayed with supported tasks, protocols, capabilities.

### TC-07-011: A2A — delegate task
**Priority:** P1
**Preconditions:** A2A agent bound to project agent
**Steps:**
1. Execute agent where it delegates a task to A2A agent
**Expected:** Task sent via A2A protocol. Response received and incorporated. Trace shows delegation.

### TC-07-012: A2A — connection failure
**Priority:** P2
**Preconditions:** A2A agent URL unreachable
**Steps:**
1. Try to connect
**Expected:** Clear error. Agent execution falls back gracefully if A2A agent unavailable.

## Marketplace

### TC-07-013: Browse marketplace
**Priority:** P1
**Preconditions:** Marketplace has published skills
**Steps:**
1. Navigate to marketplace (via library or dedicated route)
**Expected:** Skills displayed as cards with name, description, author, upvotes, downloads.

### TC-07-014: Publish skill to marketplace
**Priority:** P1
**Preconditions:** Skill exists, user on Pro tier (if gated)
**Steps:**
1. Click "Publish" on a skill
2. Fill in marketplace metadata
3. Submit
**Expected:** Skill published. Appears in marketplace. Toast confirms.

### TC-07-015: Install skill from marketplace
**Priority:** P1
**Preconditions:** Marketplace skill exists
**Steps:**
1. Click "Install" on marketplace skill
2. Select target project
3. Confirm
**Expected:** Skill imported into project. File created on disk.

### TC-07-016: Vote on marketplace skill
**Priority:** P2
**Preconditions:** Marketplace skill exists
**Steps:**
1. Click upvote
2. Verify vote count increases
3. Click upvote again (toggle off)
**Expected:** Vote toggled. Count updated in real-time.

### TC-07-017: Marketplace — search and filter
**Priority:** P2
**Preconditions:** Marketplace has 10+ skills
**Steps:**
1. Search by keyword
2. Filter by category/tags
**Expected:** Results filtered correctly. Search is responsive.

## Library

### TC-07-018: Browse library
**Priority:** P1
**Preconditions:** Library seeded with 25+ skills
**Steps:**
1. Navigate to `/library`
2. Browse categories: Laravel, PHP, TypeScript, FinTech, DevOps, Writing
**Expected:** Skills displayed per category. Search works. Categories filter correctly.

### TC-07-019: Import library skill to project
**Priority:** P0
**Preconditions:** Library skill exists, project exists
**Steps:**
1. Click "Import" on library skill
2. Select target project
3. Confirm
**Expected:** Skill copied into project. File created on disk. Appears in project skill list.

### TC-07-020: Library — search
**Priority:** P2
**Preconditions:** Library populated
**Steps:**
1. Type search query with debounce
**Expected:** Results filter in real-time. Matches name, description, tags.

### TC-07-021: Skills.sh discovery
**Priority:** P2
**Preconditions:** GitHub access available
**Steps:**
1. Click "Skills.sh" button
2. Search for skills on GitHub
3. Preview a discovered skill
4. Import
**Expected:** GitHub skills discovered. Preview shows content. Import adds to project or library.

## Bundles

### TC-07-022: Export bundle
**Priority:** P1
**Preconditions:** Project has skills and agents
**Steps:**
1. Click "Export Bundle"
2. Select skills/agents to include
3. Choose format (JSON/YAML)
4. Download
**Expected:** Bundle file downloaded with selected items. Valid format.

### TC-07-023: Import bundle — no conflicts
**Priority:** P1
**Preconditions:** Bundle file from another project
**Steps:**
1. Click "Import Bundle"
2. Upload file
3. Preview contents
4. Import
**Expected:** Skills and agents imported. Files created on disk.

### TC-07-024: Import bundle — with conflicts
**Priority:** P1
**Preconditions:** Bundle contains skill with same slug as existing skill
**Steps:**
1. Import bundle with conflicting skill
2. Preview shows conflict
3. Choose conflict mode: skip / overwrite / rename
**Expected:** Conflict resolved per chosen mode. No data loss.

## Webhooks

### TC-07-025: Create webhook
**Priority:** P2
**Preconditions:** Project exists
**Steps:**
1. Add webhook URL: https://example.com/hook
2. Select events: skill.created, skill.updated
3. Save
**Expected:** Webhook created with HMAC secret. Shows in list.

### TC-07-026: Webhook delivery on skill save
**Priority:** P2
**Preconditions:** Webhook configured for skill.updated
**Steps:**
1. Edit and save a skill
**Expected:** Webhook delivered. Delivery log shows status 200. HMAC signature valid.

### TC-07-027: Webhook — failed delivery
**Priority:** P2
**Preconditions:** Webhook URL returns 500
**Steps:**
1. Trigger webhook event
**Expected:** Delivery logged with failure status. Retry behavior (if any) documented.

## Reverse Sync (Import from Provider)

### TC-07-028: Detect importable skills from provider files
**Priority:** P2
**Preconditions:** Project path has `.cursor/rules/` with MDC files not created by eooo
**Steps:**
1. Open Import tab
2. Click "Detect"
**Expected:** Shows list of detected skills from provider config files with preview.

### TC-07-029: Import skill from provider config
**Priority:** P2
**Preconditions:** Detected skills available
**Steps:**
1. Select a detected skill
2. Click "Import"
**Expected:** Skill imported into eooo format. File created in `.agentis/skills/`.

## Search

### TC-07-030: Global search — by keyword
**Priority:** P1
**Preconditions:** Multiple projects with skills
**Steps:**
1. Navigate to `/search`
2. Enter query: "typescript"
**Expected:** Results from all projects matching keyword in name, description, body.

### TC-07-031: Global search — filter by tags
**Priority:** P2
**Preconditions:** Skills have tags
**Steps:**
1. Search with tag filter
**Expected:** Only skills with matching tags shown.

### TC-07-032: Global search — filter by project and model
**Priority:** P2
**Preconditions:** Skills across projects with different models
**Steps:**
1. Filter by specific project
2. Filter by model
**Expected:** Results correctly narrowed by both filters.
