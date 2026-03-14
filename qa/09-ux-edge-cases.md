# 09 — UX, Performance & Edge Cases

## Navigation

### TC-09-001: Sidebar navigation — all links work
**Priority:** P0
**Preconditions:** Logged in
**Steps:**
1. Click each sidebar link: Projects, Agents, Library, Search, Settings, Billing
**Expected:** Each navigates to correct page. Active state highlights current page.

### TC-09-002: Command palette — Ctrl+K
**Priority:** P1
**Preconditions:** Logged in, on any page
**Steps:**
1. Press Ctrl+K (or Cmd+K on Mac)
2. Type "code review"
3. Select a result
**Expected:** Palette opens with fuzzy search. Results include skills, projects, pages. Selection navigates to item.

### TC-09-003: Command palette — navigation commands
**Priority:** P2
**Preconditions:** Command palette open
**Steps:**
1. Type "projects" or "settings"
**Expected:** Navigation options appear. Selecting one navigates to that page.

### TC-09-004: Browser back/forward navigation
**Priority:** P1
**Preconditions:** Navigated through several pages
**Steps:**
1. Click browser back button
2. Click browser forward button
**Expected:** Navigation works correctly. No blank pages. State preserved.

### TC-09-005: Deep link — direct URL access
**Priority:** P1
**Preconditions:** Logged in
**Steps:**
1. Paste `/projects/1` directly in browser
2. Paste `/skills/5` directly
3. Paste `/agents/3` directly
**Expected:** Each page loads correctly with data. No redirect loops.

### TC-09-006: 404 handling — invalid routes
**Priority:** P2
**Preconditions:** Logged in
**Steps:**
1. Navigate to `/nonexistent-page`
2. Navigate to `/projects/99999` (non-existent ID)
**Expected:** 404 page or meaningful error. Not a blank screen or raw error.

## Unsaved Changes

### TC-09-007: Skill editor — unsaved changes guard
**Priority:** P1
**Preconditions:** Skill editor with unsaved changes
**Steps:**
1. Make a change (dirty state)
2. Try to navigate away via sidebar
**Expected:** Warning: "You have unsaved changes. Leave anyway?" Confirm/Cancel options.

### TC-09-008: Skill editor — browser close guard
**Priority:** P1
**Preconditions:** Unsaved changes in editor
**Steps:**
1. Try to close browser tab
**Expected:** Browser's native "Leave site?" confirmation dialog appears.

### TC-09-009: Agent builder — unsaved changes
**Priority:** P2
**Preconditions:** Agent builder with modifications
**Steps:**
1. Modify agent config
2. Navigate away
**Expected:** Warning about unsaved changes.

## Toast Notifications

### TC-09-010: Success toast — skill saved
**Priority:** P2
**Preconditions:** None
**Steps:**
1. Save a skill
**Expected:** Green success toast: "Skill saved." Auto-dismisses after ~3 seconds.

### TC-09-011: Error toast — API failure
**Priority:** P1
**Preconditions:** None
**Steps:**
1. Trigger an API error (e.g., save skill while server is down)
**Expected:** Red error toast with meaningful message. Not a raw error dump.

### TC-09-012: Toast — multiple toasts stack
**Priority:** P3
**Preconditions:** None
**Steps:**
1. Trigger multiple actions rapidly (save, then delete)
**Expected:** Toasts stack vertically. Each dismisses independently.

## Responsive Design

### TC-09-013: Sidebar — collapses on mobile
**Priority:** P1
**Preconditions:** Screen width < 768px
**Steps:**
1. View app on mobile viewport
**Expected:** Sidebar collapses to hamburger menu. Content takes full width.

### TC-09-014: Skill editor — mobile layout
**Priority:** P2
**Preconditions:** Mobile viewport
**Steps:**
1. Open skill editor
**Expected:** Frontmatter form and Monaco editor stack vertically. Usable (though not ideal).

### TC-09-015: Workflow builder — small screen
**Priority:** P2
**Preconditions:** Tablet viewport (768–1024px)
**Steps:**
1. Open workflow builder
**Expected:** Canvas renders. Properties panel may overlay. Basic functionality works.

### TC-09-016: Project cards — responsive grid
**Priority:** P2
**Preconditions:** Multiple projects
**Steps:**
1. Resize browser from wide to narrow
**Expected:** Grid adapts: 3 columns → 2 columns → 1 column. Cards don't overflow.

## Dark/Light Mode

### TC-09-017: Theme toggle — app-wide
**Priority:** P2
**Preconditions:** Logged in
**Steps:**
1. Toggle theme (if available in app, not just landing page)
**Expected:** All pages switch correctly. Monaco editor theme updates. No unreadable text. Charts/graphs adapt.

### TC-09-018: Theme persistence
**Priority:** P3
**Preconditions:** Theme toggled
**Steps:**
1. Switch to dark mode
2. Refresh page
**Expected:** Theme persists across refresh (stored in localStorage or cookie).

## Performance

### TC-09-019: Project with 50+ skills — load time
**Priority:** P1
**Preconditions:** Project has 50+ skills
**Steps:**
1. Navigate to project detail
2. Click Skills tab
**Expected:** Skills load within 2 seconds. No UI freeze. Pagination or virtual scroll if needed.

### TC-09-020: Search — large dataset performance
**Priority:** P2
**Preconditions:** 100+ skills across projects
**Steps:**
1. Run search query
**Expected:** Results return within 1 second. FULLTEXT index used.

### TC-09-021: Workflow builder — 20+ nodes
**Priority:** P2
**Preconditions:** Workflow with 20+ nodes and 30+ edges
**Steps:**
1. Open workflow builder
2. Pan, zoom, select nodes
**Expected:** React Flow remains responsive. No significant lag on interactions.

## Edge Cases

### TC-09-022: Concurrent edits — same skill
**Priority:** P2
**Preconditions:** Same skill open in two browser tabs
**Steps:**
1. Edit skill in tab 1, save
2. Edit skill in tab 2 (stale), save
**Expected:** Either last-write-wins with version snapshot, or conflict detection with merge option. No silent data loss.
