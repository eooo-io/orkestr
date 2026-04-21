# Compose Preview + Sharing

The agent compose preview shows what an agent's system prompt actually looks like after all skills, includes, variables, gotchas, and base instructions are merged. You can preview it against any model, inspect which skill contributes which section, and share a snapshot with someone outside the editor.

## Opening the preview

In the `AgentBuilder` or on the project detail page's agents tab, click **Compose** on any agent. The modal is also available from the command palette.

## Model override

The **model dropdown** in the preview header lets you re-render against any available model:

- Agent default (whatever the agent or project-override is configured for)
- Any Claude, OpenAI, Gemini, Grok, OpenRouter, or Ollama model from the model registry

Changing the model updates the token usage meter to show the percentage of that model's context window consumed. A GPT-5.4 preview with 200k tokens shows ~19%, while the same content against Claude at 200k context shows 100%.

```
{
  "target_model": "claude-sonnet-4-6",
  "model_context_window": 200000,
  "token_estimate": 38412
}
```

## Skill breakdown pane

Every composed response includes `skill_breakdown` — an ordered list of the skills that contributed, with character offsets into the final content:

```
{
  "slug": "invoice-gen",
  "name": "Invoice Generation",
  "token_estimate": 412,
  "starts_at_char": 1820,
  "ends_at_char": 3210,
  "tuned_for_model": "claude-sonnet-4-6",
  "last_validated_model": "claude-sonnet-4-6"
}
```

Hover any entry in the breakdown sidebar → the corresponding section of the content pane is highlighted. Each entry also shows the skill's `tuned_for_model` and `last_validated_model` badges so you can spot a stale skill contributing to a live preview at a glance.

## Sharing a preview

Click **Share** in the preview header. The modal has:

- **Expiry** — 1 / 7 (default) / 30 / 90 days
- **Snapshot toggle** (default on) — server freezes the current content; disabling it causes a live re-render on each public view

Submitting creates a row in `compose_share_links` and returns a public URL like `/share/compose/{uuid}`.

### Secret-scan gate

Before creating the link, the server runs the composed output through `PromptLinter::lint()` and rejects the request with **422** if anything matches the secret patterns (API keys, tokens, private keys, connection strings, etc.):

```json
{
  "error": "Refusing to create share link: composed output contains potential secrets.",
  "secrets": [
    { "rule": "secret_in_prompt", "message": "Potential Anthropic API key detected.", "line": 14 }
  ]
}
```

The modal surfaces the matching patterns inline so you know exactly which lines to fix.

## The public view

`GET /api/share/compose/{uuid}` is the first unauthenticated content-returning route beyond `/api/health` and the GitHub webhook. It's rate-limited with `throttle:30,1` (30 requests per minute per IP) and:

- Returns the frozen snapshot when `is_snapshot=true`
- Re-renders live otherwise (respecting the scope of the original creator's org context)
- `410 Gone` when expired
- `404` when missing
- Increments `access_count` and `last_accessed_at` on every hit

The matching SPA page is at `/share/compose/:uuid` — no sidebar, no auth guard. Read-only view of the content, token usage vs. target-model context window, and the per-skill breakdown. Safe to paste into Slack/email without leaking your Orkestr credentials.

## Deleting a share

Only the original creator can delete:

```
DELETE /api/share/compose/{uuid}
```

Returns `403` for anyone else.

## API summary

```
POST   /api/projects/{project}/agents/{agent}/compose/share   (editor+)
DELETE /api/share/compose/{uuid}                              (creator only)
GET    /api/share/compose/{uuid}                              (public, throttle:30,1)
```

## Security posture

See [#556](https://github.com/eooo-io/orkestr/issues/556) for the follow-up security review task. Current protections:

- Snapshot mode is the default (no re-render on public hits)
- `PromptLinter` secret-scan gate refuses creation with leakable content
- `sanitizePayload()` strips keys prefixed `secret_*` from stored payloads
- Ownership-only delete, editor-only creation
- UUIDs are v4 (122 bits of entropy)
