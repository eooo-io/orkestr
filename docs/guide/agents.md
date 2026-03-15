# Agent Configuration

Agents are pre-built AI roles that you can enable, customize, and assign skills to on a per-project basis. When synced, each enabled agent's composed output is included in the provider config files alongside individual skills.

## The 9 Pre-Built Agents

| Agent | Role | Purpose |
|---|---|---|
| Orchestrator | `orchestrator` | Coordinates multi-agent workflows, delegates tasks, synthesizes results |
| PM Agent | `project-manager` | Requirements gathering, user stories, sprint planning, documentation |
| Architect Agent | `architect` | System design, API contracts, technology selection, code structure |
| QA Agent | `qa` | Test writing, edge case analysis, code review, regression prevention |
| Design Agent | `designer` | UI/UX design, component specs, accessibility, responsive layouts |
| Code Review Agent | `code-reviewer` | Code correctness, security, performance, consistency checks |
| Infrastructure Agent | `infrastructure` | Docker, Kubernetes, networking, storage, security hardening |
| CI/CD Agent | `cicd` | GitHub Actions, GitLab CI, pipeline design, deployment strategies |
| Security Agent | `security` | OWASP Top 10, vulnerability auditing, secure coding practices |

Each agent comes with detailed base instructions that define its persona and behavioral guidelines.

## The Agents Tab

In the project detail page, click the **Agents** tab to see all 9 agents. Each agent card shows:

- Agent name and icon
- Description
- Enabled/disabled toggle
- Number of assigned skills

## Enabling and Disabling

Toggle an agent on or off for the current project. Disabled agents are excluded from [agent compose](./agent-compose) and provider sync. By default, no agents are enabled -- you opt in to the ones relevant to your project.

## Custom Instructions

Click an agent card to open its configuration modal. The **Custom Instructions** field lets you add project-specific instructions that get appended to the agent's base instructions during compose.

For example, you might add to the QA Agent:

```markdown
## Project-Specific Testing Rules

- All API tests must use the `RefreshDatabase` trait
- Use Pest PHP, not PHPUnit syntax
- Mock the `PaymentGateway` interface in payment tests
- Minimum 80% code coverage for new features
```

Custom instructions are stored per project -- other projects using the same agent are unaffected.

## Assigning Skills

In the agent configuration modal, you can assign skills from the current project to the agent. Assigned skills' resolved bodies are included in the [composed output](./agent-compose).

This lets you build an agent that combines its base persona with your project's specific skill prompts. For example, assign your "Coding Standards" and "API Conventions" skills to the Code Review Agent so it knows your project's rules when reviewing code.

## Agent Compose

The compose process merges three layers into a single output:

1. **Base instructions** -- The agent's built-in system prompt defining its role and behavior
2. **Custom instructions** -- Your project-specific additions
3. **Assigned skill bodies** -- The resolved body of each assigned skill (includes and template variables expanded)

Preview the composed output from the agent modal or via the API:

```
GET /api/projects/{id}/agents/{agentId}/compose
```

This returns the full Markdown output and a token estimate.

To compose all enabled agents at once:

```
GET /api/projects/{id}/agents/compose
```

## Export Formats

Agents can be exported to multiple orchestration framework formats:

| Format | Description |
|---|---|
| **Claude Agent SDK** | Python code using Anthropic's agent SDK with tool definitions |
| **LangGraph** | Python graph definition compatible with LangChain's LangGraph |
| **CrewAI** | Python agent and task definitions for the CrewAI framework |
| **JSON** | Generic JSON representation with all agent configuration |

Export from the agent configuration modal by selecting the target format. The export includes the composed instructions, tool definitions, model settings, and autonomy level.

## Agents and Provider Sync

When you run a provider sync, all enabled agents are composed (base instructions + custom instructions + assigned skill bodies) and included in the output alongside individual skills. See [Agent Compose](./agent-compose) for details on how composition works and [Provider Sync](./provider-sync) for how the output maps to provider files.
