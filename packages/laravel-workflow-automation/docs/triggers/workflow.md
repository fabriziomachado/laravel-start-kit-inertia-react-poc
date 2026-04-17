<div v-pre>

# Workflow Trigger

The `workflow` trigger fires automatically when another workflow completes or fails. This enables **workflow chaining** — connecting workflows together so the output of one becomes the input of another.

**Node key:** `workflow`

## Config

| Key | Type | Required | Expression | Description |
| --- | --- | --- | --- | --- |
| `source_workflow_id` | workflow_select | No | No | Source workflow to listen to (leave empty to listen to any workflow) |
| `trigger_on` | select | Yes | No | When to trigger: `completed`, `failed`, or `any` |

## Ports

| Direction | Port | Description |
| --- | --- | --- |
| Output | `main` | Source workflow's metadata and output data |

## Output Data

When the trigger fires, it outputs a single item with this structure:

```json
{
  "source_workflow_id": 1,
  "source_run_id": 42,
  "source_status": "completed",
  "error_message": null,
  "data": { /* source workflow's full context/output */ }
}
```

## How It Works

```text
Workflow A completes
        │
        ▼ (WorkflowCompleted event)
  ┌───────────────────────┐
  │ WorkflowChainListener │
  └──────┬────────────────┘
         │ matches trigger config?
         ▼
  ┌──────────────────────┐
  │ Workflow Trigger (B) │
  └──────┬───────────────┘
         │ main
         ▼
  [{ source_workflow_id, source_run_id, source_status, data }]
```

## Example: Order → Shipping → Invoice

Three independent workflows chained together:

```php
// 1. Order Processing Workflow (runs first)
$orderWf = Workflow::create(['name' => 'Process Order']);
$orderTrigger = $orderWf->addNode('New Order', 'webhook', [
    'method' => 'POST',
]);
$processOrder = $orderWf->addNode('Process', 'set_fields', [
    'fields' => ['status' => 'processed'],
]);
$orderTrigger->connect($processOrder);
$orderWf->activate();

// 2. Shipping Workflow (triggered when order completes)
$shippingWf = Workflow::create(['name' => 'Ship Order']);
$shippingTrigger = $shippingWf->addNode('Order Done', 'workflow', [
    'source_workflow_id' => $orderWf->id,
    'trigger_on'         => 'completed',
]);
$ship = $shippingWf->addNode('Ship', 'http_request', [
    'url'    => 'https://shipping-api.com/create',
    'method' => 'POST',
    'body'   => '{{ item.data }}',
]);
$shippingTrigger->connect($ship);
$shippingWf->activate();

// 3. Invoice Workflow (triggered when shipping completes)
$invoiceWf = Workflow::create(['name' => 'Send Invoice']);
$invoiceTrigger = $invoiceWf->addNode('Shipping Done', 'workflow', [
    'source_workflow_id' => $shippingWf->id,
    'trigger_on'         => 'completed',
]);
$invoice = $invoiceWf->addNode('Send Invoice', 'send_mail', [
    'to'      => '{{ item.data.customer_email }}',
    'subject' => 'Your invoice',
    'body'    => 'Order has been shipped and invoiced.',
]);
$invoiceTrigger->connect($invoice);
$invoiceWf->activate();
```

## Example: Error Handling Workflow

A dedicated workflow that runs whenever any workflow fails:

```php
$errorHandler = Workflow::create(['name' => 'Global Error Handler']);

$trigger = $errorHandler->addNode('Any Failure', 'workflow', [
    'source_workflow_id' => null, // listens to ALL workflows
    'trigger_on'         => 'failed',
]);

$notify = $errorHandler->addNode('Alert Team', 'send_notification', [
    'channel' => 'slack',
    'message' => 'Workflow {{ item.source_workflow_id }} (run {{ item.source_run_id }}) failed: {{ item.error_message }}',
]);

$trigger->connect($notify);
$errorHandler->activate();
```

## Chain Depth Protection

To prevent infinite chain loops (A → B → A → B → ...), a maximum depth is enforced. The default limit is 10 levels.

Configure it in `config/workflow-automation.php`:

```php
'chaining' => [
    'max_depth' => 10, // or env('WORKFLOW_CHAIN_MAX_DEPTH', 10)
],
```

When the limit is reached, the chain stops and a warning is logged.

## Self-Triggering Prevention

A workflow cannot trigger itself. If workflow A has a workflow trigger listening to workflow A, it will be silently skipped. Use the [Loop node](/nodes/loop) for repetitive execution within a workflow.

## Caching

Active workflow triggers are cached for 60 seconds. After creating or modifying a workflow trigger:

- Wait up to 60 seconds for automatic cache refresh, or
- Clear manually: `Cache::forget('workflow:workflow_triggers')`

## Advanced: Workflow Chaining

The Workflow Trigger is one of two mechanisms for connecting workflows together. For a comprehensive comparison of both approaches (Workflow Trigger vs Sub Workflow), decision guides, and advanced patterns, see the **[Workflow Chaining](/advanced/workflow-chaining)** guide.

## Tips

- Chained workflows are always dispatched **asynchronously** via the queue
- For synchronous parent-child execution, use the [Sub Workflow](/nodes/sub-workflow) control node instead
- Set `source_workflow_id` to `null` to create a "catch-all" trigger that responds to any workflow
- Use `trigger_on: failed` to build error handling workflows
- Access the source workflow's output via `{{ item.data }}` in expressions
- Each chained workflow creates its own independent `WorkflowRun` record

</div>
