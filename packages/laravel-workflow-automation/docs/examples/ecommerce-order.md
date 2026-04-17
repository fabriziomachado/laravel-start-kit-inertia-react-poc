<div v-pre>

# E-Commerce Order Processing

Process incoming orders with VIP detection, per-item stock updates, and branching logic. High-value orders (over $500) get a VIP team notification before inventory sync; standard orders go straight to stock updates.

## What This Covers

```text
+------------------------------------------------------+
|  Nodes used in this example:                         |
|                                                      |
|  - manual          Trigger from controller code      |
|  - if_condition    Route by order total (> $500)     |
|  - send_mail       VIP team notification             |
|  - loop            Iterate over line items           |
|  - http_request    POST to inventory stock API       |
|                                                      |
|  Concepts: manual trigger, IF branching, branch      |
|  convergence, loop with _loop_item, HTTP POST,       |
|  env variable secrets                                |
+------------------------------------------------------+
```

## Workflow Diagram

```text
                                +-----------------------+
                                |   Manual Trigger      |
                                |   (new order)         |
                                +----------+------------+
                                           |
                                           v
                                +----------+------------+
                                |   IF: total > 500     |
                                +----+-------------+----+
                                     |             |
                                  true           false
                                     |             |
                                     v             |
                          +----------+----------+  |
                          |  Send Mail: VIP     |  |
                          |  (vip-team@store)   |  |
                          +----------+----------+  |
                                     |             |
                                     v             v
                                +----+-------------+----+
                                |   Loop: items         |
                                +----------+------------+
                                           | loop_item
                                           v
                                +----------+------------+
                                |  HTTP POST: stock API |
                                |  (update inventory)   |
                                +-----------------------+
```

## Workflow Setup

Create an artisan command to build this workflow once:

```php
// app/Console/Commands/SetupOrderWorkflow.php

use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use Illuminate\Console\Command;

class SetupOrderWorkflow extends Command
{
    protected $signature = 'workflow:setup-orders';
    protected $description = 'Create the e-commerce order processing workflow';

    public function handle(): void
    {
        $workflow = Workflow::create(['name' => 'E-Commerce Order Processing']);

        // 1. Manual trigger — receives order data from the controller
        $trigger = $workflow->addNode('New Order', 'manual');

        // 2. Check if the order total exceeds $500
        $vipCheck = $workflow->addNode('VIP Check', 'if_condition', [
            'field'    => '{{ item.total }}',
            'operator' => 'greater_than',
            'value'    => '500',
        ]);

        // 3. Notify the VIP team for high-value orders
        $vipEmail = $workflow->addNode('Notify VIP Team', 'send_mail', [
            'to'      => 'vip-team@store.com',
            'subject' => 'VIP Order #{{ item.order_id }} — ${{ item.total }}',
            'body'    => 'Customer {{ item.customer_name }} placed a VIP order totaling ${{ item.total }}. Please prioritize fulfillment.',
            'is_html' => false,
        ]);

        // 4. Loop over each line item in the order
        $loop = $workflow->addNode('Each Item', 'loop', [
            'source_field' => 'items',
        ]);

        // 5. POST to the inventory API to reserve stock for each SKU
        $stockUpdate = $workflow->addNode('Update Stock', 'http_request', [
            'url'    => 'https://inventory.store.com/api/reserve',
            'method' => 'POST',
            'body'   => [
                'sku'      => '{{ item._loop_item.sku }}',
                'quantity' => '{{ item._loop_item.quantity }}',
            ],
            'headers' => [
                'Authorization' => 'Bearer {{ env.INVENTORY_API_TOKEN }}',
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 15,
        ]);

        // Wire the graph
        $trigger->connect($vipCheck);
        $vipCheck->connect($vipEmail, 'true');     // VIP path
        $vipEmail->connect($loop);                  // VIP → loop
        $vipCheck->connect($loop, 'false');          // Standard → loop (merge)
        $loop->connect($stockUpdate, 'loop_item');

        $workflow->activate();

        $this->info("Order workflow created (ID: {$workflow->id})");
    }
}
```

Run it once:

```bash
php artisan workflow:setup-orders
```

## Triggering the Workflow

Call `start()` from your `OrderController` after an order is placed:

```php
// app/Http/Controllers/OrderController.php

use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use App\Models\Order;

class OrderController extends Controller
{
    public function store(Request $request)
    {
        $order = Order::create($request->validated());

        $workflow = Workflow::where('name', 'E-Commerce Order Processing')->firstOrFail();

        $run = $workflow->start([[
            'order_id'      => $order->id,
            'customer_name' => $order->customer_name,
            'total'         => $order->total,
            'items'         => [
                ['sku' => 'WIDGET-A', 'quantity' => 2],
                ['sku' => 'GADGET-B', 'quantity' => 1],
                ['sku' => 'CABLE-C',  'quantity' => 5],
            ],
        ]]);

        // $run->status === 'completed'

        return response()->json(['order_id' => $order->id, 'workflow_run' => $run->id]);
    }
}
```

## What Happens

1. **Manual trigger** receives the order payload containing `order_id`, `customer_name`, `total`, and an `items` array.
2. **IF Condition** evaluates `total > 500`. A $750 order goes to the `true` branch; a $120 order goes to `false`.
3. **Send Mail (VIP)** fires only for high-value orders. The VIP team at `vip-team@store.com` gets an email with the order details.
4. **Loop** iterates over the `items` array. Each element becomes a separate item with `_loop_item` containing `sku` and `quantity`.
5. **HTTP POST** runs once per loop iteration, calling the inventory API with the SKU and quantity from the current loop element.

Both the VIP path (after the email) and the standard path converge on the same Loop node, so every order gets its stock updated regardless of value.

## Concepts Demonstrated

| Concept | How It Is Used |
|---------|----------------|
| Manual trigger | Order data is pushed from the controller via `$workflow->start()` |
| IF condition with branching | Routes orders to VIP or standard paths based on `total > 500` |
| Named output ports | `$vipCheck->connect($vipEmail, 'true')` and `$vipCheck->connect($loop, 'false')` |
| Branch convergence | Both `true` and `false` branches connect into the same Loop node |
| Loop node | Expands the `items` array so each SKU is processed individually |
| Loop item expressions | `{{ item._loop_item.sku }}` accesses the current element inside the loop |
| HTTP request with body | POSTs JSON to an external inventory API with dynamic interpolation |
| Environment variables in expressions | `{{ env.INVENTORY_API_TOKEN }}` injects a secret without hardcoding |


</div>
