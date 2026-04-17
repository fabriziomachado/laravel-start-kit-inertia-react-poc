<div v-pre>

# Event Trigger

The `event` trigger fires automatically when a Laravel event is dispatched. Unlike the `model_event` trigger which only handles Eloquent lifecycle events, this trigger works with any Laravel event class — domain events, notification events, third-party package events, and more.

**Node key:** `event`

## Config

| Key | Type | Required | Expression | Description |
| --- | --- | --- | --- | --- |
| `event_class` | string | Yes | No | Fully qualified event class (e.g. `App\\Events\\OrderPlaced`) |

## Ports

| Direction | Port | Description |
| --- | --- | --- |
| Output | `main` | The event's public properties as an associative array, wrapped in a single-item array |

## Setup

No manual setup needed — the package automatically registers event listeners when the application boots. Active trigger configurations are cached for 60 seconds.

::: tip
If you create a new workflow while the app is running, the trigger will be picked up automatically after the cache expires (60 seconds) or after clearing the cache with `Cache::forget('workflow:event_triggers')`.
:::

## How It Works

```text
event(new OrderPlaced($order))
          │
          ▼ (Laravel event dispatcher)
   ┌───────────────────┐
   │ Event Trigger     │
   └──────┬────────────┘
          │ main
          ▼
   [{'order': {'id': 1, 'total': 250}, 'user': {'id': 5, 'name': 'Alice'}}]
```

## Example: Notify Team on Order Placement

First, create your Laravel event:

```php
// app/Events/OrderPlaced.php

namespace App\Events;

class OrderPlaced
{
    public function __construct(
        public array $order,
        public array $customer,
    ) {}
}
```

Then define the workflow:

```php
$workflow = Workflow::create(['name' => 'Order Notification']);

$trigger = $workflow->addNode('Order Placed', 'event', [
    'event_class' => 'App\\Events\\OrderPlaced',
]);

$email = $workflow->addNode('Notify Team', 'send_mail', [
    'to'      => 'orders@company.com',
    'subject' => 'New Order: ${{ item.order.total }}',
    'body'    => 'Customer {{ item.customer.name }} placed an order.',
]);

$trigger->connect($email);
$workflow->activate();
```

Now whenever the event is dispatched, the workflow fires:

```php
event(new \App\Events\OrderPlaced(
    order: ['id' => 1, 'total' => 250],
    customer: ['name' => 'Alice', 'email' => 'alice@example.com'],
));
// → Workflow triggers, team gets notified
```

## Example: Handle Payment Failures

```php
// app/Events/PaymentFailed.php

class PaymentFailed
{
    public function __construct(
        public int $orderId,
        public string $reason,
        public string $customerEmail,
    ) {}
}
```

```php
$workflow = Workflow::create(['name' => 'Payment Failure Handler']);

$trigger = $workflow->addNode('Payment Failed', 'event', [
    'event_class' => 'App\\Events\\PaymentFailed',
]);

$notifyCustomer = $workflow->addNode('Notify Customer', 'send_mail', [
    'to'      => '{{ item.customerEmail }}',
    'subject' => 'Payment issue with order #{{ item.orderId }}',
    'body'    => 'Your payment could not be processed: {{ item.reason }}',
]);

$trigger->connect($notifyCustomer);
$workflow->activate();
```

## Payload Extraction

The trigger converts the event object into an array:

1. If the event has a `toArray()` method, it uses that
2. Otherwise, it extracts all **public properties** via `get_object_vars()`

```php
// Event class:
class OrderPlaced
{
    public function __construct(
        public array $order,
        public string $source,
    ) {}
}

// Output on `main` port:
[
    [
        'order'  => ['id' => 1, 'total' => 250],
        'source' => 'web',
    ],
]
```

## Tips

- The event data is always a single item: `[extractedProperties]`
- If multiple workflows listen to the same event, they all fire independently
- Workflows are dispatched asynchronously via the queue by default
- Use `toArray()` on your event class if you want to customize which data enters the workflow
- Private and protected properties are **not** included — only public properties are extracted
- This trigger does **not** handle Eloquent model events (use the `model_event` trigger for those)

</div>
