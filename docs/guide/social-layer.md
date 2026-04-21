# Agent Social Layer

When an organization has dozens of agents across multiple projects, "which agent should I ask about X?" becomes a real question. The social layer turns agents into first-class org citizens: every agent has a named owner, a reputation score, a derived specialization, and a browseable directory. Runs can be shared across the org and forked for remixing, and projects declare who plays which role.

## Agent ownership

Every `agents` row has:

| Column | What it means |
|---|---|
| `owner_user_id` | The human accountable for this agent's behavior. Backfilled from `created_by` when the phase shipped. |
| `reputation_score` | 0–100 score computed nightly. `null` when insufficient run history. |
| `reputation_last_computed_at` | Timestamp of the last recompute. |

Ownership is a trust signal. When a coworker sees output from *Billing Bot, owned by Aarav*, they know who to talk to if the output is wrong.

## Reputation formula

`AgentReputationService::calculate` is intentionally simple and auditable:

```
start at 50 (neutral)
+ (success_rate × 30)          // up to +30
- (halt_rate × 20)              // up to -20 for guardrail halts
- (failure_rate × 10)           // up to -10 for plain failures
± (review_signal × 15)          // ±15 from skill reviews, if any
clamp to [0, 100]
return 0 when fewer than 3 runs in the last 30 days
```

Agents without sufficient run history return **0** rather than a noisy score — no signal beats bad signal.

Recomputed via `RecomputeAgentReputationJob`, scheduled at **03:00 daily** in `routes/console.php`.

## The profile page

`/agents/:id/profile` surfaces the public view:

- Owner name + email
- Reputation score with last-computed timestamp
- **Domain summary** — derived from attached skills ("Specializes in: Invoice Generation, Refund Handling, …")
- Total invocation count
- Last 10 non-private runs

API:

```
GET /api/agents/{agent}/profile
```

## Agent directory + "Who should I ask?"

`/agents/directory` is a browseable grid of all agents in the user's org with a routing search at the top.

Ask "Who handles invoice refunds?" and the routing service ranks agents by:

- **0.7 × skill overlap** — fraction of question tokens present in attached skill names/descriptions/summaries
- **0.3 × past-run overlap** — fraction of tokens present in the last 30 run inputs

Question tokens are lowercased, stopword-stripped, and require ≥3 characters. Results come back with human-readable reasoning:

```json
{
  "agent_id": 14,
  "name": "Billing Bot",
  "score": 0.64,
  "reasoning": "40% skill overlap, 20% past-run overlap",
  "reputation_score": 82.5,
  "owner": { "id": 7, "name": "Aarav" }
}
```

API:

```
POST /api/agents/route
{
  "question": "Who handles invoice refunds?",
  "project_id": 14   // optional: scope to a project
}
```

## Work feed + fork

Every run has a `visibility` field: `private` (default), `team`, or `org`. Only the creator can change it:

```
PUT /api/runs/{run}/visibility  { "visibility": "org" }
```

Org-level default is controlled by `organizations.default_run_visibility`.

`/work-feed` is the chronological stream of `team` + `org` visible runs scoped to the caller's current org. Excludes `pending`/`running` runs — no in-flight noise. Each entry shows:

- Agent + owner
- Who triggered the run
- Input summary + token count
- Halt reason (if the run was stopped by a guardrail)
- **Fork** button

### Forking

Clicking **Fork** creates a new `pending` + `private` run owned by the forker, with `input` + `config` cloned from the original and `forked_from_run_id` pointing back. You can't fork someone's private run.

```
POST /api/runs/{run}/fork
```

This is the "Midjourney effect" — when runs are visible, everyone in the org gets smarter about what's possible, and can build on each other's prompts.

## Project roles (IC / DRI / coach)

The `project_role_assignments` table assigns users to one of three roles on a project:

| Role | What they own |
|---|---|
| **IC** | A capability — a discrete function of the system |
| **DRI** | A cross-cutting outcome (use the `scope` field, e.g. "merchant-churn") |
| **Coach** | Craft + people — mentors, reviews, unblocks |

A user can hold **multiple active roles per project** — e.g. IC on one scope, coach on another.

Assignments live on the Team tab of each project as a three-column role map. Adding, changing scope, and ending assignments are inline:

```
GET    /api/projects/{project}/role-assignments
POST   /api/projects/{project}/role-assignments          (editor+)
PUT    /api/projects/{project}/role-assignments/{id}     (editor+)
DELETE /api/projects/{project}/role-assignments/{id}     (editor+)
```

`DELETE` sets `ended_at` instead of removing the row — history is preserved.

### Why three roles

This is the Dorsey-style "New AI Org Chart" model. Responsibilities naturally normalize down to these three buckets, and making them explicit feeds downstream:

- **Review routing** — the Phase 7 follow-up [#559](https://github.com/eooo-io/orkestr/issues/559) wires `SkillEvalGateService` to prefer coaches in the same project for skill review assignment
- **Capability discovery** — "Which IC owns the X capability here?"
- **Trust** — seeing a skill authored by a known DRI carries weight

## Deferred

- **Filament admin resource** for role assignments — tracked in [#558](https://github.com/eooo-io/orkestr/issues/558)
- **Coach preference in review routing** — tracked in [#559](https://github.com/eooo-io/orkestr/issues/559)
