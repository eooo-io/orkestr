# Schedules

Orkestr lets you run agents automatically on a schedule or in response to events. Schedules are configured per project and tied to a specific agent.

## Creating a Schedule

Navigate to your project and open the **Schedules** section. Click **Add Schedule** and configure:

| Field | Description |
|---|---|
| **Name** | A descriptive name (e.g., "Nightly code review") |
| **Agent** | The agent to execute |
| **Trigger type** | `cron`, `webhook`, or `event` |
| **Input template** | JSON template for the agent's input |
| **Max retries** | Number of consecutive failures before auto-disable (0 = unlimited) |
| **Timezone** | Timezone for cron evaluation (default: UTC) |

## Trigger Types

### Cron Schedules

Cron schedules run agents on a recurring basis using standard cron expressions.

```json
{
  "name": "Daily security scan",
  "agent_id": 3,
  "trigger_type": "cron",
  "cron_expression": "0 2 * * *",
  "timezone": "America/New_York",
  "input_template": {
    "task": "Run a security review of all skills modified in the last 24 hours"
  }
}
```

Common cron patterns:

| Expression | Meaning |
|---|---|
| `0 * * * *` | Every hour |
| `0 9 * * 1-5` | Weekdays at 9 AM |
| `0 2 * * *` | Daily at 2 AM |
| `0 0 * * 0` | Weekly on Sunday at midnight |
| `0 0 1 * *` | First day of each month |

::: tip
Orkestr uses the `dragonmantank/cron-expression` library for parsing, which supports standard five-field cron syntax. The `next_run_at` timestamp is computed automatically after each run.
:::

### Webhook Triggers

Webhook schedules fire when an external system sends a POST request to a unique webhook URL. Each webhook schedule gets an auto-generated token.

```json
{
  "name": "On PR merge",
  "agent_id": 5,
  "trigger_type": "webhook",
  "input_template": {
    "task": "Review the merged changes and update documentation"
  }
}
```

After creation, the schedule provides a webhook URL and token. Configure your CI/CD system or external service to POST to that URL with the token in the `Authorization` header.

### Event Triggers

Event schedules respond to internal Orkestr events -- such as a skill being updated, a sync completing, or a guardrail violation being detected.

```json
{
  "name": "Lint on skill save",
  "agent_id": 2,
  "trigger_type": "event",
  "event_name": "skill.updated",
  "event_filters": {
    "project_id": 1
  },
  "input_template": {
    "task": "Lint the updated skill and suggest improvements",
    "skill_id": "{{event.skill_id}}"
  }
}
```

::: warning
Event triggers use template variables from the event payload. Wrap dynamic values in `{{event.field_name}}` syntax within the input template.
:::

## Managing Schedules

### Enable / Disable

Toggle a schedule on or off without deleting it. Disabled schedules are skipped during cron evaluation and do not respond to webhooks or events.

### Monitoring

Each schedule tracks:

| Field | Description |
|---|---|
| `is_enabled` | Whether the schedule is active |
| `last_run_at` | Timestamp of the most recent execution |
| `next_run_at` | Computed next execution time (cron only) |
| `run_count` | Total number of times this schedule has fired |
| `failure_count` | Consecutive failures since last success |
| `last_error` | Error message from the most recent failure |

### Automatic Disable on Failure

If `max_retries` is set to a value greater than zero, the schedule automatically disables itself after that many consecutive failures. This prevents a broken schedule from running indefinitely.

When a schedule succeeds, the `failure_count` resets to zero.

::: tip
Set `max_retries` to 3 or 5 for production schedules. This gives transient errors a chance to resolve while catching persistent problems early.
:::

## Execution History

Every schedule run creates an `ExecutionRun` linked back to the schedule via `schedule_id`. View the execution history to see:

- Which runs succeeded and which failed
- Token usage and cost per run
- Full execution traces (see [Execution & Replay](./execution))

## API Reference

```
# Schedule CRUD is managed through the agent schedules endpoints
# Schedules are scoped to a project and agent

# Cron schedules are evaluated by the Laravel scheduler
# Webhook schedules respond to inbound HTTP requests
# Event schedules fire on internal Orkestr events
```

## Best Practices

1. **Start with long intervals** -- begin with daily or weekly schedules and increase frequency once you have confidence in the agent's behavior.

2. **Use specific input templates** -- vague inputs lead to unpredictable agent behavior. Be explicit about what the agent should do.

3. **Monitor failure counts** -- check the schedule dashboard regularly. A rising failure count often indicates a configuration issue or an unreachable MCP server.

4. **Pair with guardrails** -- scheduled agents run unattended. Always apply appropriate guardrail policies to limit what they can do without human oversight.
