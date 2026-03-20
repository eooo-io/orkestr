# Enterprise Guardrails

**Goal:** Configure organization-level guardrail policies that cascade to projects and agents, with profiles for different risk levels.

**Time:** 20 minutes

## Ingredients

- A running Orkestr instance with an organization
- At least one project with agents configured
- Admin access

## Steps

### 1. Define Organization Policies

Navigate to **Settings → Guardrails → Policies** and create the organization-wide defaults.

#### Budget Policy

```
Name: Org Budget Limits
Type: Budget
Scope: Organization
Config:
  max_per_run: $5.00
  max_per_agent_daily: $25.00
  max_org_daily: $200.00
Severity: Block
```

#### Tool Policy

```
Name: Org Tool Restrictions
Type: Tool
Scope: Organization
Config:
  blocked_tools:
    - shell.execute
    - filesystem.deleteFile
    - database.dropTable
  dangerous_patterns:
    - "rm -rf"
    - "DROP TABLE"
    - "curl.*|.*bash"
Severity: Block
```

#### Output Policy

```
Name: Org Output Safety
Type: Output
Scope: Organization
Config:
  pii_redaction: true
  secret_detection: true
  patterns:
    - type: email
      action: redact
    - type: api_key
      action: block
    - type: ssn
      action: block
Severity: Block (for secrets), Warn (for PII)
```

### 2. Create Guardrail Profiles

Go to **Guardrail Profiles** and create three presets:

#### Strict Profile

For production and sensitive environments:

```
Name: Strict
Budget: $1/run, $10/day
Tools: Allowlist only — filesystem.readFile, github.getFileContents
Autonomy: Manual (every action needs approval)
Output: Full PII/secret redaction
Data: Read-only, project directory only
```

#### Moderate Profile

For development and testing:

```
Name: Moderate
Budget: $5/run, $50/day
Tools: Most allowed, destructive blocked
Autonomy: Supervised (expensive/destructive needs approval)
Output: PII/secret redaction enabled
Data: Project directory, read/write
```

#### Permissive Profile

For sandbox and experimentation:

```
Name: Permissive
Budget: $20/run, $100/day
Tools: All allowed
Autonomy: Autonomous
Output: Logging only (no redaction)
Data: Full access
```

### 3. Assign Profiles to Projects

For each project, assign the appropriate profile:

- **Production Backend** → Strict profile
- **Development Environment** → Moderate profile
- **Research Sandbox** → Permissive profile

### 4. Override Per-Agent

Some agents need different settings than their project default:

```
Project: Production Backend (Strict profile)
  ├── Security Agent → Strict (inherits project)
  ├── Code Review Agent → Strict (inherits project)
  └── QA Agent → Custom:
      ├── Budget: $3/run (tighter than profile's $1)
      ├── Tools: + filesystem.writeFile (needs to create test files)
      └── Autonomy: Supervised (less restrictive than Strict's Manual)
```

The QA Agent gets `supervised` autonomy, but the org-level policy overrides this if the org requires `manual`.

### 5. Set Up Violation Monitoring

Go to **Settings → Guardrails → Reports** to configure:

- **Dashboard view:** Violation trends over time
- **Alerts:** Notify admins when violation rate exceeds threshold
- **Weekly export:** Automated CSV report of all violations

### 6. Test the Guardrails

Run an agent and intentionally trigger guardrails:

1. **Budget test:** Run a skill that generates a very long response → should hit budget limit
2. **Tool test:** Try to use a blocked tool → should be blocked
3. **Output test:** Generate content with a fake API key → should be redacted
4. **Approval test:** With supervised autonomy, trigger a tool call → should pause for approval

Check the violation dashboard to confirm everything is logged.

## Result

You have a multi-layer guardrail system:
- Organization policies set the broadest boundaries
- Profiles simplify assignment (Strict/Moderate/Permissive)
- Per-project and per-agent overrides handle special cases
- Violation monitoring provides audit trail and compliance reporting
- Cascading ensures child scopes can only tighten, never loosen

## Key Principles

1. **Start strict, loosen as needed** — It's easier to grant more access than to revoke it
2. **Use profiles for consistency** — Don't configure every agent individually
3. **Monitor violations** — Frequent violations might mean policies are too tight or agents need adjustment
4. **Review regularly** — As your agent system grows, review guardrails quarterly
