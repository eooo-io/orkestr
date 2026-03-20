# Guardrail System

This deep dive covers the multi-layer guardrail architecture — how Orkestr enforces safety policies across organizations, projects, and agents.

## Architecture Overview

```
┌─────────────────────────────────────────────────────┐
│              Guardrail Resolution Pipeline            │
│                                                      │
│  Organization Policy                                 │
│       │ (most permissive scope)                      │
│       ▼                                              │
│  Project Policy (can only tighten)                   │
│       │                                              │
│       ▼                                              │
│  Agent Policy (can only tighten further)             │
│       │                                              │
│       ▼                                              │
│  Resolved Policy (intersection of all three)         │
│       │                                              │
│       ▼                                              │
│  ┌───────────┬───────────┬───────────┬────────────┐ │
│  │ Budget    │ Tool      │ Approval  │ Output     │ │
│  │ Guard     │ Guard     │ Guard     │ Guard      │ │
│  └───────────┴───────────┴───────────┴────────────┘ │
│  ┌───────────┐                                       │
│  │ DataAccess│                                       │
│  │ Guard     │                                       │
│  └───────────┘                                       │
└─────────────────────────────────────────────────────┘
```

## Policy Cascading

Guardrail policies cascade from organization → project → agent. Each level can **tighten** restrictions but never loosen them:

```
Resolution algorithm:
  resolved.max_budget = min(org.max_budget, project.max_budget, agent.max_budget)
  resolved.allowed_tools = intersection(org.allowed_tools, project.allowed_tools, agent.allowed_tools)
  resolved.blocked_tools = union(org.blocked_tools, project.blocked_tools, agent.blocked_tools)
  resolved.autonomy = min_autonomy(org.autonomy, project.autonomy, agent.autonomy)
  resolved.pii_redaction = org.pii_redaction || project.pii_redaction || agent.pii_redaction
```

### Example

```
Organization:
  max_daily_budget: $100
  blocked_tools: [shell.execute, database.drop]
  autonomy: supervised
  pii_redaction: true

Project "Backend API":
  max_daily_budget: $25        ← tighter than org's $100
  blocked_tools: [filesystem.deleteFile]  ← adds to org blocklist
  autonomy: supervised         ← same as org

Agent "Security Auditor":
  max_run_budget: $2           ← per-run limit
  allowed_tools: [filesystem.readFile, github.createComment]  ← allowlist
  autonomy: autonomous         ← WOULD be overridden to supervised (org policy)

Resolved for this agent:
  max_run_budget: $2
  max_daily_budget: $25
  allowed_tools: [filesystem.readFile, github.createComment]
  blocked_tools: [shell.execute, database.drop, filesystem.deleteFile]
  autonomy: supervised (org overrides autonomous)
  pii_redaction: true
```

## Data Model

### Guardrail Policies

```
guardrail_policies
├── id (UUID)
├── organization_id → organizations
├── project_id → projects (nullable — org-level if null)
├── name: "Default Security Policy"
├── type: "budget" | "tool" | "autonomy" | "data" | "output"
├── config (JSON):
│   ├── budget: { max_per_run, max_per_day, max_per_agent }
│   ├── tool: { allowed: [...], blocked: [...], patterns: [...] }
│   ├── autonomy: { level, approval_threshold }
│   ├── data: { allowed_paths: [...], blocked_paths: [...] }
│   └── output: { pii_redaction, secret_detection, patterns: [...] }
├── severity: "block" | "warn" | "log"
├── enabled: true/false
└── priority: 1 (higher = evaluated first)
```

### Guardrail Profiles

Pre-built policy bundles:

```
guardrail_profiles
├── id (UUID)
├── name: "Strict" | "Moderate" | "Permissive"
├── description
├── policies (JSON) — full policy definitions
└── is_default: true/false

Profile Presets:
├── Strict:
│   ├── Budget: $1/run, $10/day
│   ├── Tools: allowlist only, no write/execute
│   ├── Autonomy: manual (every action approved)
│   ├── Output: full PII/secret redaction
│   └── Data: read-only, limited paths
│
├── Moderate:
│   ├── Budget: $5/run, $50/day
│   ├── Tools: most allowed, dangerous blocked
│   ├── Autonomy: supervised (expensive/destructive need approval)
│   ├── Output: PII/secret redaction enabled
│   └── Data: project directory access
│
└── Permissive:
    ├── Budget: $20/run, $200/day
    ├── Tools: all allowed
    ├── Autonomy: autonomous
    ├── Output: logging only (no redaction)
    └── Data: full filesystem access
```

### Violations

```
guardrail_violations
├── id (UUID)
├── organization_id → organizations
├── policy_id → guardrail_policies
├── execution_run_id → execution_runs (nullable)
├── agent_id → agents
├── type: "budget_exceeded" | "tool_blocked" | "approval_required" |
│         "pii_detected" | "secret_detected" | "path_violation"
├── severity: "block" | "warn" | "log"
├── details (JSON) — what triggered the violation
├── action_taken: "blocked" | "warned" | "logged" | "redacted" | "dismissed"
├── dismissed_by → users (nullable)
├── dismissed_at
└── created_at
```

## Guard Implementations

### BudgetGuard

```
Check order:
1. Per-run budget: has this run exceeded its allocation?
2. Per-agent daily budget: has this agent exceeded today's limit?
3. Per-org daily budget: has the org exceeded today's total?
4. Delegation budget: does the parent have enough remaining to allocate?

On exceed:
├── severity=block → stop execution, return partial results
├── severity=warn → log warning, continue
└── severity=log → record violation, continue

Cost calculation:
├── Input tokens × model's input price
├── Output tokens × model's output price
├── Tool call costs (if applicable)
└── Accumulated across all iterations
```

### ToolGuard

```
Check order:
1. Is the tool in the blocklist? → block
2. Is there an allowlist AND the tool is NOT in it? → block
3. Dangerous input pattern scan:
   ├── Command injection: |, ;, &&, $(, backticks
   ├── Path traversal: ../, /../
   ├── SQL injection: union, drop, --, ;
   └── Configurable patterns from policy
4. All checks pass → allow

On block:
├── Record violation with tool name and input
├── Return error to agent loop (agent can reason about it)
└── Agent may try alternative approach
```

### ApprovalGuard

```
Autonomy levels:
├── autonomous → all actions pass
├── supervised → check threshold:
│   ├── Estimated cost > threshold → pause for approval
│   ├── Tool is in "requires_approval" list → pause
│   └── Otherwise → pass
└── manual → all actions pause for approval

On pause:
├── Set execution_run.status = "paused"
├── Send notification to approvers
├── Wait for human action (approve/reject)
├── On approve → continue execution
├── On reject → terminate with "rejected" status
```

### OutputGuard

```
Scan targets:
├── LLM response text
├── Tool call results
├── Agent final output
└── Memory entries being stored

Detectors:
├── PII:
│   ├── Email addresses (regex)
│   ├── Phone numbers (regex)
│   ├── SSN/tax IDs (regex)
│   └── Configurable patterns
├── Secrets:
│   ├── API keys (pattern matching: sk-..., AIza..., etc.)
│   ├── Passwords in key-value contexts
│   ├── JWT tokens
│   └── Private keys
└── Custom patterns from org policy

On detection:
├── If redaction enabled → replace with [REDACTED]
├── Record violation with detected type and location
├── If severity=block → stop execution
└── If severity=warn → continue with redacted output
```

### DataAccessGuard

```
Scope types:
├── File paths: allowed/blocked directory patterns
├── API endpoints: allowed/blocked URL patterns
├── Database schemas: allowed/blocked table patterns

Check:
├── Before filesystem tool calls → validate path
├── Before API tool calls → validate URL
├── Before database tool calls → validate query target

Delegation enforcement:
├── Child agent scope = intersection(parent scope, child config)
├── Never exceeds parent scope
└── Recorded in delegation trace
```

## Security Scanner (Static Analysis)

Beyond runtime guards, the static security scanner analyzes skills and agent configs:

```
SecurityRuleSet:
├── Prompt injection patterns:
│   ├── "ignore previous instructions"
│   ├── "disregard all rules"
│   ├── "you are now..."
│   └── System prompt override attempts
├── Data exfiltration patterns:
│   ├── "send to", "upload to", "post to" + external URLs
│   ├── Webhook/API calls with data payloads
│   └── Base64 encode + transmit patterns
├── Credential harvesting:
│   ├── "what is your API key"
│   ├── "show me the password"
│   └── Environment variable extraction
└── Obfuscation:
    ├── Base64 encoded instructions
    ├── Hex encoded content
    ├── Unicode tricks
    └── Comment-hidden instructions
```

The scanner runs:
- On demand via the Skill Editor "Scan" button
- Automatically when publishing to the marketplace
- Optionally on every skill save

## Reporting Dashboard

The guardrail reporting dashboard provides:

### Violation Trends
- Time-series chart of violations by type
- Breakdown by severity (block/warn/log)
- Top triggering agents and tools

### Agent Behavior Analysis
- Per-agent violation frequency
- Budget utilization patterns
- Tool usage heatmaps

### Compliance Exports
- CSV export of all violations
- PDF reports with trend analysis
- Filterable by date range, agent, type, severity

### Dismissal Workflow
- Violations can be reviewed and dismissed by admins
- Dismissal records who dismissed it and why
- Dismissed violations excluded from trend reporting (but still in audit log)
