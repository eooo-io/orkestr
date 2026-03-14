# 03 — Skill Authoring

## Skill CRUD

### TC-03-001: Create a new skill
**Priority:** P0
**Preconditions:** Project exists, on project detail Skills tab
**Steps:**
1. Click "Add Skill"
2. Enter name: "Summarize Document"
3. Enter description, select model (claude-sonnet-4-6), set max_tokens: 1000
4. Add tags: "summarization", "documents"
5. Write prompt body in Monaco editor
6. Click Save (or Ctrl+S)
**Expected:** Skill created. Appears in skill list. File written to `.agentis/skills/summarize-document.md` on disk. Toast: "Skill saved."

### TC-03-002: Edit existing skill
**Priority:** P0
**Preconditions:** Skill exists
**Steps:**
1. Click on skill card to open editor
2. Modify the prompt body
3. Save
**Expected:** Changes persisted. Version snapshot created. File on disk updated.

### TC-03-003: Delete skill
**Priority:** P0
**Preconditions:** Skill exists
**Steps:**
1. Click delete on skill
2. Confirm
**Expected:** Skill removed from DB. File removed from disk. Removed from skill list.

### TC-03-004: Duplicate skill
**Priority:** P1
**Preconditions:** Skill exists
**Steps:**
1. Click "Duplicate" in skill editor action bar
**Expected:** New skill created with name "Summarize Document (copy)". Same body and frontmatter. Unique slug.

### TC-03-005: Create skill with duplicate name in same project
**Priority:** P2
**Preconditions:** Skill "Summarize Document" exists in project
**Steps:**
1. Create new skill with same name
**Expected:** Either auto-suffixed slug or validation error. No collision.

## Monaco Editor

### TC-03-006: Monaco editor — syntax highlighting
**Priority:** P1
**Preconditions:** Skill editor open
**Steps:**
1. Type markdown with headers, bold, lists, code blocks
**Expected:** Proper markdown syntax highlighting in editor.

### TC-03-007: Monaco editor — YAML frontmatter validation
**Priority:** P1
**Preconditions:** Skill editor open
**Steps:**
1. Manually type invalid YAML in frontmatter area (e.g., `name: [unclosed`)
**Expected:** Validation error shown. Save either blocked or warns.

### TC-03-008: Monaco editor — keyboard shortcut Ctrl+S
**Priority:** P1
**Preconditions:** Skill editor with unsaved changes
**Steps:**
1. Make a change
2. Press Ctrl+S (or Cmd+S on Mac)
**Expected:** Skill saved. Dirty indicator clears. Toast confirms save.

### TC-03-009: Monaco editor — large content performance
**Priority:** P2
**Preconditions:** None
**Steps:**
1. Paste 5000+ lines of content into editor
2. Scroll, type, save
**Expected:** No significant lag. Editor remains responsive. Save completes.

### TC-03-010: Monaco editor — undo/redo
**Priority:** P2
**Preconditions:** Editor open
**Steps:**
1. Type text
2. Ctrl+Z to undo
3. Ctrl+Shift+Z to redo
**Expected:** Standard undo/redo works within editor.

## Frontmatter Form

### TC-03-011: Frontmatter form — all fields
**Priority:** P1
**Preconditions:** Skill editor open
**Steps:**
1. Fill in: Name, Model (dropdown), Max Tokens (number), Description (textarea), Tags (multi-select/create)
**Expected:** All fields accept input. Values sync to YAML frontmatter.

### TC-03-012: Frontmatter form — tag creation
**Priority:** P1
**Preconditions:** Skill editor open
**Steps:**
1. Type a new tag name that doesn't exist
2. Confirm/create
**Expected:** Tag created globally. Applied to skill.

### TC-03-013: Frontmatter form — model selection
**Priority:** P1
**Preconditions:** API keys configured for multiple providers
**Steps:**
1. Open model dropdown
**Expected:** Shows models from all configured providers (Anthropic, OpenAI, Gemini, Ollama).

### TC-03-014: Frontmatter — includes (skill dependencies)
**Priority:** P1
**Preconditions:** Project has 3+ skills
**Steps:**
1. In skill editor, open Includes picker
2. Select 2 other skills
3. Save
**Expected:** Includes array set in frontmatter. Composed skill body includes referenced skills.

### TC-03-015: Frontmatter — circular dependency detection
**Priority:** P1
**Preconditions:** Skill A includes Skill B
**Steps:**
1. Edit Skill B, add Skill A to its includes
2. Save
**Expected:** Error: "Circular dependency detected: A → B → A." Save blocked.

### TC-03-016: Frontmatter — tools configuration
**Priority:** P2
**Preconditions:** Skill editor open
**Steps:**
1. Add tools JSON to tools field
2. Save
**Expected:** Tools stored in `skills.tools` JSON column. Validated.

### TC-03-017: Frontmatter — template variables
**Priority:** P2
**Preconditions:** Skill body contains `{{project_name}}` and `{{language}}`
**Steps:**
1. Save skill with template variables
2. Open Template Variables panel
3. Set values for each variable
**Expected:** Variables detected from body. Values saved per-project.

### TC-03-018: Frontmatter — conditions editor
**Priority:** P2
**Preconditions:** Skill editor open
**Steps:**
1. Add file pattern condition: `*.ts`
2. Add path prefix condition: `src/components`
3. Save
**Expected:** Conditions stored. Skill only included in sync when conditions match.

## Versions

### TC-03-019: Version created on save
**Priority:** P0
**Preconditions:** Skill exists
**Steps:**
1. Edit skill body
2. Save
3. Open Versions tab
**Expected:** New version snapshot appears in list with timestamp and version number.

### TC-03-020: Version history — list all versions
**Priority:** P1
**Preconditions:** Skill has been saved 5+ times
**Steps:**
1. Open Versions tab
**Expected:** All versions listed, newest first. Each shows version number, timestamp, size change.

### TC-03-021: Version diff viewer
**Priority:** P1
**Preconditions:** Skill has 2+ versions
**Steps:**
1. Open Versions tab
2. Click "Diff" on a version
**Expected:** Monaco diff editor opens showing side-by-side comparison. Additions in green, deletions in red.

### TC-03-022: Restore previous version
**Priority:** P0
**Preconditions:** Skill has previous version with different content
**Steps:**
1. Open Versions tab
2. Click "Restore" on an older version
3. Confirm
**Expected:** Skill body reverted to that version. New version snapshot created for the restore. File on disk updated.

### TC-03-023: Version — view specific version content
**Priority:** P2
**Preconditions:** Multiple versions exist
**Steps:**
1. Click on a version entry
**Expected:** Full content of that version displayed (frontmatter + body).

## Lint

### TC-03-024: Lint panel — shows issues
**Priority:** P2
**Preconditions:** Skill has lintable content (e.g., very long lines, missing description, vague instructions)
**Steps:**
1. Open Lint tab in skill editor
**Expected:** Lint issues displayed with severity (warning/suggestion), line number, message.

### TC-03-025: Lint — no issues on clean skill
**Priority:** P3
**Preconditions:** Well-formed skill
**Steps:**
1. Open Lint tab
**Expected:** "No issues found" or empty list.

## Live Test

### TC-03-026: Test skill — SSE streaming
**Priority:** P0
**Preconditions:** API key configured for selected model, skill has content
**Steps:**
1. Open Test tab in skill editor
2. Enter test input
3. Click "Run"
**Expected:** Response streams in real-time via SSE. Tokens appear incrementally. Completion indicator when done.

### TC-03-027: Test skill — token count and timing
**Priority:** P1
**Preconditions:** Test completed
**Steps:**
1. Run a test
2. Check output footer
**Expected:** Shows input tokens, output tokens, total tokens, elapsed time.

### TC-03-028: Test skill — model not configured
**Priority:** P1
**Preconditions:** Skill uses Gemini model, Gemini API key not set
**Steps:**
1. Try to run test
**Expected:** Clear error: "Gemini API key not configured. Set it in Settings." Not a generic 500.

### TC-03-029: Test skill — cancel mid-stream
**Priority:** P2
**Preconditions:** Test running
**Steps:**
1. Start a test with a long prompt
2. Click cancel/stop while streaming
**Expected:** Stream stops. Partial output preserved. No hanging state.

## Bulk Operations

### TC-03-030: Bulk select skills
**Priority:** P2
**Preconditions:** Project has 5+ skills
**Steps:**
1. Enter bulk selection mode
2. Select 3 skills via checkboxes
**Expected:** Bulk action bar appears with count "3 selected."

### TC-03-031: Bulk tag skills
**Priority:** P2
**Preconditions:** 3 skills selected
**Steps:**
1. Click "Tag" in bulk action bar
2. Select/create tag
3. Apply
**Expected:** Tag added to all 3 skills. Toast confirms.

### TC-03-032: Bulk delete skills
**Priority:** P1
**Preconditions:** 3 skills selected
**Steps:**
1. Click "Delete" in bulk action bar
2. Confirm
**Expected:** All 3 skills deleted. Files removed from disk. List updated.

### TC-03-033: Bulk move skills to another project
**Priority:** P2
**Preconditions:** 2+ projects exist, skills selected
**Steps:**
1. Click "Move"
2. Select target project
3. Confirm
**Expected:** Skills moved to target project. Removed from source. Files moved on disk.

### TC-03-034: Bulk assign skills to agent
**Priority:** P2
**Preconditions:** Skills selected, agent exists in project
**Steps:**
1. Click "Assign to Agent"
2. Select agent
3. Apply
**Expected:** Skills assigned to agent. Agent compose reflects new skills.

## AI Generation

### TC-03-035: Generate skill from description
**Priority:** P1
**Preconditions:** Anthropic API key configured
**Steps:**
1. Click "AI Generate" button
2. Enter description: "A skill that reviews TypeScript code for common anti-patterns"
3. Submit
**Expected:** Skill generated with appropriate name, description, tags, and prompt body. Opens in editor for review before saving.
