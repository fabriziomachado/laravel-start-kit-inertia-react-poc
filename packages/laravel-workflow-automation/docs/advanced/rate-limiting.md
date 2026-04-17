# Rate Limiting / Concurrency

Control how many workflow runs can execute simultaneously — both globally across all workflows and per individual workflow.

## Configuration

Add to `config/workflow-automation.php`:

```php
'rate_limiting' => [
    'global_max_concurrent'      => env('WORKFLOW_GLOBAL_MAX_CONCURRENT', 0),
    'max_concurrent_per_workflow' => env('WORKFLOW_MAX_CONCURRENT_PER_WORKFLOW', 0),
    'strategy'                   => env('WORKFLOW_RATE_LIMIT_STRATEGY', 'exception'),
    'queue_retry_delay'          => env('WORKFLOW_RATE_LIMIT_RETRY_DELAY', 30),
],
```

| Key | Default | Description |
|-----|---------|-------------|
| `global_max_concurrent` | `0` | Maximum concurrent runs across **all** workflows. `0` = unlimited. |
| `max_concurrent_per_workflow` | `0` | Default per-workflow limit. `0` = unlimited. |
| `strategy` | `exception` | What happens when a limit is hit: `exception` or `queue`. |
| `queue_retry_delay` | `30` | Seconds to wait before retrying a rate-limited queued job. |

## Per-Workflow Override

Each workflow can override the default per-workflow limit via its `settings` JSON column:

```php
$workflow->update([
    'settings' => ['max_concurrent_runs' => 3],
]);
```

This takes precedence over the `max_concurrent_per_workflow` config value.

## Strategies

### `exception` (default)

When the concurrency limit is reached, a `RateLimitExceededException` is thrown immediately. This works for both synchronous and asynchronous execution.

```php
use Aftandilmmd\WorkflowAutomation\Exceptions\RateLimitExceededException;

try {
    $service->run($workflow, $payload);
} catch (RateLimitExceededException $e) {
    // $e->workflowId    — the workflow that was blocked
    // $e->currentRuns   — how many runs are currently active
    // $e->maxConcurrent — the configured limit
    // $e->scope         — 'workflow' or 'global'
}
```

### `queue`

When a queued job hits the concurrency limit, it is **released back to the queue** and retried after `queue_retry_delay` seconds. This is only applicable to async (queued) execution — synchronous calls always throw.

```bash
WORKFLOW_RATE_LIMIT_STRATEGY=queue
WORKFLOW_RATE_LIMIT_RETRY_DELAY=30
```

## Checking Status

### PHP API

```php
$service = app(WorkflowService::class);

// Boolean check
$canRun = $service->canRun($workflow); // true/false

// Detailed status
$status = $service->rateLimitStatus($workflow);
// Returns:
// [
//     'global' => [
//         'max_concurrent' => 10,
//         'active_runs'    => 3,
//         'available'      => 7,
//     ],
//     'workflow' => [
//         'max_concurrent' => 5,
//         'active_runs'    => 2,
//         'available'      => 3,
//     ],
//     'can_run' => true,
// ]
```

### ConcurrencyGuard

For lower-level access, use the `ConcurrencyGuard` service directly:

```php
use Aftandilmmd\WorkflowAutomation\Services\ConcurrencyGuard;

$guard = app(ConcurrencyGuard::class);

$guard->acquire($workflow);  // throws RateLimitExceededException
$guard->canRun($workflow);   // returns bool
$guard->status($workflow);   // returns detailed array
```

## MCP Tool

The `rate_limit_status` MCP tool is available for AI agents:

```
Tool: rate_limit_status
Input: { "workflow_id": 1 }
```

## How It Works

- **Active runs** are counted as runs with `pending` or `running` status.
- `completed`, `failed`, `cancelled`, and `waiting` runs do **not** count.
- A cache lock is used to prevent race conditions when checking limits concurrently.
- Global limits are checked **before** per-workflow limits.

## Examples

### Limit a single heavy workflow

```php
// Only allow 1 run at a time for this workflow
$workflow->update([
    'settings' => ['max_concurrent_runs' => 1],
]);
```

### System-wide protection

```bash
# Max 20 workflows running at once across the entire system
WORKFLOW_GLOBAL_MAX_CONCURRENT=20

# Default 5 per workflow
WORKFLOW_MAX_CONCURRENT_PER_WORKFLOW=5
```

### Queue-based throttling

```bash
# Release jobs back to queue when limited
WORKFLOW_RATE_LIMIT_STRATEGY=queue
WORKFLOW_RATE_LIMIT_RETRY_DELAY=60
```
