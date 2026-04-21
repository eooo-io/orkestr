# Model Staleness Tracking

Skills are tuned for specific models. When a model is deprecated, or when a skill's behavior hasn't been validated against a recent change, Orkestr surfaces that drift before it silently degrades your agent.

## The three signals

Every skill has three model-related fields:

| Field | What it means |
|---|---|
| `model` | The model the skill *runs* against at compose time (from frontmatter). |
| `tuned_for_model` | The model the author *designed* for — captured on first save, overrideable any time. |
| `last_validated_model` / `last_validated_at` | The model + timestamp of the most recent successful eval run. |

`tuned_for_model` is authorial intent. `last_validated_*` is evidence. When they diverge, the skill is stale.

## Reasons, in priority order

The staleness service returns one of four reasons:

| Reason | Trigger | Color |
|---|---|---|
| `model_deprecated` | `tuned_for_model` appears in the deprecated list (retired Claude 3.x, GPT-4/o1, Gemini 1.5/2.x, Grok 2). | Red |
| `needs_tuning` | `tuned_for_model` is null — the skill has never been pinned to a baseline. | Neutral |
| `needs_revalidation` | Never validated, or `last_validated_model` differs from `tuned_for_model`, or the current `model` differs from `tuned_for_model`. | Yellow |
| `ok` | Tuned, validated on the same model, current use matches. | Hidden |

`model_deprecated` always wins. If your skill is tuned for `gpt-4` and you validated it yesterday, it's still flagged red — the model itself is retired.

## The `StalenessBanner`

Shows above the Monaco editor, next to the inline gotcha strip. Hidden when status is `ok`. Refetches on save.

- **Red** — model is deprecated. Retune for a current model.
- **Yellow** — needs revalidation. Run an eval suite against the tuned-for model.
- **Neutral** — needs tuning. Pick a baseline so changes can be tracked against it.

## Setting the tuned-for model

The frontmatter form has a **"Tuned for model"** dropdown. It writes through `PUT /api/skills/{id}/staleness` — separate from the skill body edit, so it doesn't create a version snapshot by itself.

On a skill's **first save**, the frontmatter `model` is auto-copied into `tuned_for_model` if it's null. On subsequent saves, if `tuned_for_model` is still null and `model` is populated, the same auto-seed runs. You can override any time via the dropdown.

## Version history shows the baseline

Each entry in `VersionHistoryPanel` shows the `tuned_for_model` that was active when the version was saved. This matters for revalidating old versions — the baseline is frozen per snapshot.

## Revalidation flow

1. See yellow banner → model drift detected
2. Open the **Evals** tab → run an eval suite against the current `tuned_for_model`
3. When the run completes, `last_validated_*` updates on the skill
4. Banner hides (or upgrades to red if the run had poor results, depending on your gate config — see [Eval Gates](./eval-gates))

## Deprecated model list

Maintained as a class constant on `SkillStalenessService::DEPRECATED_MODELS`. Currently covers:

- Claude 3.x (Opus/Sonnet/Haiku including `claude-3-5-*` and `claude-sonnet-4-5` / `claude-opus-4-5`)
- GPT-3.5, GPT-4, GPT-4 Turbo, GPT-4o, GPT-4o-mini, o1-preview, o1-mini
- Gemini 1.5 Pro/Flash, Gemini 2.0 Pro, Gemini 2.5 Pro, `gemini-pro`
- Grok 2, Grok beta

Update this list when a model is retired; any skill whose `tuned_for_model` matches will flip to red on the next staleness check.

## API

```
GET  /api/skills/{skill}/staleness?current_model=claude-sonnet-4-6
PUT  /api/skills/{skill}/staleness    # { "tuned_for_model": "claude-opus-4-6" }
```

`current_model` is optional. Pass it to detect the "tuned for X but you're running it on Y" case.
