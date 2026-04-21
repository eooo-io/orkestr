# Eval Regression Gates

A skill edit can silently degrade the behavior it encodes. Eval regression gates catch that: every time you save, configured eval suites run against the new version, compare to the last-known-good baseline, and surface the delta. The gate can also block provider sync when a skill has failed a recent eval.

## ⚠️ Queue configuration

**`QUEUE_CONNECTION=sync` is not safe with eval gates.** Eval runs take 30s–10min and would block the HTTP request in sync mode. Use `database` (default in `.env.example`) or `redis`.

## Scoring

Scoring happens through a `ScorerInterface`. Two implementations ship:

| Scorer | Column value | Description |
|---|---|---|
| `KeywordScorer` | `keyword` (default) | Extracts non-stopword keywords from `expected_behavior`, scores 0–100 based on presence in the response. Deterministic, reproducible, cheap. |
| `LlmJudgeScorer` | `llm_judge` | Opt-in. Makes a second LLM call that grades against `grading_criteria` and returns strict JSON. Non-deterministic; costs a real API call. |

Pick one per suite via the `skill_eval_suites.scorer` column. `KeywordScorer` is the default because gate decisions need to be reproducible.

The scorer result shape:

```json
{
  "score": 82,
  "reasoning": "Matched 7 of 8 expected keywords.",
  "signals": {
    "scorer": "keyword",
    "expected_keywords": ["widgets", "gears", "sprockets", "..."],
    "matched": ["widgets", "gears", "..."],
    "missing": ["cogs"]
  }
}
```

## Queued execution

`EvalSuiteController::run` creates a pending `SkillEvalRun` row and dispatches `RunEvalSuiteJob` — the HTTP request returns immediately. The frontend polls `GET /api/eval-runs/{id}` via the `useEvalRunStatus(runId)` hook (3s interval) until the run leaves `pending`/`running`.

The job stamps each run with the active `skill_version_id` at dispatch time, so revalidating an old version knows exactly which snapshot was graded.

## Baselines

**Baseline = most recent completed run for `(suite, model)`, regardless of which version it ran against.**

This is intentional: revalidating the same version twice shouldn't zero out your delta. The baseline is "last known good" for a `(suite, model)` pair, not "prior version".

```php
$baseline = $gateService->findBaselineFor($skill, $suite, 'claude-sonnet-4-6');
$delta = $gateService->computeDelta($currentRun, $baseline);
// { overall_delta: -7.5, per_prompt: [...] }
```

## Configuring a gate

The `GateConfigPanel` at the top of the skill's **Evals** tab:

- **Enabled** — master switch
- **Required suites** — which suites count toward the gate
- **Fail threshold delta** — default `-5.00`. A run whose `delta_score` falls below this is considered a failure
- **Auto-run on save** — dispatches runs automatically after every skill save
- **Block sync on failure** — `ProviderSyncService::canSync` returns false for any skill whose latest run breaches the threshold

Settings live in `skill_eval_gates` (one row per skill, `HasOne Skill::evalGate`).

## Running the gate

On every skill save, `SkillController::update` calls `SkillEvalGateService::evaluateSkillSave`:

```json
{
  "reason": "dispatched",
  "enqueued_run_ids": [142, 143],
  "baseline_info": [
    {
      "suite_id": 7,
      "suite_name": "Pricing behavior",
      "run_id": 142,
      "baseline_run_id": 110,
      "baseline_score": 88
    }
  ],
  "est_duration_seconds": 60
}
```

`reason` can also be `no_gate`, `disabled`, `no_required_suites`, or `no_target_model`.

The decision ships back in the `SkillResource` response as `additional.gate_decision` so the SPA can toast and register a pending gate.

## The banner

`RegressionGateBanner` mounts above Monaco next to the staleness + gotcha strips. It polls `/api/skills/{id}/eval-gate/status` and renders four states:

- **Queued** — neutral. Runs dispatched, waiting for a worker.
- **Running (n/m)** — blue/neutral with progress.
- **Completed Δ +X.Y** — neutral green/grey, score held or improved.
- **Completed Δ −X.Y ⚠** — red. Threshold breached. Includes a "see delta" link that opens `RegressionDeltaModal` with a per-prompt `baseline vs current` table.

Pending gates are tracked in the Zustand `pendingEvalGates` slice keyed by `skillId` so navigating away and back doesn't lose the banner.

## Blocking sync

When `block_sync=true` and a skill's latest run has `delta_score < fail_threshold_delta`, `ProviderSyncService::syncProject` throws `EvalGateBlockedException`, which renders as **409 Conflict**:

```json
{
  "error": "Sync blocked by one or more skill eval gates.",
  "blocked_skills": [
    {
      "skill_id": 42,
      "skill_slug": "invoice-helper",
      "skill_name": "Invoice Helper",
      "last_delta": -8.50,
      "last_run_id": 118
    }
  ]
}
```

The SPA surfaces these entries in a modal with links to each blocked skill's Evals tab.

## API summary

```
GET   /api/skills/{skill}/eval-gate
PUT   /api/skills/{skill}/eval-gate               (editor+)
POST  /api/skills/{skill}/eval-gate/run-now       (editor+)
GET   /api/skills/{skill}/eval-gate/status
GET   /api/eval-runs/{eval_run}                   (used by polling hook)
```

## Deferred

Org-level monthly eval budget cap is tracked in [#557](https://github.com/eooo-io/orkestr/issues/557). It will skip auto-run-on-save when the org exceeds its monthly spend, with a banner warning.
