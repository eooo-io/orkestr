# Guardrails

Orkestr provides organization-level guardrail policies to enforce safety, security, and compliance standards across all agent and skill activity.

## Organization-Level Policies

Guardrails are scoped to organizations. Each organization can define policies that apply to all projects and users within it.

```
GET    /api/organizations/{org}/guardrails
POST   /api/organizations/{org}/guardrails
PUT    /api/organizations/{org}/guardrails/{id}
DELETE /api/organizations/{org}/guardrails/{id}
```

A guardrail policy includes:

- **Name** -- human-readable identifier (e.g., "No PII in prompts")
- **Type** -- `content`, `security`, `compliance`, or `custom`
- **Severity** -- `error` (blocks execution), `warning` (flags but allows), `info` (advisory)
- **Pattern** -- regex or keyword match rule
- **Action** -- what happens on violation: block, flag, redact, or notify

## Guardrail Profiles

Profiles bundle multiple policies into a named configuration for easy assignment.

```
GET    /api/guardrail-profiles
POST   /api/guardrail-profiles
PUT    /api/guardrail-profiles/{id}
DELETE /api/guardrail-profiles/{id}
```

Orkestr ships with three built-in profiles:

### Strict

- Blocks prompts containing PII patterns (SSN, credit card, email)
- Blocks code execution instructions
- Requires all custom endpoints to be approved
- All violations are errors (execution blocked)

### Moderate

- Flags PII patterns as warnings
- Allows code execution with logging
- Custom endpoints require approval
- Mix of errors and warnings

### Permissive

- Advisory-only notifications for PII
- All content types allowed
- Custom endpoints auto-approved
- All violations are info-level

Assign a profile to a project or use it as the organization default.

## Security Scanning

Scan individual skills for security concerns before deployment:

```
POST /api/skills/{id}/security-scan
```

The scanner checks for:

- **Prompt injection patterns** -- attempts to override system instructions
- **Data exfiltration risks** -- instructions that could leak sensitive data
- **PII exposure** -- personal information in prompt text
- **Unsafe tool usage** -- dangerous tool configurations
- **Dependency risks** -- included skills with known issues

Results include a severity rating, description, and suggested remediation for each finding.

## Content Review

Skills can go through a review workflow before being deployed or synced.

```
GET  /api/skills/{id}/reviews
POST /api/skills/{id}/reviews
```

```json
{
  "status": "approved",
  "comment": "Reviewed -- safe for production use."
}
```

Review statuses: `pending`, `approved`, `rejected`, `needs_changes`.

When guardrails require review, skills in `pending` or `rejected` state are excluded from provider sync.

## Endpoint Approval Workflow

Custom model endpoints (vLLM, TGI, etc.) can require admin approval before use:

1. A user adds a custom endpoint
2. The endpoint enters `pending_approval` state
3. An organization admin reviews and approves or rejects it
4. Only approved endpoints are available for skill testing and agent execution

This prevents unauthorized model servers from being used within the organization.

## Violation Reporting

When guardrail violations occur, they are logged and available via the reporting API:

```
GET /api/reports/audit
```

Violation reports include:

- Timestamp and user
- Guardrail policy that triggered
- Severity and action taken
- The content or action that caused the violation
- Project and skill context

Organization admins can view violation trends on the dashboard and configure notification channels (email, webhook) for real-time alerts.
