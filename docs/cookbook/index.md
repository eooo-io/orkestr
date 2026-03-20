# Cookbook

Practical, step-by-step recipes for common tasks in Orkestr. Each recipe assumes you have a running installation and a project created. If not, start with the [Getting Started](/guide/getting-started) guide.

::: tip Format
Each recipe follows the same structure: **Goal** (what you're building), **Ingredients** (what you need), **Steps** (how to do it), and **Result** (what you end up with).
:::

## Recipes

### Getting Started

| Recipe | What You'll Build | Difficulty |
|---|---|---|
| [Your First Agent Team](./first-agent-team) | A 3-agent team for code review with orchestration | Beginner |
| [Skills from Scratch](./skills-from-scratch) | A composable skill library with includes and templates | Beginner |

### Agent Patterns

| Recipe | What You'll Build | Difficulty |
|---|---|---|
| [Code Review Pipeline](./code-review-pipeline) | A workflow that reviews PRs with security, quality, and testing agents | Intermediate |
| [Scheduled Security Scanner](./scheduled-security-scanner) | An agent that runs nightly security scans on your codebase | Intermediate |
| [Multi-Agent Architecture Review](./architecture-review) | An architect + infrastructure + security team that reviews system designs | Advanced |

### Infrastructure

| Recipe | What You'll Build | Difficulty |
|---|---|---|
| [Air-Gapped Local Setup](./air-gapped-setup) | A fully offline Orkestr with Ollama local models | Intermediate |
| [MCP Tool Integration](./mcp-tool-integration) | Custom MCP servers wired to agents on the canvas | Intermediate |
| [Production Deployment](./production-deployment) | A production-grade Orkestr with reverse proxy, backups, and monitoring | Advanced |

### Enterprise

| Recipe | What You'll Build | Difficulty |
|---|---|---|
| [Enterprise Guardrails](./enterprise-guardrails) | Organization-level policies with cascading profiles | Intermediate |
| [CI/CD with GitHub Actions](./cicd-github-actions) | Automated skill sync and validation on every PR | Intermediate |
