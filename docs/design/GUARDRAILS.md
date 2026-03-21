# Guardrails Design — Preventing Malicious Skills & Agents

> Design doc for Orkestr security guardrails.
> Created: 2026-03-12

## Threat Model

Skills are prompts that get injected into AI coding assistants. Malicious skills could:

- **Prompt injection** — Override the host AI's safety instructions ("ignore all previous rules")
- **Exfiltration instructions** — Tell the AI to leak secrets, env vars, or source code to external URLs
- **Vulnerability insertion** — Subtly instruct the AI to write insecure code (weak crypto, SQLi, backdoors)
- **Supply chain attacks** — Shared skills that look helpful but contain hidden instructions
- **Malicious tool config** — MCP servers or A2A agents pointing to attacker-controlled endpoints

## Design Principle

**Warn loudly, block rarely, log everything.**

Legitimate users get full freedom with clear visibility. Bad actors get caught at the distribution layer (library imports, bundle imports) where review is justified. Multiple lightweight layers rather than one heavy gate — each layer catches a different class of abuse while staying out of the way for legitimate use.

## Layer 1 — Structural Constraints

Zero false positives. Enforced at the data model and sync engine level.

- MCP server URLs and A2A endpoints validated against allowlists or require explicit user approval
- Tool definitions can't reference arbitrary executables
- Skills can't modify their own activation conditions at runtime
- Max prompt length caps (a 50K token skill is suspicious)
- Skill file format enforces separation of config (frontmatter) from content (body)

**Implementation:** Validation rules in `SkillController::store/update`, `McpServerController::store`, `A2aAgentController::store`. No new services needed — add validation rules to existing request handling.

## Layer 2 — Static Pattern Detection

Fast, cheap, transparent. Runs on every save.

### Patterns to detect

| Category | Examples |
|---|---|
| Prompt injection | "ignore previous instructions", "system prompt override", "you are now", role-switching attacks |
| Exfiltration | URLs in prompts, `curl`/`wget`/`fetch` to external hosts, base64-encoded payloads |
| Credential harvesting | Patterns requesting env vars, API keys, tokens to be included in output |
| Obfuscation | Base64 strings, hex-encoded content, Unicode homoglyphs |
| Unsafe code instructions | "disable CSRF", "skip validation", "eval(", "--no-verify" |

### Behavior

- **Flag, don't block.** Show warnings in the UI with explanations. Let the user override.
- Warnings displayed inline in the skill editor (similar to existing lint panel)
- Each warning includes: pattern matched, risk level (low/medium/high), explanation of why it's flagged
- User can dismiss warnings per-skill (dismissals are recorded for audit)

**Implementation:** Extend the existing `PromptLinter` service with a new `SecurityRuleSet`. Returns structured warnings alongside existing lint results. No changes to save flow — warnings are advisory.

## Layer 3 — LLM-Based Content Review

Nuanced analysis for marketplace publishing. Catches obfuscated attacks that static patterns miss.

### When it runs

- **On marketplace publish** (required, blocking above a risk threshold)
- **On-demand scan** triggered by user ("Scan this skill for risks")
- **NOT on every save** — too expensive and too slow for the editing loop

### How it works

1. Full skill content (frontmatter + body + included skills if composed) sent to a classifier prompt
2. Classifier evaluates against categories: exfiltration, injection, vulnerability insertion, social engineering
3. Returns a structured risk assessment:
   - Overall risk score (0-100)
   - Per-category findings with reasoning
   - Specific passages flagged with explanations
4. Scores above threshold require human review before marketplace listing goes live

### Classifier prompt design

- Uses a dedicated system prompt optimized for adversarial content detection
- Tested against a corpus of known-malicious and known-benign skills to calibrate thresholds
- The classifier model should be different from the model being tested (defense in depth)

**Implementation:** New `SkillGuardrailService` with `analyze(Skill $skill): GuardrailReport`. Called from `MarketplaceController::publish` and exposed via `POST /api/skills/{id}/scan`.

## Layer 4 — Marketplace Trust & Transparency

Community-driven defense layer for the distribution surface.

### Transparency report

Every published marketplace skill displays:
- What static patterns were detected (if any, with dismissal reasons)
- What tools, URLs, and external services are referenced
- What permissions/capabilities it implies (file access, network, shell)
- Diff view on updates — users see exactly what changed before auto-updating

### Trust signals

- Verified publisher badges (linked to Stripe Connect identity)
- Install count and community rating
- Community flagging with review queue
- Publisher history: how many skills published, average ratings, any prior removals

### Marketplace moderation

- Skills above LLM risk threshold are held for manual review
- Flagged skills are temporarily unlisted pending review
- Repeat offenders get publishing privileges revoked
- All moderation actions logged in audit trail

**Implementation:** Add `risk_score`, `review_status`, `transparency_report` columns to `marketplace_skills`. New `MarketplaceModerationService` for review queue management.

## Layer 5 — Runtime Guardrails

Defense in depth for the live test runner and playground.

- When testing skills, sandbox the execution: no network access from the response, no filesystem writes outside the project
- Log all test runs with full prompt/response for audit
- Rate limiting on test execution to prevent abuse
- Streaming responses can be interrupted if runtime heuristics detect suspicious output patterns

**Implementation:** Extend `SkillTestController` with rate limiting middleware. Add test execution logging to a `test_runs` table.

## What NOT To Do

These are explicit anti-patterns to avoid:

- **Don't blacklist keywords.** Blocking "hack", "exploit", "vulnerability" kills every security-focused skill. A pentesting skill is legitimate.
- **Don't restrict prompt content structurally.** Skills need to say anything — that's the entire value proposition. Guardrails live around the skill, not inside it.
- **Don't require approval for local/private skills.** Only gate marketplace publishing. Your own skills on your own machine are your business.
- **Don't break composability.** Skills that `include:` other skills shouldn't lose their ability to compose just because one layer flagged something.
- **Don't create a false sense of security.** No guardrail system is perfect. Transparency and auditability matter more than prevention theater.

## Implementation Priority

| Priority | Layer | Effort | Impact |
|---|---|---|---|
| 1 | Static pattern scanner (extend `PromptLinter`) | Low | Catches obvious attacks immediately |
| 2 | Marketplace publish gate (LLM review) | Medium | Secures the highest-risk vector |
| 3 | Transparency UI (skill report card) | Medium | Informed users are the best guardrail |
| 4 | Audit log | Medium | Essential for incident response |
| 5 | MCP/A2A endpoint validation | Low | Prevents malicious tool configs |
| 6 | Runtime sandboxing | High | Defense in depth for test runner |

## Data Model Additions

```
skill_guardrail_reports
├── id
├── skill_id (FK)
├── triggered_by (enum: save, publish, manual_scan)
├── static_warnings (JSON: [{pattern, category, severity, passage, dismissed}])
├── llm_risk_score (int 0-100, nullable)
├── llm_findings (JSON, nullable)
├── llm_model_used (string, nullable)
├── reviewed_by (FK users, nullable)
├── review_decision (enum: approved, rejected, nullable)
├── created_at
└── updated_at

marketplace_skills (additions)
├── risk_score (int, nullable)
├── review_status (enum: pending, approved, rejected, flagged)
└── transparency_report (JSON)

audit_log
├── id
├── organization_id (FK)
├── user_id (FK)
├── action (string: skill.created, skill.published, guardrail.dismissed, etc.)
├── auditable_type (morph)
├── auditable_id (morph)
├── metadata (JSON)
├── ip_address (string, nullable)
└── created_at
```
