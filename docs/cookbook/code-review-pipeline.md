# Code Review Pipeline

**Goal:** Build a workflow that automatically reviews PRs with security, code quality, and testing agents — with a human checkpoint before merging.

**Time:** 25 minutes

## Ingredients

- A running Orkestr instance with a project
- At least 3 agents enabled: Security Agent, Code Review Agent, QA Agent
- Skills assigned to each agent (see [Your First Agent Team](./first-agent-team))
- Optionally: MCP filesystem server configured for reading code

## Steps

### 1. Create the Workflow

Navigate to your project's **Workflows** tab and click **+ New Workflow**:

- **Name:** "PR Review Pipeline"
- **Trigger:** Webhook (GitHub push/PR events)

### 2. Design the DAG

On the workflow canvas, build this flow:

```
┌─────────┐
│  Start  │
└────┬────┘
     │
     ▼
┌────────────────┐
│ Parallel Split │
└──┬──────────┬──┘
   │          │
   ▼          ▼
┌────────┐  ┌────────────┐
│Security│  │Code Review │
│Agent   │  │Agent       │
└───┬────┘  └─────┬──────┘
    │             │
    ▼             ▼
┌────────────────────┐
│   Parallel Join    │
└────────┬───────────┘
         │
         ▼
┌──────────────────┐
│   QA Agent       │
│   (test analysis)│
└────────┬─────────┘
         │
         ▼
┌──────────────────────┐
│  Condition:          │
│  Any critical issues?│
└──┬────────────────┬──┘
   │ Yes            │ No
   ▼                ▼
┌──────────┐  ┌──────────────┐
│ Block &  │  │ Checkpoint:  │
│ Report   │  │ "Approve     │
│ End      │  │  merge?"     │
└──────────┘  └──────┬───────┘
                     │ Approved
                     ▼
              ┌──────────┐
              │   End    │
              │ (Approved)│
              └──────────┘
```

### 3. Configure Each Step

#### Parallel Split

No configuration needed — it just forks the flow.

#### Security Agent Step

```
Config:
  Input mapping: { "diff": "context.pr.diff", "files": "context.pr.files" }
  Output key: "security_results"
  Model override: claude-sonnet-4-6 (optional)
```

#### Code Review Agent Step

```
Config:
  Input mapping: { "diff": "context.pr.diff", "files": "context.pr.files" }
  Output key: "review_results"
```

#### Parallel Join

Waits for both Security and Code Review to complete.

#### QA Agent Step

```
Config:
  Input mapping: {
    "diff": "context.pr.diff",
    "security": "context.security_results",
    "review": "context.review_results"
  }
  Output key: "qa_results"
```

The QA Agent receives both the original diff AND the outputs from security and code review, so it can synthesize a comprehensive assessment.

#### Condition Step

```
Expression: context.security_results.findings.some(f => f.severity == 'critical')
  OR context.review_results.findings.some(f => f.severity == 'critical')
```

If any critical issues exist, the workflow goes to "Block & Report." Otherwise, it goes to the human checkpoint.

#### Checkpoint

```
Config:
  Message: "PR review complete. Security: {{security_results.summary}}.
            Code quality: {{review_results.summary}}.
            QA: {{qa_results.summary}}.
            Approve merge?"
  Approvers: project admins
```

### 4. Set Up the Webhook Trigger

Copy the workflow's webhook URL and configure it in GitHub:

1. Go to your GitHub repo → Settings → Webhooks
2. **Payload URL:** `https://your-orkestr.com/api/webhooks/github/{projectId}`
3. **Content type:** `application/json`
4. **Events:** Select "Pull requests"

When a PR is opened or updated, GitHub sends a webhook that triggers the workflow.

### 5. Validate and Activate

Click **Validate** in the workflow toolbar. The DAG validator checks:
- Exactly one Start node
- All paths lead to an End node
- No cycles
- All agent bindings are valid

If valid, toggle the workflow status to **Active**.

### 6. Test with a Real PR

Open a pull request on your repository. The workflow should:

1. Trigger automatically from the GitHub webhook
2. Run Security and Code Review in parallel
3. Pass results to QA for synthesis
4. Evaluate if any critical issues exist
5. If none, pause at the checkpoint for your approval

Watch the execution live on the workflow canvas — nodes light up as they run.

## Result

You have an automated PR review pipeline that:
- Runs security and quality checks in parallel (faster)
- Synthesizes results through a QA agent
- Blocks PRs with critical issues automatically
- Requires human approval for clean PRs
- Tracks every run with full execution traces and cost data

## Variations

- **Add more parallel agents:** Add a Performance Agent alongside Security and Code Review
- **Slack notifications:** Add an MCP server for Slack and have the final step post results
- **Auto-approve low-risk:** Add a condition that auto-approves PRs under a certain complexity threshold
