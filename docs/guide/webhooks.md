# Webhooks

Agentis Studio supports outbound webhooks for reacting to skill and project events, plus inbound webhooks for GitHub push-triggered project scans.

## Supported Events

| Event | Trigger |
|---|---|
| `skill.created` | A new skill is created in any project |
| `skill.updated` | An existing skill is saved |
| `skill.deleted` | A skill is deleted |
| `project.synced` | A provider sync completes |

## Configuring Outbound Webhooks

Webhooks are configured per project. In the project detail page, navigate to the **Webhooks** tab.

Click **Add Webhook** and provide:

- **URL** -- The endpoint to receive POST requests
- **Events** -- Which events should trigger this webhook (multi-select)
- **Secret** -- A shared secret for HMAC-SHA256 signature verification (optional but recommended)

### API

```
GET    /api/projects/{id}/webhooks        # List webhooks for a project
POST   /api/projects/{id}/webhooks        # Create a webhook
PUT    /api/webhooks/{id}                 # Update a webhook
DELETE /api/webhooks/{id}                 # Delete a webhook
```

## Payload Format

Webhook payloads are sent as JSON POST requests:

```json
{
  "event": "skill.updated",
  "timestamp": "2026-03-10T14:30:00Z",
  "project": {
    "id": "uuid",
    "name": "my-project"
  },
  "data": {
    "skill_id": "uuid",
    "skill_name": "Code Review Standards",
    "skill_slug": "code-review-standards"
  }
}
```

## HMAC-SHA256 Signing

If you set a secret on the webhook, every delivery includes an `X-Agentis-Signature` header containing the HMAC-SHA256 hash of the request body using the secret as the key.

Verify the signature on your receiving server:

```php
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_AGENTIS_SIGNATURE'];
$expected = hash_hmac('sha256', $payload, $secret);

if (!hash_equals($expected, $signature)) {
    http_response_code(401);
    exit('Invalid signature');
}
```

## Delivery Logs

Every webhook delivery is logged with:

- HTTP status code received
- Response time
- Request payload
- Response body (truncated)
- Success/failure status

View delivery logs for a webhook:

```
GET /api/webhooks/{id}/deliveries
```

## Testing Webhooks

Click **Test** on a webhook to send a test payload. This sends a `ping` event to the configured URL so you can verify your endpoint is receiving and processing requests correctly.

```
POST /api/webhooks/{id}/test
```

## GitHub Inbound Webhook

Agentis Studio can receive GitHub push webhooks to automatically scan a project for new or changed skills.

### Setup

1. In your GitHub repository settings, add a webhook pointing to:
   ```
   https://your-agentis-url/api/webhooks/github/{project-id}
   ```
2. Set the content type to `application/json`
3. Select the **Push** event

When a push event is received, Agentis Studio runs a `ProjectScanJob` on the corresponding project, picking up any new or changed `.agentis/skills/*.md` files.

::: tip
Combine the GitHub inbound webhook with [git auto-commit](./git-integration) for a bidirectional sync: changes in the editor auto-commit to git, and pushes from other sources auto-scan into the database.
:::
