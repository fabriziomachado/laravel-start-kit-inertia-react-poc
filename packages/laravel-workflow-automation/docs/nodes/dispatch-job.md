# Dispatch Job

Dispatches a Laravel queued job for each item.

**Node key:** `dispatch_job` · **Type:** Action

## Config

| Key | Type | Required | Expression | Description |
| --- | --- | --- | --- | --- |
| `job_class` | string | Yes | No | Fully qualified job class (e.g. `App\\Jobs\\GeneratePdf`) |
| `queue` | string | No | No | Queue name (uses default if omitted) |
| `delay` | integer | No | No | Delay in seconds before the job runs |
| `with_item` | boolean | No | No | Pass the current item data to the job constructor |

## Ports

| Direction | Port | Description |
| --- | --- | --- |
| Input | `main` | Items to process |
| Output | `main` | Items that were dispatched (with `job_dispatched` field) |
| Output | `error` | Items that failed to dispatch |

## Behavior

For each input item:

1. Instantiates the `job_class`. If `with_item` is `true`, passes the item array as the first constructor argument
2. If `queue` is set, routes to that queue via `->onQueue(...)`
3. If `delay` is set, schedules via `->delay(...)` in seconds
4. Dispatches the job and moves on — the workflow does **not** wait for the job to complete

## Example

```php
$dispatch = $workflow->addNode('Generate Invoice', 'dispatch_job', [
    'job_class' => 'App\\Jobs\\GenerateInvoicePdf',
    'queue'     => 'pdfs',
    'delay'     => 5,
    'with_item' => true,
]);
```

Your job class when `with_item` is enabled:

```php
class GenerateInvoicePdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public array $data) {}

    public function handle(): void
    {
        $order = Order::find($this->data['id']);
        // Generate PDF...
    }
}
```

## Input / Output Example

**Input:**

```php
[
    ['id' => 42, 'customer' => 'Alice', 'total' => 99.90],
]
```

**Output (on `main`):**

```php
[
    ['id' => 42, 'customer' => 'Alice', 'total' => 99.90, 'job_dispatched' => 'App\\Jobs\\GenerateInvoicePdf'],
]
```

## Tips

- When `with_item` is `true`, the job class must accept an `array` as its first constructor argument
- The workflow continues immediately after dispatch — it doesn't wait for the job
- Use a separate trigger (model event, webhook) if you need to react to job completion
