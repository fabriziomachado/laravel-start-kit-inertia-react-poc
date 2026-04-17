# Manual Trigger

The `manual` trigger starts a workflow when you explicitly call `$workflow->start()` from your code or the REST API. This is the simplest trigger type and a good starting point for testing.

**Node key:** `manual`

## Config

| Key | Type | Required | Expression | Description |
| --- | --- | --- | --- | --- |
| `input_schema` | json | No | No | Expected input schema (for documentation/validation) |

## Ports

| Direction | Port | Description |
| --- | --- | --- |
| Output | `main` | All items from the payload |

## How It Works

The manual trigger is a pass-through: whatever items you pass to `start()` are forwarded to the `main` output port unchanged.

```text
start([{name: "Alice"}, {name: "Bob"}])
          │
          ▼
   ┌───────────────┐
   │ Manual Trigger│
   └──────┬────────┘
          │ main
          ▼
   [{name: "Alice"}, {name: "Bob"}]
```

## Triggering from PHP

```php
use Aftandilmmd\WorkflowAutomation\Models\Workflow;

$workflow = Workflow::where('name', 'My Workflow')->firstOrFail();

// Single item
$run = $workflow->start([
    ['name' => 'Alice', 'email' => 'alice@example.com'],
]);

// Multiple items — each flows through every node independently
$run = $workflow->start([
    ['name' => 'Alice', 'email' => 'alice@example.com', 'total' => 250],
    ['name' => 'Bob',   'email' => 'bob@example.com',   'total' => 800],
]);

echo $run->status->value; // "completed"
```

## Triggering from the REST API

```http
POST /workflow-engine/workflows/{id}/run
Content-Type: application/json

{
  "payload": [
    {"name": "Alice", "email": "alice@example.com"}
  ]
}
```

## Triggering Asynchronously

```php
// Dispatches to the queue and returns immediately
$workflow->startAsync([
    ['name' => 'Alice', 'email' => 'alice@example.com'],
]);
```

## Example: Order Confirmation

```php
$workflow = Workflow::create(['name' => 'Order Confirmation']);

$trigger = $workflow->addNode('New Order', 'manual');
$email   = $workflow->addNode('Confirm', 'send_mail', [
    'to'      => '{{ item.email }}',
    'subject' => 'Order #{{ item.order_id }} received',
    'body'    => 'Thank you for your order of ${{ item.total }}.',
]);

$trigger->connect($email);
$workflow->activate();

// Later, from a controller:
$workflow->start([
    ['order_id' => 42, 'email' => 'alice@example.com', 'total' => 99.90],
]);
```

## Input / Output

**Input:** None (triggers have no input ports)

**Output on `main` port:**

Whatever you pass to `start()`:

```php
// start([['name' => 'Alice', 'total' => 500]])

// Output:
[
    ['name' => 'Alice', 'total' => 500],
]
```

## Tips

- The payload must be an array of arrays (items). A single item is `[[...]]`.
- For automatic triggering on model events, use the [Model Event](/triggers/model-event) trigger instead.
- For HTTP-based triggering from external services, use the [Webhook](/triggers/webhook) trigger.
- For time-based triggering, use the [Schedule](/triggers/schedule) trigger.
