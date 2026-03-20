# Scheduled Security Scanner

**Goal:** Set up a Security Agent that runs every night at 2am, scans your codebase for vulnerabilities, and posts a summary report.

**Time:** 15 minutes

## Ingredients

- A running Orkestr instance with a project
- Security Agent enabled with security skills assigned
- MCP filesystem server configured (so the agent can read files)
- An API key for at least one LLM provider

## Steps

### 1. Configure the Security Agent

Ensure the Security Agent has:

- **Skills assigned:** Security Checklist, OWASP Top 10, Coding Standards
- **MCP Tools bound:** Filesystem server (to read source code)
- **Model:** claude-sonnet-4-6 (or your preferred model)
- **Custom Instructions:**

```markdown
## Nightly Scan Mode

When running as a scheduled scan:
1. Focus on files changed in the last 24 hours (use git log)
2. For each changed file, perform a full security review
3. Compile a summary report with:
   - Total files scanned
   - Findings by severity (critical, high, medium, low)
   - Top 5 most urgent issues with file paths and line numbers
4. If zero findings, report "All clear"
```

### 2. Set Up the Schedule

Open the Security Agent's configuration and go to the **Schedules** tab:

1. Click **+ New Schedule**
2. **Type:** Cron
3. **Expression:** `0 2 * * *` (every day at 2:00 AM)
4. **Input:**
```json
{
  "scope": "last_24_hours",
  "focus": "security",
  "output_format": "summary_report"
}
```
5. Click **Save** and toggle **Enabled** to ON

### 3. Set Budget Limits

For a nightly scan, set reasonable limits:

- **Per-run budget:** $2.00 (prevents runaway costs)
- **Max iterations:** 30 (enough for a thorough scan)
- **Timeout:** 10 minutes

### 4. Configure Notifications

Set up a webhook to receive the scan results:

Go to your project's **Webhooks** tab and create a webhook:

- **URL:** Your Slack incoming webhook URL (or email API, or any endpoint)
- **Events:** `execution.completed`
- **Filter:** Agent = "Security Agent"

Now when the nightly scan completes, the results are automatically posted.

### 5. Test It Manually

Before waiting until 2am, test the schedule manually:

1. Click **Run Now** on the schedule
2. Watch the execution in the dashboard
3. Verify the agent reads files, identifies issues, and produces a report
4. Check that the webhook fires with the results

### 6. Monitor Over Time

After a few days of scheduled runs, check the **Analytics** dashboard:

- Trend of findings over time (are they decreasing?)
- Average cost per scan
- Most common vulnerability types
- Files that repeatedly have issues

## Result

You have an autonomous security scanner that:
- Runs every night at 2am
- Reviews files changed in the last 24 hours
- Reports findings with severity levels
- Sends notifications via webhook
- Stays within budget limits
- Builds a historical record of your security posture

## Variations

- **Hourly scans of critical paths:** `0 * * * *` with scope limited to `auth/` and `payment/`
- **Weekly deep scan:** `0 3 * * 0` (Sunday 3am) with full codebase scope
- **PR-triggered:** Use webhook trigger instead of cron for on-demand scans
