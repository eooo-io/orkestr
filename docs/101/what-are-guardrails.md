# What are Guardrails?

## The One-Sentence Answer

Guardrails are safety rules that control what agents can do, how much they can spend, and what they need permission for — preventing runaway behavior and enforcing organizational policies.

## The Analogy: Guardrails on a Highway

Highway guardrails don't stop you from driving — they stop you from driving off a cliff. You still have full control of the car, but if you veer too far, the guardrails keep you safe.

Orkestr guardrails work the same way. Agents have freedom to operate, but within defined boundaries. They can't overspend their budget, access unauthorized data, or execute dangerous tool calls without human approval.

## Why Guardrails Matter

AI agents are powerful — they can read files, call APIs, execute commands, and delegate to other agents. That power needs boundaries:

| Without Guardrails | With Guardrails |
|---|---|
| An agent could burn $500 in tokens exploring a rabbit hole | Budget caps stop spending at the defined limit |
| An agent could read files outside its project scope | Data access boundaries enforce file/directory permissions |
| An agent could call a destructive API (delete a database) | Tool allowlists prevent unauthorized operations |
| An agent could leak secrets in its output | Output filtering detects and redacts PII and credentials |
| A prompt injection could hijack an agent | Input sanitization strips malicious patterns |

## The Five Guards

Orkestr's runtime enforces five types of guards at every step of the agent loop:

### 1. Budget Guard

Controls how much an agent can spend:

```
Budget Limits:
├── Per-run: $0.50 max per execution
├── Per-agent: $10.00 max per day
├── Daily org total: $100.00 max
└── Delegation: child agents inherit remaining parent budget
```

When a budget limit is reached, the agent stops gracefully and reports its current state.

### 2. Tool Guard

Controls which tools an agent can use:

```
Tool Policy:
├── Allowed: filesystem.readFile, github.createComment
├── Blocked: filesystem.deleteFile, shell.execute("rm")
└── Dangerous input patterns: command injection, path traversal
```

### 3. Approval Guard

Controls which actions need human permission:

```
Autonomy: supervised
├── Autonomous: read operations, analysis, comments
├── Needs approval: write operations over $0.10
└── Always blocked: destructive operations
```

### 4. Output Guard

Scans agent outputs for sensitive content:

```
Output Scanning:
├── PII detection: emails, phone numbers, SSNs
├── Secret detection: API keys, passwords, tokens
├── Redaction: automatically mask detected content
└── Logging: record violations for audit trail
```

### 5. Data Access Guard

Controls what data an agent can reach:

```
Data Boundaries:
├── Project scope: can only read files within the project directory
├── API scope: can only call approved endpoints
└── Delegation scope: child agents inherit intersected permissions
```

## Guardrail Levels

Guardrails cascade from organization → project → agent. Each level can **tighten** restrictions but never loosen them:

```
Organization Policy (broadest):
├── Max $100/day across all agents
├── No shell.execute allowed
├── PII redaction enabled
│
├── Project Policy (tighter):
│   ├── Max $25/day for this project
│   ├── Filesystem access limited to /src and /tests
│   │
│   ├── Agent Policy (tightest):
│   │   ├── Max $5/run for this agent
│   │   ├── Supervised autonomy
│   │   └── Only filesystem.readFile allowed
```

If the org says "no shell.execute," no project or agent can override that.

## Guardrail Profiles

Pre-built profiles make it easy to apply consistent policies:

| Profile | Philosophy | Token Budget | Tool Access | Approval |
|---|---|---|---|---|
| **Strict** | Locked down, everything reviewed | Low | Allowlist only | Every action |
| **Moderate** | Balanced safety and productivity | Medium | Most tools, some blocked | Expensive/destructive actions |
| **Permissive** | Maximum agent freedom | High | All tools allowed | Only destructive operations |

Assign a profile to an agent, and all the guardrail settings are applied automatically. You can also create custom profiles.

## Security Scanner

Beyond runtime guardrails, Orkestr includes a static security scanner that checks skills and agent configurations for:

- **Prompt injection patterns** — Instructions that try to override system prompts
- **Data exfiltration** — Attempts to send data to external endpoints
- **Credential harvesting** — Requests for API keys, passwords, or tokens
- **Obfuscation** — Base64 encoding, hex encoding, or other tricks to hide malicious content

The scanner runs on demand or as part of automated review workflows.

## Guardrail Reports

The reporting dashboard shows:

- **Violation trends** — Which guardrails trigger most often
- **Agent behavior** — Which agents hit guardrail limits
- **Cost analysis** — Spending patterns and budget utilization
- **Compliance exports** — CSV/PDF reports for audit requirements

## Key Takeaway

Guardrails are the safety infrastructure of the Agent OS. They enforce budgets, tool permissions, data boundaries, output filtering, and approval workflows — cascading from organization to project to agent. They let you give agents real power while maintaining real control.

---

**Next:** [What are Schedules?](./what-are-schedules) →
