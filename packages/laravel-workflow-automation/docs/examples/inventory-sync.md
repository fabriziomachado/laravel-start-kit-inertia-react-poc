# Inventory Sync

Receive product updates from a supplier webhook, loop through each product, and update internal stock levels via an API. Errors are routed through an error handler: timeouts trigger a retry, 404s are silently ignored, and all other errors send a notification to the ops team.

## What This Covers

```text
+------------------------------------------------------+
|  Nodes used in this example:                         |
|                                                      |
|  - webhook         Receive supplier product updates  |
|  - loop            Iterate over each product         |
|  - http_request    POST stock update to internal API |
|  - error_handler   Route errors by regex pattern     |
|  - send_notification  Alert ops team on failures     |
|  - http_request    Retry timed-out requests          |
|                                                      |
|  Concepts: webhook with bearer auth, loop node,      |
|  error port routing, error handler with regex rules, |
|  default route, unconnected ignore port, retry       |
|  pattern, send_notification node                     |
+------------------------------------------------------+
```

## Workflow Diagram

```text
    +------------------+     +------------------+     +------------------+
    |  Webhook:        | --> |  Loop:           | --> |  HTTP POST:      |
    |  supplier POST   |     |  each product    |     |  update stock    |
    +------------------+     +--+---------------+     +--------+---------+
                                                               |
                                                          main | error
                                                               v
                                                      +--------+---------+
                                                      |  Error Handler   |
                                                      +--+------+----+--+
                                                         |      |    |
                                                      notify  retry  ignore
                                                         |      |    |
                                                         v      v    v
                                              +----------+-+ +--+--+ (end)
                                              | Send        | | HTTP |
                                              | Notification| | retry|
                                              +------------+ +------+
```

## Workflow Setup

```php
// app/Console/Commands/SetupInventorySync.php

use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use Illuminate\Console\Command;

class SetupInventorySync extends Command
{
    protected $signature = 'workflow:setup-inventory-sync';
    protected $description = 'Create the inventory sync workflow';

    public function handle(): void
    {
        $workflow = Workflow::create(['name' => 'Inventory Sync']);

        // 1. Webhook receives product updates from the supplier
        $trigger = $workflow->addNode('Supplier Webhook', 'webhook', [
            'method'     => 'POST',
            'auth_type'  => 'bearer',
            'auth_value' => config('services.supplier.webhook_secret'),
        ]);

        // 2. Loop through each product in the payload
        $loop = $workflow->addNode('Each Product', 'loop', [
            'source_field' => 'products',
        ]);

        // 3. Update internal stock via API
        $updateStock = $workflow->addNode('Update Stock', 'http_request', [
            'url'    => 'https://internal.yourapp.com/api/inventory/update',
            'method' => 'POST',
            'body'   => [
                'sku'      => '{{ item._loop_item.sku }}',
                'quantity' => '{{ item._loop_item.quantity }}',
                'price'    => '{{ item._loop_item.unit_price }}',
            ],
            'headers' => [
                'Authorization' => 'Bearer {{ env.INTERNAL_API_TOKEN }}',
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 15,
        ]);

        // 4. Error handler routes failures by error pattern
        $errorHandler = $workflow->addNode('Handle Errors', 'error_handler', [
            'rules' => [
                ['match' => 'timeout',       'route' => 'retry'],
                ['match' => '404|not.found', 'route' => 'ignore'],
            ],
            'default_route' => 'notify',
        ]);

        // 5a. Notify — send notification for unexpected errors
        $notifyOps = $workflow->addNode('Notify Ops Team', 'send_notification', [
            'notification_class' => 'App\\Notifications\\InventoryError',
            'notifiable_class'   => 'App\\Models\\User',
            'notifiable_id'      => '{{ env.OPS_TEAM_USER_ID }}',
        ]);

        // 5b. Retry — re-attempt the stock update
        $retryStock = $workflow->addNode('Retry Stock Update', 'http_request', [
            'url'    => 'https://internal.yourapp.com/api/inventory/update',
            'method' => 'POST',
            'body'   => [
                'sku'      => '{{ item._loop_item.sku }}',
                'quantity' => '{{ item._loop_item.quantity }}',
                'price'    => '{{ item._loop_item.unit_price }}',
            ],
            'headers' => [
                'Authorization' => 'Bearer {{ env.INTERNAL_API_TOKEN }}',
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 30,
        ]);

        // Wire the graph
        $trigger->connect($loop);
        $loop->connect($updateStock, 'loop_item');
        $updateStock->connect($errorHandler, 'error');        // Failures go to error handler
        $errorHandler->connect($notifyOps, 'notify');
        $errorHandler->connect($retryStock, 'retry');
        // 'ignore' port is not connected — those items are silently dropped

        $workflow->activate();

        $this->info("Inventory sync workflow created (ID: {$workflow->id})");
        $this->info("Webhook URL: /workflow-webhook/{$trigger->config['path']}");
    }
}
```

## Notification Class

Create the notification that the error handler sends when unexpected errors occur:

```php
// app/Notifications/InventoryError.php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InventoryError extends Notification
{
    use Queueable;

    public function __construct(public array $data)
    {
    }

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Inventory Sync Error')
            ->line('An error occurred while syncing inventory.')
            ->line('SKU: ' . ($this->data['_loop_item']['sku'] ?? 'unknown'))
            ->line('Error: ' . ($this->data['error'] ?? 'unknown'))
            ->action('View Dashboard', url('/admin/inventory'));
    }

    public function toArray($notifiable): array
    {
        return [
            'sku'   => $this->data['_loop_item']['sku'] ?? 'unknown',
            'error' => $this->data['error'] ?? 'unknown',
        ];
    }
}
```

## Supplier Webhook Payload

The supplier sends a payload like this to your webhook URL:

```json
{
    "supplier_id": "SUP-001",
    "timestamp": "2025-01-15T10:30:00Z",
    "products": [
        { "sku": "WIDGET-A", "quantity": 150, "unit_price": 12.99 },
        { "sku": "GADGET-B", "quantity": 0, "unit_price": 29.99 },
        { "sku": "CABLE-C", "quantity": 500, "unit_price": 4.50 }
    ]
}
```

## What Happens

1. **Webhook** receives the supplier's POST request. Bearer authentication validates the shared secret.
2. **Loop** iterates over the `products` array. Each product becomes a separate item with `_loop_item` containing `sku`, `quantity`, and `unit_price`.
3. **HTTP POST** calls the internal inventory API for each product. On success, the item flows out the `main` port (and the workflow ends for that item). On failure, the item flows out the `error` port.
4. **Error Handler** inspects the error message on each failed item and applies regex rules in order:
   - If the error contains `"timeout"`, the item is routed to the `retry` port.
   - If the error contains `"404"` or `"not found"`, the item is routed to the `ignore` port (silently dropped).
   - All other errors fall through to the `default_route` of `notify`.
5. **Retry** re-attempts the stock update with a longer timeout (30s instead of 15s).
6. **Notify** sends a Laravel notification to the ops team user, including the SKU and error details via mail and database channels.

## Concepts Demonstrated

| Concept | How It Is Used |
|---------|----------------|
| Webhook trigger with bearer auth | Secures the supplier endpoint with a shared secret token |
| Loop node | Iterates over the `products` array for per-product processing |
| HTTP request error port | Failed requests automatically route to the `error` output port |
| Error handler node | Routes errors to `retry`, `ignore`, or `notify` based on regex pattern matching |
| Regex-based error routing | `timeout` and `404\|not.found` patterns match specific failure types |
| Default route fallback | Unmatched errors go to `notify` as the default route |
| Unconnected port (ignore) | The `ignore` port has no downstream connection -- items are silently dropped |
| Send notification node | Dispatches a Laravel Notification to a notifiable model with full item data |
| Retry pattern | A second HTTP request node with a longer timeout handles transient failures |
