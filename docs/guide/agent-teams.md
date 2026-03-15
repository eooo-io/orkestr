# Agent Teams

Agent teams let you compose multiple specialized agents for a project, each with their own role, skills, autonomy level, and delegation rules. The Agents and Team tabs on the project detail page work together -- Agents is where you configure individual agents, and Team is where you see the operational dashboard.

## The 9 Pre-Built Agents

Orkestr ships with 9 agents, each designed for a specific development role:

| Agent | Role | Focus |
|---|---|---|
| Orchestrator | `orchestrator` | Coordinates multi-agent workflows, delegates tasks |
| PM Agent | `project-manager` | Requirements, user stories, sprint planning |
| Architect Agent | `architect` | System design, API contracts, technology selection |
| QA Agent | `qa` | Test writing, edge case analysis, regression prevention |
| Design Agent | `designer` | UI/UX design, accessibility, responsive layouts |
| Code Review Agent | `code-reviewer` | Correctness, security, performance checks |
| Infrastructure Agent | `infrastructure` | Docker, Kubernetes, networking, security hardening |
| CI/CD Agent | `cicd` | Pipeline design, deployment strategies |
| Security Agent | `security` | OWASP Top 10, vulnerability auditing, secure coding |

Each agent comes with detailed base instructions that define its persona, behavioral guidelines, and areas of expertise.

## Enabling Agents

Navigate to the **Agents** tab on the project detail page. Each agent is listed with a toggle switch. By default, no agents are enabled -- you opt in to the ones relevant to your project.

When you enable an agent:

- It appears in the Team dashboard
- It is included in [agent compose](./agent-compose) output
- It is written to provider config files during sync

When you disable an agent, it is excluded from compose and sync but retains its per-project configuration.

## Configuring an Agent

Click the settings icon on any agent to open the configuration modal.

### Custom Instructions

Add project-specific instructions that get appended to the agent's base prompt during compose. For example, adding to the QA Agent:

```markdown
## Project-Specific Testing Rules

- All tests use Pest PHP, not PHPUnit syntax
- Mock the PaymentGateway interface in payment tests
- Minimum 80% coverage for new features
- Use RefreshDatabase trait for all API tests
```

Custom instructions are stored per project. Other projects using the same agent are unaffected.

### Skill Assignment

Assign skills from the current project to an agent. When composed, the assigned skills' resolved bodies are appended to the agent's output. This lets you build agents that combine their base persona with your project's specific rules.

For example, assign "Coding Standards" and "API Conventions" skills to the Code Review Agent so it knows your project's rules.

::: tip
You can also assign skills to agents visually on the [Canvas](./canvas) by dragging a skill node onto an agent node.
:::

### Compose Preview

Click the eye icon on an enabled agent to see the full composed output. This shows the final prompt that will be written to provider config files: base instructions + custom instructions + resolved skill bodies.

## Autonomy Levels

Each agent has an autonomy level that controls how much independent action it can take during execution:

| Level | Badge Color | Behavior |
|---|---|---|
| **Supervised** | Blue | Every action requires human approval before proceeding |
| **Semi-Autonomous** | Amber | Routine actions execute automatically; significant decisions pause for approval |
| **Autonomous** | Green | Full autonomous execution within its tool and budget limits |

Autonomy levels are configured in the agent's base definition and can be adjusted per project.

## The Team Dashboard

The **Team** tab shows a card-based dashboard of all enabled agents. Each card displays:

- **Agent name and role** with an active/idle status indicator
- **Model** -- the LLM model the agent uses
- **Autonomy badge** -- color-coded supervised/semi-auto/autonomous
- **Run count** -- total executions with success rate percentage
- **Average cost** -- mean cost per execution in USD
- **Last run** -- relative timestamp of the most recent execution
- **Next run** -- scheduled next execution (if applicable)

Click any agent card to navigate to its detailed configuration page.

::: warning
The Team tab only shows agents that are enabled for the project. If you do not see an agent here, go to the Agents tab and toggle it on.
:::

## Delegation

Agents can delegate tasks to other agents using the A2A (Agent-to-Agent) protocol. Delegation is configured visually on the [Canvas](./canvas) by drawing edges between agent nodes. Each delegation edge specifies:

- **Trigger** -- the condition that initiates delegation
- **Handoff context** -- what data to pass (conversation history, memory, tools, custom JSON)
- **Return behavior** -- report back, fire and forget, or chain forward

See [Canvas](./canvas) for details on configuring delegation edges.

## Agents and Provider Sync

When you run a provider sync, each enabled agent is composed (base + custom + skills) and included in the output alongside individual skills. The composed agent output appears under an H2 heading with the agent's name in providers that use a single file (Claude, Copilot, Cline, OpenAI), or as a separate file in providers that use per-skill files (Cursor, Windsurf).

See [Agent Compose](./agent-compose) and [Provider Sync](./provider-sync) for details.

## Next Steps

- [Agent Compose](./agent-compose) -- how composition merges base + custom + skills
- [Canvas](./canvas) -- visual builder for agent-skill-MCP relationships
- [Workflows](./workflows) -- multi-agent DAG orchestration
- [Provider Sync](./provider-sync) -- how agents map to provider config files
