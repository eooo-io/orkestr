# Organizations

Orkestr is multi-tenant -- every user belongs to one or more organizations, and all resources (projects, skills, agents, guardrails) are scoped to an organization. This lets teams collaborate while keeping data isolated.

## Creating an Organization

The first organization is created during the [Setup Wizard](./getting-started#setup-wizard). To create additional organizations, navigate to **Settings > Organizations** and click **Create Organization**.

Each organization has:

| Field | Description |
|---|---|
| **Name** | Display name |
| **Slug** | URL-friendly identifier (auto-generated) |
| **Description** | Optional description |
| **Plan** | `free`, `pro`, or `teams` |

## Roles

Every organization member has one of five roles. Roles are hierarchical -- higher roles inherit all permissions of lower roles.

| Role | Permissions |
|---|---|
| **Owner** | Full control. Transfer ownership, delete the org, manage billing. One owner per org. |
| **Admin** | Manage members, configure SSO and guardrails, create/delete projects. Cannot delete the org or transfer ownership. |
| **Editor** | Create and edit skills, agents, and workflows. Run executions. Cannot manage members or org settings. |
| **Viewer** | Read-only access to all projects, skills, and execution history. Cannot create or modify anything. |
| **Member** | Base role. Can view projects they are assigned to. Limited access until elevated. |

::: tip
Use the **Editor** role for developers who need to work with skills and agents daily. Reserve **Admin** for team leads who manage access and policies.
:::

## Inviting Members

Navigate to **Settings > Organizations** and click **Invite Member**. Enter the user's email address and select a role.

The invited user receives an email with a link to accept the invitation. Until accepted, the invitation appears in the pending list.

### Managing Invitations

- View pending invitations on the organization settings page
- Cancel a pending invitation before it is accepted
- Resend the invitation email if needed

## Removing Members

Admins and owners can remove members from the organization. Navigate to **Settings > Organizations**, find the member in the list, and click **Remove**.

::: warning
Removing a member immediately revokes their access to all projects and resources in the organization. This action cannot be undone -- you would need to send a new invitation.
:::

## Switching Organizations

If a user belongs to multiple organizations, they can switch between them using the organization selector in the sidebar. The active organization is stored as `current_organization_id` on the user record and sent via the `X-Organization-Id` header on API requests.

## SSO / SAML Setup

Enterprise organizations can configure Single Sign-On for centralized authentication.

### Adding an SSO Provider

Navigate to **Settings > SSO** and click **Add SSO Provider**:

| Field | Description |
|---|---|
| **Name** | Display name (e.g., "Corporate Okta") |
| **Type** | `saml2` or `oidc` |
| **Entity ID** | Your identity provider's entity ID |
| **SSO URL** | The IdP's single sign-on endpoint |
| **Certificate** | The IdP's X.509 certificate for signature verification |
| **Attribute mapping** | Map IdP attributes to Orkestr user fields (email, name) |

```
POST /api/organizations/{org}/sso-providers
```

```json
{
  "name": "Corporate Okta",
  "type": "saml2",
  "config": {
    "entity_id": "https://idp.company.com/saml2",
    "sso_url": "https://idp.company.com/saml2/sso",
    "certificate": "MIID...",
    "attribute_mapping": {
      "email": "user.email",
      "name": "user.displayName"
    }
  },
  "is_enabled": true
}
```

### Testing SSO

Before enforcing SSO for all users, test the configuration:

```
POST /api/sso-providers/{id}/test
```

This initiates a test authentication flow and reports whether the handshake succeeded.

::: tip
Always test SSO configuration with a non-admin account first. If SSO is misconfigured and you enforce it for all users, you could lock yourself out.
:::

## Plan-Based Feature Gating

Organizations operate under one of three plans, each with different feature limits.

| Feature | Free | Pro | Teams |
|---|---|---|---|
| Projects | 3 | Unlimited | Unlimited |
| Skills per project | 25 | Unlimited | Unlimited |
| Team members | 1 | 5 | Unlimited |
| Execution history | 7 days | 90 days | Unlimited |
| Guardrail policies | Basic | Full | Full + audit |
| SSO/SAML | -- | -- | Yes |
| Priority support | -- | Email | Dedicated |

Plan enforcement happens at three levels:

1. **Feature gates** -- `CheckPlanFeature` middleware blocks access to features not available on the current plan
2. **Usage limits** -- `CheckPlanLimit` middleware enforces numeric limits (projects, skills, members)
3. **Budget controls** -- `CheckUsageBudget` middleware tracks monthly token usage against plan allowances

### Upgrading

Upgrade your plan at **Settings > License** or via the billing API:

```
POST /api/billing/subscribe
{ "plan": "pro" }
```

::: warning
Downgrading a plan does not delete resources that exceed the new plan's limits. Existing resources remain accessible, but you cannot create new ones until you are within limits.
:::
