# 02 — Project Management

## Project CRUD

### TC-02-001: Create a new project
**Priority:** P0
**Preconditions:** Logged in, on `/projects`
**Steps:**
1. Click "New Project"
2. Enter name: "Test Project", path: a valid directory path
3. Select providers: Claude, Cursor
4. Submit
**Expected:** Project created, appears in project list. Redirected to project detail or list.

### TC-02-002: Create project with duplicate name
**Priority:** P2
**Preconditions:** Project "Test Project" exists
**Steps:**
1. Create another project with name "Test Project"
**Expected:** Either allowed (names aren't unique) or validation error. Verify behavior is intentional.

### TC-02-003: Create project with invalid path
**Priority:** P1
**Preconditions:** Logged in
**Steps:**
1. Enter path: `/nonexistent/path/that/doesnt/exist`
2. Submit
**Expected:** Validation error or warning about path not existing.

### TC-02-004: Edit project name and description
**Priority:** P1
**Preconditions:** Project exists
**Steps:**
1. Navigate to project settings (`/projects/:id/settings`)
2. Change name and description
3. Save
**Expected:** Changes persisted. Project list shows updated name.

### TC-02-005: Delete project with confirmation
**Priority:** P0
**Preconditions:** Project exists with skills
**Steps:**
1. Click delete on project card
2. Confirm in modal
**Expected:** Project deleted. Skills associated with project deleted. Redirected to project list. Toast confirms deletion.

### TC-02-006: Delete project — cancel confirmation
**Priority:** P2
**Preconditions:** Project exists
**Steps:**
1. Click delete
2. Click cancel in confirmation modal
**Expected:** Project still exists. No changes.

### TC-02-007: Project list — empty state
**Priority:** P2
**Preconditions:** No projects exist
**Steps:**
1. Navigate to `/projects`
**Expected:** Empty state with helpful message and "Create Project" CTA.

### TC-02-008: Project list — multiple projects display
**Priority:** P1
**Preconditions:** 5+ projects exist
**Steps:**
1. Navigate to `/projects`
**Expected:** All projects displayed as cards with name, description, path, provider badges, skill count.

## Project Detail

### TC-02-009: Project detail — tabs render
**Priority:** P0
**Preconditions:** Project exists with skills and agents
**Steps:**
1. Navigate to `/projects/:id`
2. Click each tab: Agents, Skills, MCP, A2A, OpenClaw, Visualize
**Expected:** Each tab loads without error. Content is correct for each.

### TC-02-010: Skills tab — list/grid view toggle
**Priority:** P3
**Preconditions:** Project has 3+ skills
**Steps:**
1. On Skills tab, toggle between grid and list view
**Expected:** View switches. Skill data remains the same. Preference persists.

### TC-02-011: Skills tab — skill cards display correctly
**Priority:** P1
**Preconditions:** Project has skills with tags, descriptions, models
**Steps:**
1. View skills in grid/list
**Expected:** Each card shows name, description (truncated), tags, model badge, last updated.

## Scan

### TC-02-012: Scan project — discovers new skills
**Priority:** P0
**Preconditions:** Project path has `.orkestr/skills/` directory with YAML+MD skill files
**Steps:**
1. Add a new skill file to `.orkestr/skills/` on disk
2. Click "Scan" button
**Expected:** New skill discovered and imported. Toast: "Scan complete: 1 new skill found." Skill appears in list.

### TC-02-013: Scan project — detects modified skills
**Priority:** P1
**Preconditions:** Existing skill on disk has been edited outside the app
**Steps:**
1. Edit a skill's markdown body directly on disk
2. Click "Scan"
**Expected:** Skill updated in DB. Version snapshot created.

### TC-02-014: Scan project — detects deleted skills
**Priority:** P1
**Preconditions:** A skill file has been deleted from disk
**Steps:**
1. Delete a `.orkestr/skills/xxx.md` file from disk
2. Click "Scan"
**Expected:** Skill marked as deleted or removed. User notified.

### TC-02-015: Scan project — invalid path
**Priority:** P1
**Preconditions:** Project path no longer exists on disk
**Steps:**
1. Click "Scan"
**Expected:** Error message: path not found. No crash.

## Sync

### TC-02-016: Sync project — Claude provider
**Priority:** P0
**Preconditions:** Project has Claude provider enabled and 2+ skills
**Steps:**
1. Click "Sync"
**Expected:** `.claude/CLAUDE.md` generated at project path. Contains all skills under H2 headings. Toast: "Sync complete."

### TC-02-017: Sync project — Cursor provider
**Priority:** P0
**Preconditions:** Cursor provider enabled
**Steps:**
1. Sync
**Expected:** `.cursor/rules/{slug}.mdc` files generated, one per skill. MDC format correct.

### TC-02-018: Sync project — all 6 providers simultaneously
**Priority:** P1
**Preconditions:** All 6 providers enabled (Claude, Cursor, Copilot, Windsurf, Cline, OpenAI)
**Steps:**
1. Sync
**Expected:** All 6 output paths generated correctly. No file conflicts. Each in correct format.

### TC-02-019: Sync preview — shows diff before writing
**Priority:** P1
**Preconditions:** Project has been synced before, skills have changed
**Steps:**
1. Click "Preview" (sync preview button)
**Expected:** Modal shows files to be added/modified/unchanged/deleted. User can review before committing.

### TC-02-020: Sync project — empty project (no skills)
**Priority:** P2
**Preconditions:** Project has no skills
**Steps:**
1. Click "Sync"
**Expected:** Either generates empty files or shows "No skills to sync" message. No error.

## Project Settings

### TC-02-021: Enable/disable providers
**Priority:** P1
**Preconditions:** Project exists
**Steps:**
1. Navigate to project settings
2. Disable Claude provider
3. Enable Windsurf provider
4. Save
**Expected:** Provider changes saved. Next sync reflects new provider set.

### TC-02-022: Repository integration — connect GitHub
**Priority:** P2
**Preconditions:** Valid GitHub access token configured in settings
**Steps:**
1. In project settings, go to repository section
2. Enter repo URL and branch
3. Connect
**Expected:** Repository connected. Status shows "Connected." Branch info displayed.

### TC-02-023: Repository integration — auto-scan on push
**Priority:** P2
**Preconditions:** Repository connected, auto-scan enabled
**Steps:**
1. Enable "Auto-scan on push"
2. Trigger a webhook push event
**Expected:** Project scan runs automatically. New/changed skills imported.

### TC-02-024: Webhook management — create webhook
**Priority:** P2
**Preconditions:** Project exists
**Steps:**
1. In project settings, add webhook URL
2. Select events to subscribe to
3. Save
**Expected:** Webhook created. Secret generated. Shows in webhook list.

### TC-02-025: Webhook management — test delivery
**Priority:** P2
**Preconditions:** Webhook exists
**Steps:**
1. Click "Test" on webhook
**Expected:** Test delivery sent. Delivery shows in delivery log with response status.

## Visualization

### TC-02-026: Visualize tab — renders graph
**Priority:** P2
**Preconditions:** Project has skills with includes/dependencies and agents
**Steps:**
1. Click Visualize tab
**Expected:** D3 graph renders showing skills, agents, providers, MCP servers. Nodes draggable.

### TC-02-027: Full project visualization — full screen
**Priority:** P3
**Preconditions:** Project has skills and agents
**Steps:**
1. Navigate to `/projects/:id/visualize`
**Expected:** Full-screen graph renders. Zoom/pan controls work. Back button returns to project.

### TC-02-028: Visualization — empty project
**Priority:** P3
**Preconditions:** Project has no skills or agents
**Steps:**
1. Open visualization
**Expected:** Empty state or minimal graph. No crash.
