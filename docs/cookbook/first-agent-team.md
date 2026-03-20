# Your First Agent Team

**Goal:** Set up a 3-agent team — Orchestrator, Code Reviewer, and Security Auditor — that can review code together.

**Time:** 15 minutes

## Ingredients

- A running Orkestr instance
- A project with at least one codebase path configured
- An API key for at least one LLM provider (Anthropic, OpenAI, or a local Ollama model)

## Steps

### 1. Create the Project

If you haven't already, go to **Settings → Projects** (or Filament Admin) and create a project:

- **Name:** "My App"
- **Path:** `/path/to/your/codebase`
- **Providers:** Enable at least Claude or Cursor

### 2. Create Your First Skills

Navigate to the project in the React SPA and click **+ New Skill** to create these three skills:

#### Skill 1: Coding Standards

```yaml
---
name: Coding Standards
description: Team coding conventions
tags: [standards, quality]
---
```

Body:
```markdown
When writing or reviewing code, follow these conventions:

- Use meaningful variable names that describe purpose
- Keep functions under 30 lines
- Write descriptive commit messages
- Add error handling for all async operations
- Use early returns to reduce nesting
```

#### Skill 2: Security Checklist

```yaml
---
name: Security Checklist
description: OWASP-inspired security review
tags: [security, owasp]
---
```

Body:
```markdown
When reviewing code for security:

1. **Injection:** Are all queries parameterized? Is user input sanitized?
2. **Authentication:** Are passwords hashed? Are sessions managed correctly?
3. **Data exposure:** Are API responses filtered? Is PII logged?
4. **Access control:** Can users access resources they shouldn't?
5. **Configuration:** Are secrets in environment variables, not code?
```

#### Skill 3: Test Coverage Rules

```yaml
---
name: Test Coverage Rules
description: Testing standards for the team
tags: [testing, quality]
---
```

Body:
```markdown
When reviewing or writing tests:

- Every new feature must have at least one happy-path test
- Every bug fix must have a regression test
- Test edge cases: empty inputs, null values, boundary conditions
- Mock external dependencies (APIs, databases)
- Aim for 80%+ coverage on new code
```

### 3. Enable Agents

Go to the **Agents** tab in your project. Enable three agents:

1. **Orchestrator** — Toggle ON
2. **Code Review Agent** — Toggle ON
3. **Security Agent** — Toggle ON

### 4. Customize Agent Instructions

Click each agent to open its configuration:

#### Code Review Agent — Custom Instructions

```markdown
## Project Context

This is a {{framework}} project. Focus on:
- Consistent naming (camelCase for variables, PascalCase for classes)
- Proper use of the framework's built-in patterns
- Performance issues (N+1 queries, unnecessary re-renders)
```

#### Security Agent — Custom Instructions

```markdown
## Project Context

This project handles user authentication and payment data.
Pay extra attention to:
- Any changes to auth/ or payment/ directories
- API endpoints that accept user input
- Database migrations that touch sensitive tables
```

### 5. Assign Skills to Agents

In each agent's configuration modal, assign skills:

- **Code Review Agent:** Assign "Coding Standards" and "Test Coverage Rules"
- **Security Agent:** Assign "Security Checklist" and "Coding Standards"
- **Orchestrator:** No skills needed (it delegates)

### 6. Wire It Up on the Canvas

Open the **Canvas** tab. You'll see the three enabled agents as nodes. Now:

1. **Draw a delegation edge** from Orchestrator → Code Review Agent
2. **Draw a delegation edge** from Orchestrator → Security Agent
3. You'll see the skills attached as green chips to each agent

Your canvas should look like:

```
              ┌──────────────┐
         ┌───►│ Code Review  │──── Coding Standards
         │    │ Agent        │──── Test Coverage Rules
┌────────┤    └──────────────┘
│ Orches-│
│ trator │
└────────┤    ┌──────────────┐
         └───►│ Security     │──── Security Checklist
              │ Agent        │──── Coding Standards
              └──────────────┘
```

### 7. Preview the Compose

Click the Orchestrator and go to the compose preview. You'll see the merged output — base instructions + your custom instructions + all assigned skill bodies.

### 8. Test in the Playground

Open the **Playground**, select the Code Review Agent, and paste some code:

```
Review this function:

function getUser(id) {
  const query = "SELECT * FROM users WHERE id = " + id;
  const result = db.query(query);
  return result;
}
```

The agent should flag the SQL injection and suggest parameterized queries.

### 9. Sync to Your Coding Tools

Click **Sync** on the project page. Preview the diff to see what files will be generated, then confirm. Your skills and composed agent instructions are now in your AI coding tool's config files.

## Result

You now have:
- 3 skills that encode your team's standards
- 3 agents configured with those skills
- Delegation wiring so the orchestrator can coordinate reviews
- Skills synced to your AI coding tools
- A working playground for testing

## Next Steps

- Add more skills as you discover patterns in your code reviews
- Set up a [workflow](./code-review-pipeline) to automate the full review process
- Connect [MCP tools](./mcp-tool-integration) so agents can actually read your codebase
- Configure [guardrails](./enterprise-guardrails) for budget and safety controls
