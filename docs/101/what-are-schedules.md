# What are Schedules?

## The One-Sentence Answer

Schedules let agents run automatically at specific times or in response to events, without anyone clicking "Run."

## The Analogy: Alarm Clocks and Doorbells

You use an alarm clock to wake up at the same time every day — that's a **scheduled trigger**. A doorbell rings when someone arrives — that's an **event trigger**. You don't need to manually check either; they happen automatically.

Agent schedules work the same way:
- **Cron schedules** — "Run the security scan every night at 2am"
- **Webhook triggers** — "Run the code review agent when a PR is opened"

## Cron Schedules

A cron schedule runs an agent or workflow at a recurring time. You define it with a cron expression:

| Schedule | Cron Expression | Meaning |
|---|---|---|
| Every hour | `0 * * * *` | At minute 0 of every hour |
| Every night at 2am | `0 2 * * *` | At 2:00 AM every day |
| Every Monday at 9am | `0 9 * * 1` | At 9:00 AM every Monday |
| Every 15 minutes | `*/15 * * * *` | Every 15 minutes |

### Use Cases

- **Nightly security scan** — The Security Agent reviews all recent changes
- **Weekly status report** — The PM Agent compiles a sprint summary
- **Hourly monitoring** — The Infrastructure Agent checks system health
- **Daily dependency check** — The QA Agent scans for vulnerable packages

## Webhook Triggers

A webhook trigger runs an agent when an external system sends an HTTP request:

```
External System (GitHub)
    │
    │  POST /api/webhooks/github/{projectId}
    │  { event: "pull_request", action: "opened", pr: 42 }
    │
    ▼
Orkestr
    │
    │  Matches trigger: "On PR opened → run Security Agent"
    │
    ▼
Security Agent executes with PR #42 as input
```

### Common Webhook Sources

| Source | Event | Agent Action |
|---|---|---|
| GitHub | PR opened | Run code review |
| GitHub | Push to main | Run security scan |
| Slack | Message in channel | Respond as AI assistant |
| Jira | Issue created | Generate implementation plan |
| PagerDuty | Alert fired | Run incident response workflow |

## Setting Up Schedules

### In the UI

1. Open the agent or workflow you want to schedule
2. Go to the Schedules tab
3. Choose trigger type: **Cron** or **Webhook**
4. For Cron: select the frequency with the visual schedule builder
5. For Webhook: copy the webhook URL and configure it in the external system

### Via API

```
POST /api/projects/{id}/agents/{agentId}/schedules
{
  "type": "cron",
  "expression": "0 2 * * *",
  "enabled": true,
  "input": { "scope": "last_24_hours" }
}
```

## Schedule Management

- **Enable/disable** — Toggle schedules without deleting them
- **Execution history** — See when each scheduled run happened and its result
- **Failure handling** — Get notified when a scheduled run fails
- **Overlap prevention** — Don't start a new run if the previous one is still going

## Key Takeaway

Schedules turn agents from on-demand tools into always-on systems. Cron schedules handle recurring tasks; webhook triggers respond to real-time events. Combined with guardrails and observability, scheduled agents can operate autonomously and reliably.

---

**Next:** [What are Projects?](./what-are-projects) →
