# Webhook Trigger

The `webhook` trigger starts a workflow when an external service sends an HTTP request to a unique URL. Useful for integrating with Stripe, GitHub, Slack, and any service that supports outbound webhooks.

**Node key:** `webhook`

## Config

| Key | Type | Required | Expression | Description |
| --- | --- | --- | --- | --- |
| `path` | string | Auto | No | Auto-generated UUID path (read-only) |
| `method` | select | Yes | No | HTTP method: `GET`, `POST`, `PUT`, `PATCH` |
| `auth_type` | select | Yes | No | Authentication: `none`, `basic`, `bearer`, `header_key` |
| `credential_id` | credential | No | No | Encrypted credential (see [Credential Vault](/advanced/credentials)) |
| `auth_value` | string | No | No | Auth credentials — legacy, use `credential_id` instead |

## Ports

| Direction | Port | Description |
| --- | --- | --- |
| Output | `main` | The request payload as a single item |

## Webhook URL

When you create a webhook trigger node, the package generates a UUID and registers a route:

```text
POST /workflow-webhook/{uuid}
```

The full URL looks like:

```text
https://yourapp.com/workflow-webhook/a1b2c3d4-e5f6-7890-abcd-ef1234567890
```

The prefix is configurable:

```php
// config/workflow-automation.php
'webhook_prefix' => 'workflow-webhook',
```

## How It Works

```text
External Service (Stripe, GitHub, etc.)
          │
          │ POST /workflow-webhook/{uuid}
          │ Body: {"event": "payment.success", "amount": 99.90}
          ▼
   ┌───────────────────┐
   │ Webhook Trigger   │
   └──────┬────────────┘
          │ main
          ▼
   [{"event": "payment.success", "amount": 99.90}]
```

## Authentication Types

| Type | How It Works |
| --- | --- |
| `none` | No authentication check |
| `basic` | Checks `Authorization: Basic {base64}` header |
| `bearer` | Checks `Authorization: Bearer {token}` header against `auth_value` |
| `header_key` | Checks `X-Webhook-Secret` header against `auth_value` |

## Example: Stripe Payment Webhook

Using a credential (recommended):

```php
$workflow = Workflow::create(['name' => 'Stripe Payment Handler']);

// First, create a credential via API or code:
// WorkflowCredential::create(['name' => 'Stripe Webhook', 'type' => 'bearer_token', 'data' => ['token' => 'whsec_xxx']]);

$trigger = $workflow->addNode('Stripe Hook', 'webhook', [
    'method'        => 'POST',
    'auth_type'     => 'bearer',
    'credential_id' => 1, // references the stored credential
]);

$router = $workflow->addNode('Route Event', 'switch', [
    'field' => '{{ item.type }}',
    'cases' => [
        ['port' => 'case_success', 'operator' => 'equals', 'value' => 'payment_intent.succeeded'],
        ['port' => 'case_failed',  'operator' => 'equals', 'value' => 'payment_intent.payment_failed'],
    ],
]);

$receipt = $workflow->addNode('Send Receipt', 'send_mail', [
    'to'      => '{{ item.data.object.receipt_email }}',
    'subject' => 'Payment received',
    'body'    => 'Thank you for your payment.',
]);

$trigger->connect($router);
$router->connect($receipt, 'case_success');
$workflow->activate();
```

## Input / Output

**Input:** None (triggers have no input ports)

**Output on `main` port:**

```php
// POST /workflow-webhook/{uuid}
// Body: {"event": "order.created", "order_id": 42, "total": 99.90}

// Output:
[
    ['event' => 'order.created', 'order_id' => 42, 'total' => 99.90],
]
```

## Tips

- Webhook routes bypass the package's configurable middleware — they only use the `api` middleware
- The request body is parsed as JSON and passed as `[$request->all()]`
- Use `credential_id` with `auth_type: bearer` for production webhooks — secrets are encrypted at rest
- The auto-generated UUID in the `path` config is set when the node is created and remains constant
- Multiple workflows can each have their own webhook URL — each gets a unique UUID
