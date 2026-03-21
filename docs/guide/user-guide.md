# User Guide: Build Your First Agent

This guide walks you through the core flow of eooo.ai — from creating a project to running your first agent.

## Step 1: Create a Project

A project is a container for your agents, skills, and workflows. Think of it as a workspace for a specific product, team, or use case.

1. Navigate to **Projects** in the sidebar
2. Click **New Project**
3. Fill in the basics:
   - **Name** — e.g., "Content Pipeline"
   - **Description** — what this project is about
   - **Icon** — pick an emoji to identify it quickly
   - **Color** — assign a color for visual distinction
4. Configure defaults:
   - **Default Model** — the LLM your agents will use (e.g., `claude-sonnet-4-6`, `gpt-4o`). Models are grouped by provider in the dropdown.
   - **Environment** — Development, Staging, or Production. This is a label for organizing your work.
   - **Monthly Budget** — optional USD spending cap across all agents in this project.
5. Click **Save**

::: tip Advanced: Filesystem Path
If you want to sync skills to a local directory (for use with Claude Code, Cursor, etc.), expand the **Advanced** section and set a filesystem path. This enables the legacy provider sync feature from Skillr.
:::

## Step 2: Create an Agent

Agents are the core of eooo.ai. An agent has an identity, a goal, a reasoning strategy, and permissions.

1. From your project, go to the **Agents** tab
2. Click **New Agent**
3. Configure the **Identity** section:
   - **Name** — e.g., "Code Reviewer"
   - **Role** — a short slug like `code-reviewer`
   - **Model** — override the project default if needed
   - **Base Instructions** — the core prompt that tells the agent what it is and how to behave

   ```
   You are a senior code reviewer. You analyze pull requests for
   correctness, security vulnerabilities, and adherence to team
   conventions. Be concise and actionable in your feedback.
   ```
4. Configure the **Goal** section:
   - **Objective** — what the agent is trying to achieve
   - **Success Criteria** — one per line, e.g., `no_security_issues`, `all_tests_passing`
   - **Max Iterations** — how many reasoning loops before stopping
5. Choose a **Reasoning** strategy:
   - **None** — direct execution, no planning
   - **Act** — execute actions directly
   - **Plan then Act** — the agent plans first, then executes
   - **ReAct** — the agent alternates between reasoning and acting (recommended for complex tasks)
6. Click **Save**

::: info Autonomy Levels
Under **Autonomy & Permissions**, you can control how much freedom the agent has:
- **Supervised** — every tool call requires your approval
- **Semi-Autonomous** — only sensitive operations need approval
- **Autonomous** — the agent runs without interruption

Start with **Supervised** until you trust the agent's behavior, then graduate to Semi-Autonomous.
:::

## Step 3: Create Skills

Skills are reusable prompt modules that agents can use. They're written in Markdown with optional YAML frontmatter.

1. From your project, go to the **Skills** tab
2. Click **New Skill**
3. In the editor:
   - Set a **Name** and **Description** in the frontmatter panel
   - Write the skill body in the **Monaco Editor**:

   ```markdown
   When reviewing code, follow these rules:

   1. Check for SQL injection, XSS, and other OWASP Top 10 issues
   2. Flag any hardcoded credentials or secrets
   3. Verify error handling covers edge cases
   4. Ensure new code has corresponding tests
   5. Keep feedback constructive — suggest fixes, not just problems
   ```
4. Optionally add **Tags** for organization
5. Press `Ctrl+S` (or `Cmd+S`) to save

### Assign Skills to Agents

Once you have skills, attach them to your agent:

1. Open the agent in the **Agent Builder**
2. The skills for your project are available for assignment
3. Assigned skills are merged into the agent's system prompt at runtime

## Step 4: Test in the Playground

Before running your agent in production, test it interactively.

1. Navigate to **Playground** in the sidebar
2. Select your **Project** from the dropdown
3. Choose a **System Prompt source**:
   - Pick a **Skill** to test a single skill's prompt
   - Pick an **Agent** to test the full composed agent prompt (base instructions + skills)
4. Select a **Model** (must have the provider's API key configured in Settings)
5. Type a message and press `Ctrl+Enter` to send
6. Watch the response stream in real-time with token counts

The Playground supports multi-turn conversations, so you can iterate on your prompts and see how the agent handles follow-ups.

## Step 5: Run the Agent

When you're ready to run the agent for real:

1. From your project, trigger an agent execution
2. Monitor progress in the **Execution Dashboard**:
   - See each execution step: Perceive, Reason, Act, Observe
   - Track tokens used, cost, and duration per run
   - If the agent is in **Supervised** mode, you'll see an approval banner — review the proposed action and click **Approve** or **Reject**
3. View the **Final Output** once the run completes

### Execution Dashboard Stats

The dashboard gives you an at-a-glance view of:
- **Total Runs** — how many times your agents have executed
- **Total Cost** — cumulative spending across all runs
- **Success Rate** — percentage of runs that completed successfully
- **Cost by Model** — breakdown of which models are costing the most

## Step 6: Set Up Schedules (Optional)

Agents can run on a schedule, respond to webhooks, or trigger on events.

1. From your project, go to the **Schedules** tab
2. Click **New Schedule**
3. Choose a trigger type:
   - **Cron** — run on a recurring schedule (use the visual cron builder or enter a custom expression)
   - **Webhook** — trigger via an HTTP POST to a unique URL
   - **Event** — trigger when something happens in the system
4. Select which agent to run
5. Toggle the schedule **On**

::: tip Webhook Triggers
Each webhook schedule gets a unique URL and secret token. Send a POST request to that URL to trigger the agent on demand — great for CI/CD integrations.
:::

## Step 7: Build Workflows (Optional)

For multi-agent orchestration, use the visual **Workflow Builder**.

1. Navigate to your project's **Workflows** tab
2. Click **New Workflow**
3. Use the drag-and-drop canvas to build a DAG:
   - **Start** node — entry point
   - **Agent** nodes — each runs a specific agent
   - **Condition** nodes — branch based on output
   - **Split/Join** nodes — run agents in parallel
   - **End** node — exit point
4. Connect nodes by dragging edges between them
5. Set a trigger (Manual, Schedule, Webhook, or Event)
6. Click **Save** and set the workflow status to **Active**

## What's Next?

- **Fallback Models** — Add fallback models to your agents so they automatically retry with a different provider if the primary model fails
- **Budget Controls** — Set per-run and daily budgets to prevent runaway costs
- **Performance Dashboard** — Track KPIs, agent leaderboards, and cost trends across your organization
- **Audit Log** — Review every action taken by your agents for compliance and debugging

## Quick Reference

| Keyboard Shortcut | Action |
|---|---|
| `Cmd+K` / `Ctrl+K` | Command palette (fuzzy search) |
| `Ctrl+S` / `Cmd+S` | Save current skill |
| `Ctrl+Enter` | Send message in Playground |

| Default Login | |
|---|---|
| Email | `admin@admin.com` |
| Password | `password` |

| Interface | URL |
|---|---|
| React SPA | `http://localhost:5173` |
| API | `http://localhost:8000/api` |
