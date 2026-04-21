# Prompt Linting

The prompt linter analyzes your skill's body and frontmatter for common prompt engineering issues and suggests improvements. It runs 11 rule-based checks — 8 on the body, 3 on the frontmatter — that catch vague instructions, conflicting directives, weak metadata, and structural problems.

## Using the Lint Tab

In the Skill Editor, click the **Lint** tab in the right panel. Click **Run Lint** to analyze the current skill body. Results appear as color-coded cards:

- **Yellow cards** -- Warnings that likely affect prompt quality
- **Blue cards** -- Suggestions for potential improvements

Each card shows the rule name, a description of the issue, a suggestion for how to fix it, and the line number (when applicable).

## The 8 Lint Rules

### 1. Vague Instructions

**Severity:** Warning

Detects hedge words and imprecise language that gives the model too much latitude:

- "do your best"
- "try to"
- "if possible"
- "when appropriate"
- "as needed"
- "feel free to"
- "maybe"
- "somehow"

**Fix:** Replace vague phrases with specific, actionable directives.

```markdown
<!-- Bad -->
Try to keep responses concise if possible.

<!-- Good -->
Keep responses under 200 words. Use bullet points for lists of 3+ items.
```

### 2. Weak Constraints

**Severity:** Suggestion

Flags "you should" as weaker than "you must" for critical requirements. The model treats "should" as optional guidance.

**Fix:** Use "You must" or "Always" for non-negotiable rules.

### 3. Conflicting Directives

**Severity:** Warning

Detects contradictory instructions in the same skill:

- Asking to be both concise and detailed
- Contradictory code output rules
- Conflicting output format requirements (e.g., "respond only in JSON" and "use markdown")

**Fix:** Choose one approach or add conditions that clarify when each applies.

### 4. Missing Output Format

**Severity:** Suggestion

Flags generation-oriented skills (those using words like "generate", "create", "write") that do not specify an output format.

**Fix:** Add a section like "Format your response as..." with explicit structure.

### 5. Excessive Length

**Severity:** Warning (over 5,000 tokens) or Suggestion (over 2,000 tokens)

Very long prompts can dilute the model's focus. Token count is estimated at approximately 1 token per 4 characters.

**Fix:** Split into smaller skills and use the [includes system](./includes) to compose them.

### 6. Role Confusion

**Severity:** Warning

Flags skills that define more than 2 different roles (e.g., "You are a ..." appearing 3+ times). Multiple role assignments can confuse the model about which persona to adopt.

**Fix:** Focus on a single role per skill, or use clearly separated sections.

### 7. Missing Examples

**Severity:** Suggestion

Flags skills that mention complexity (words like "complex", "nuanced", "edge case", "ambiguous", "multi-step") but do not include any examples.

**Fix:** Add few-shot examples to clarify expected behavior.

### 8. Redundancy

**Severity:** Suggestion

Detects lines that are more than 85% similar to other lines in the same skill. Repeated instructions waste tokens without adding value.

**Fix:** Remove the duplicate instruction.

## Frontmatter rules (`lintSkill`)

When the linter runs against a full `Skill` (not just a body string), it also checks the skill's metadata. These rules were added in Phase 0 alongside the progressive-disclosure structural check.

### 9. Missing Summary

**Severity:** Suggestion

Fires when `summary` is empty. The summary is the tier-1 progressive-disclosure hint — without it, agent context indexes fall back to the longer `description` and waste tokens.

**Fix:** Add a one-line summary (≤500 chars) describing when to use this skill.

### 10. Missing Description

**Severity:** Warning

Fires when `description` is empty, under 20 chars, contains a vague word (`stuff`, `things`, `various`, `general`, `misc`), or lacks an actionable verb (generate, analyze, review, summarize, etc.). Agents use the description to decide whether to invoke the skill — vague descriptions mean the skill never triggers when needed.

**Fix:** Write a concrete description with an action verb and trigger conditions.

### 11. No Progressive Disclosure

**Severity:** Suggestion

Fires when the body is >500 chars and has no `##` or `###` heading in the first 40 lines. Long skills without section headings force the model to read everything before deciding what matters.

**Fix:** Add section headings so the model can skim before committing to details.

## Linting via API

You can lint a skill programmatically:

```
GET /api/skills/{id}/lint
```

Returns an array of issues:

```json
[
  {
    "severity": "warning",
    "rule": "vague_instruction",
    "message": "Vague instruction detected.",
    "suggestion": "Replace \"try to\" with a direct instruction.",
    "line": 5
  }
]
```
