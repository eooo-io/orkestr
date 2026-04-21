# Compound Learning

Three features work together to grow the org's collective capability over time: **capability discovery** (what could this agent be doing?), **pattern extraction** (what should we encode from what it's already learned?), and **skill propagation** (what's working elsewhere that we should try here?).

Each is a small service with a scheduled job. Together they close the loop between runtime learning and declarative config — the thesis behind Orkestr's single-source-of-truth model.

## Capability discovery

The "imagination gap" problem: users have capabilities configured they don't realize their agents could use.

`CapabilityDiscoveryService::suggestFor(Agent)` ranks up to 6 suggestions from three sources, highest-signal first:

1. **Unused MCP servers** — the agent's project has an MCP server configured but the agent isn't wired to use it. Capability is already there; it just needs a click.
2. **Peer skills** — other agents in the same project use a skill this agent doesn't have. Social proof signal.
3. **Library starters** — library skills tagged for the agent's role, for fresh agents without peers yet.

Each suggestion has a stable `key` so users can dismiss it — the `capability_suggestion_dismissals` table records dismissals per `(user_id, agent_id, suggestion_key)` with a default 30-day expiry.

```
GET  /api/agents/{agent}/capability-suggestions
POST /api/agents/{agent}/capability-suggestions/dismiss
```

Response shape:

```json
{
  "key": "unused_mcp:42",
  "type": "unused_tool",
  "title": "Try the GitHub MCP server",
  "rationale": "This server is configured in your project but this agent isn't using it yet.",
  "example_prompt": "Using the GitHub tool, ",
  "action_url": "/agents/14#mcp-servers"
}
```

::: tip
The dashboard widget surfacing these across your owned agents is tracked as [#560](https://github.com/eooo-io/orkestr/issues/560).
:::

## Pattern extraction → skill proposals

When an agent gets told the same thing repeatedly, that feedback should become a durable skill update. `PatternExtractionService` scans `agent_memories` for recurring content and opens a `SkillUpdateProposal` when a canonical form repeats past the threshold.

### The detection heuristic

For each long-term or working memory in the last 30 days:

1. Extract plain text from the content JSON
2. **Canonicalize** — lowercase, drop stopwords + punctuation, keep tokens ≥3 chars
3. Group by canonical form
4. Open a proposal if the group has **≥3 memories**

Canonicalization means `Always use pnpm!` and `please use pnpm` collapse to the same bucket, so the frequency count aggregates.

Scans are **idempotent** — proposals are keyed on `(agent_id, pattern_key)`, so re-running the job updates the evidence list rather than duplicating.

Scheduled at **03:30 daily** via `ExtractMemoryPatternsJob`.

### The proposal inbox

`/skill-proposals` lists pending proposals scoped to agents the current user owns. Each card shows:

- Proposed title (usually the first 80 chars of the detected feedback)
- Rationale (how many memories contributed)
- Proposed body (the canonical text)
- Target skill link (if attached; otherwise disabled)

Two actions:

- **Accept → new version** — creates a new `skill_version` with the proposed body, linked back to the proposal. Replaces the skill's current body.
- **Reject (suppress 30d)** — sets `status=rejected` and `suppress_until`. The service won't reopen an identical pattern during the suppression window.

API:

```
GET  /api/skill-proposals[?skill_id=X]
GET  /api/skill-proposals/{proposal}
POST /api/skill-proposals/{proposal}/accept   (editor+)
POST /api/skill-proposals/{proposal}/reject   (editor+)
```

::: tip
The in-editor banner version of this (`ProposalBanner`) is tracked as [#561](https://github.com/eooo-io/orkestr/issues/561). The inbox is live today.
:::

## Cross-project skill propagation

When a skill proves valuable in one project, compatible projects in the same org should get a heads-up.

`SkillPropagationService::suggestPropagations` scans skills with completed eval runs, then for each sibling project in the same org:

- Skip if the target already has a same-slug skill
- If the source has `tuned_for_model`, require a target agent in the same model family (don't propose a `claude-*`-tuned skill to a gpt-only project)
- Score: `0.7 × normalized_eval_score + 0.3 × agent_present`
- Threshold: `0.4`

Successful matches write a row to `skill_propagations` with `status=suggested`. Scheduled at **04:00 daily** via `SuggestSkillPropagationsJob`.

### The propagation inbox

`/skill-propagations` lists suggestions for the user's current org, sorted by `suggestion_score`. Each card shows:

- Source skill → target project → optional target agent
- Score
- Accept / Dismiss

Accept clones the source skill into the target project with a unique slug, optionally overriding the body for project-specific context. The new skill's `modified_skill_id` points back to the propagation row.

### Lineage

When a skill was created via propagation, you can query its provenance:

```
GET /api/skills/{skill}/lineage
```

Returns `null` for skills that weren't propagated, otherwise:

```json
{
  "source_skill_id": 14,
  "source_skill_slug": "invoice-helper",
  "source_skill_name": "Invoice Helper",
  "source_project_id": 3,
  "source_project_name": "Acme Billing",
  "status": "accepted",
  "resolved_at": "2026-04-15T10:22:00Z"
}
```

::: tip
The lineage strip in the skill editor is tracked as [#562](https://github.com/eooo-io/orkestr/issues/562). The endpoint is live today.
:::

## The scheduled cadence

```
03:00  RecomputeAgentReputationJob
03:30  ExtractMemoryPatternsJob
04:00  SuggestSkillPropagationsJob
```

All three need `QUEUE_CONNECTION != sync` to run properly.

## API summary

```
# Capability discovery
GET  /api/agents/{agent}/capability-suggestions
POST /api/agents/{agent}/capability-suggestions/dismiss

# Skill proposals
GET  /api/skill-proposals
GET  /api/skill-proposals/{proposal}
POST /api/skill-proposals/{proposal}/accept      (editor+)
POST /api/skill-proposals/{proposal}/reject      (editor+)

# Propagations
GET  /api/orgs/{organization}/skill-propagations
POST /api/skill-propagations/{propagation}/accept     (editor+)
POST /api/skill-propagations/{propagation}/dismiss    (editor+)
GET  /api/skills/{skill}/lineage
```
