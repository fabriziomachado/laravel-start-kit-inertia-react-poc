# E-Commerce Order Processing

> English | **[Türkçe](tr/01-e-ticaret-siparis-isleme.md)**

When a customer places an order, check if it's high-value, notify the VIP team if it is, and update inventory for each item. This example shows branching (`if_condition`), looping (`loop`), and calling external APIs (`http_request`).

## Flow

```
[Manual Trigger] → [IF: total > 500]
                       ├─ true  → [Send Mail: VIP notice] → [Loop: items] → [HTTP: update stock]
                       └─ false → [Loop: items] → [HTTP: update stock]
```

## Step 1 — Define the Workflow

Create an artisan command and run it once with `php artisan workflow:setup-orders`.

```php
// app/Console/Commands/SetupOrderWorkflow.php

use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use Illuminate\Console\Command;

class SetupOrderWorkflow extends Command
{
    protected $signature = 'workflow:setup-orders';
    protected $description = 'Create the order processing workflow';

    public function handle(): void
    {
        $workflow = Workflow::create(['name' => 'Order Processing']);

        $trigger = $workflow->addNode('New Order', 'manual');

        $checkAmount = $workflow->addNode('High Value?', 'if_condition', [
            'field'    => 'total',
            'operator' => 'greater_than',
            'value'    => 500,
        ]);

        $notifyVip = $workflow->addNode('Notify VIP Team', 'send_mail', [
            'to'      => 'vip-team@store.com',
            'subject' => 'VIP Order #{{ item.order_id }} — ${{ item.total }}',
            'body'    => '{{ item.customer_name }} placed a ${{ item.total }} order.',
        ]);

        $loop = $workflow->addNode('Each Item', 'loop', [
            'source_field' => 'items',
        ]);

        $updateStock = $workflow->addNode('Update Stock', 'http_request', [
            'url'    => 'https://inventory.api/stock/decrement',
            'method' => 'POST',
            'body'   => [
                'sku'      => '{{ item._loop_item.sku }}',
                'quantity' => '{{ item._loop_item.quantity }}',
            ],
        ]);

        // Edges
        $trigger->connect($checkAmount);
        $checkAmount->connect($notifyVip, sourcePort: 'true');
        $notifyVip->connect($loop);
        $checkAmount->connect($loop, sourcePort: 'false');
        $loop->connect($updateStock, sourcePort: 'loop_item');

        $workflow->activate();

        $this->info("Order Processing workflow created (ID: {$workflow->id})");
    }
}
```

Both branches converge on the same `$loop` node — VIP orders get an email first, then both paths update stock. No need to duplicate nodes.

## Step 2 — Trigger from a Controller

```php
// app/Http/Controllers/OrderController.php

use Aftandilmmd\WorkflowAutomation\Models\Workflow;

class OrderController extends Controller
{
    public function store(StoreOrderRequest $request): JsonResponse
    {
        $order = Order::create($request->validated());

        $workflow = Workflow::where('name', 'Order Processing')->firstOrFail();

        $workflow->start([[
            'order_id'      => $order->id,
            'customer_name' => $order->customer_name,
            'total'         => $order->total,
            'items'         => $order->items->map(fn ($i) => [
                'sku'      => $i->sku,
                'quantity' => $i->quantity,
            ])->toArray(),
        ]]);

        return response()->json(['order' => $order], 201);
    }
}
```

## What Happens

Given an order with `total: 750` and 2 items:

1. **IF Condition** — `750 > 500` = true → VIP path
2. **Send Mail** — VIP team gets notified
3. **Loop** — Iterates over `items` array (2 items)
4. **HTTP Request** — Calls inventory API twice, once per item

If the order were $200, it skips the email and goes straight to the loop.

## Concepts Demonstrated

| Concept | How |
|---------|-----|
| Branching | `if_condition` with `sourcePort: 'true'` / `'false'` |
| Converging branches | Both paths connect to the same `$loop` node |
| Looping | `loop` node expands an array, processes each via `loop_item` port |
| External API calls | `http_request` with expression-based body |
