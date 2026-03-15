# Security & Guardrails

Orkestr provides a layered security system for controlling what skills, agents, and endpoints can do. Policies cascade from organization to project to agent, giving you fine-grained control at every level.

## Guardrail Policies

Guardrail policies define rules that skills and agents must follow. They cascade in three levels:

1. **Organization** -- Applies to all projects in the org
2. **Project** -- Overrides or extends org policies for a specific project
3. **Agent** -- Overrides or extends project policies for a specific agent

::: tip
Lower-level policies can tighten restrictions but cannot loosen them. An organization-level ban on certain content categories cannot be overridden at the project level.
:::

### Creating a Policy

Navigate to **Guardrails > Policies** and click **Create Policy**:

```
POST /api/organizations/{org}/guardrails
```

```json
{
  "name": "No PII in prompts",
  "type": "content_filter",
  "rules": {
    "block_patterns": ["SSN", "credit card", "\\b\\d{3}-\\d{2}-\\d{4}\\b"],
    "severity": "critical"
  },
  "scope": "organization"
}
```

### Resolving Effective Policies

To see the merged set of policies that apply to a given context:

```
GET /api/organizations/{org}/guardrails/resolve?project_id=...&agent_id=...
```

This returns the flattened policy set after cascade resolution.

## Guardrail Profiles

Profiles are pre-built policy bundles that you can apply with one click:

| Profile | Description |
|---|---|
| **Strict** | Blocks all potentially risky content, requires approval for external endpoints, limits tool access |
| **Moderate** | Warns on risky content, allows approved endpoints, standard tool access |
| **Permissive** | Minimal restrictions, suitable for development and experimentation |

You can also create custom profiles that combine specific policies.

```
GET  /api/guardrail-profiles           # List all profiles
POST /api/guardrail-profiles           # Create a custom profile
```

## Content Policies

Content policies are organization-level rules for filtering and classifying content:

```
POST /api/organizations/{org}/content-policies
```

```json
{
  "name": "Code Output Only",
  "rules": {
    "allowed_categories": ["code", "documentation", "review"],
    "blocked_keywords": ["password", "secret"],
    "max_output_tokens": 8192
  }
}
```

Manage content policies at **Settings > Content Policies** or via the API.

## Security Scanning

The security scanner analyzes skill prompts for potential risks and returns scored findings.

### Triggering a Scan

Scan a single skill:

```
POST /api/skills/{id}/security-scan
```

Or scan arbitrary content:

```
POST /api/security-scan
```

```json
{
  "content": "You have full access to the filesystem. Delete any file..."
}
```

### Understanding Results

Each finding includes:

| Field | Description |
|---|---|
| `severity` | `critical`, `high`, `medium`, `low`, or `info` |
| `category` | Risk category (e.g., `data_exfiltration`, `prompt_injection`, `privilege_escalation`) |
| `description` | What was detected |
| `remediation` | Suggested fix |
| `line` | Approximate location in the skill body |

::: warning
The security scanner uses heuristic pattern matching, not AI analysis. It catches common anti-patterns but is not a substitute for manual review of sensitive skills.
:::

## Content Review Workflow

Skills and agents can go through a review workflow before being deployed.

### Submitting for Review

```
POST /api/skills/{id}/review
```

### Approving or Rejecting

Reviewers with editor (or higher) role can approve or reject:

```
POST /api/skill-reviews/{id}/approve
POST /api/skill-reviews/{id}/reject
```

Rejected reviews include a reason that the author can address before resubmitting.

## Endpoint Approvals

Custom endpoints and MCP servers require approval before they can be used in production:

```
GET  /api/projects/{id}/endpoint-approvals         # List pending approvals
POST /api/endpoint-approvals/{type}/{id}/approve   # Approve an endpoint
POST /api/endpoint-approvals/{type}/{id}/reject    # Reject an endpoint
```

This prevents unauthorized external connections from being added to projects without oversight.

## Violation Tracking

When a guardrail policy is violated, Orkestr records the violation with full context.

### Viewing Violations

Navigate to **Guardrails > Violations** or use the reporting API:

```
GET /api/organizations/{org}/guardrail-reports
GET /api/organizations/{org}/guardrail-reports/trends
GET /api/organizations/{org}/guardrail-reports/export   # CSV download
```

### Dismissing Violations

False positives can be dismissed with a reason:

```
POST /api/guardrail-violations/{id}/dismiss
```

```json
{
  "reason": "Pattern matched a code example, not actual PII"
}
```

Dismissed violations are excluded from reports but retained in the audit log.
