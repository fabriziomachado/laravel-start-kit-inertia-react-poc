# Error Handling

## Error Ports

Most action nodes have an `error` output port alongside their `main` port. When execution fails after all retries, the error is routed to the `error` port instead of crashing the entire workflow.

```
[HTTP Request] → main  → [Process Response]
              → error → [Error Handler] → notify → [Send Alert]
```

The error item contains:
```json
{
  "error": "Connection timed out",
  "input": [{"url": "https://api.example.com/data"}]
}
```

## Error Handler Node

The `error_handler` node routes errors to different ports based on regex pattern matching:

```php
$errorHandler = $workflow->addNode('Handle Errors', 'error_handler', [
    'rules' => [
        ['match' => 'timeout|timed.out', 'route' => 'retry'],
        ['match' => '404|not.found',     'route' => 'ignore'],
        ['match' => '5\\d{2}',           'route' => 'retry'],
        ['match' => 'validation',        'route' => 'notify'],
    ],
    'default_route' => 'stop',
]);
```

Output ports: `notify`, `retry`, `ignore`, `stop`.

Connect each port to appropriate downstream actions:

```php
$httpNode->connect($errorHandler, sourcePort: 'error');

$errorHandler->connect($retryNode, sourcePort: 'retry');
$errorHandler->connect($alertEmail, sourcePort: 'notify');
// 'ignore' and 'stop' ports left unconnected — items are discarded
```

## Retry Configuration

### Per-Node Retry

Add `retry_count` and `retry_delay_ms` to any node's config:

```php
$httpNode = $workflow->addNode('Call API', 'http_request', [
    'url'            => 'https://api.example.com/data',
    'method'         => 'GET',
    'retry_count'    => 3,
    'retry_delay_ms' => 2000,
]);
```

### Global Defaults

Set in `config/workflow-automation.php`:

```php
'default_retry_count'    => 0,       // No retries by default
'default_retry_delay_ms' => 1000,    // 1 second base delay
'retry_backoff'          => 'exponential', // or 'linear'
```

### Backoff Strategies

**Exponential** (default): `delay * 2^(attempt-1)` + jitter

```
Attempt 1: 1000ms ± 250ms
Attempt 2: 2000ms ± 500ms
Attempt 3: 4000ms ± 1000ms
```

**Linear**: `delay * attempt` + jitter

```
Attempt 1: 1000ms ± 250ms
Attempt 2: 2000ms ± 500ms
Attempt 3: 3000ms ± 750ms
```

Jitter is ±25% to prevent thundering herd.

## Run-Level Retry

### Retry from Failure

Retries a failed run from the first failed node, restoring context:

```php
use Aftandilmmd\WorkflowAutomation\Facades\Workflow;

$newRun = Workflow::retryFromFailure($failedRun);
```

Or via API:

```http
POST /workflow-engine/runs/{id}/retry
```

### Retry Specific Node

Retries a specific failed node:

```php
$newRun = Workflow::retryNode($failedRun, $nodeId);
```

Or via API:

```http
POST /workflow-engine/runs/{id}/retry-node
{"node_id": 5}
```

### Replay

Re-runs the entire workflow with the original payload:

```php
$newRun = Workflow::replay($failedRun);
```

## Error Events

Listen for errors in your application:

```php
use Aftandilmmd\WorkflowAutomation\Events\NodeFailed;
use Aftandilmmd\WorkflowAutomation\Events\WorkflowFailed;

// In EventServiceProvider
protected $listen = [
    NodeFailed::class => [
        LogNodeFailure::class,
    ],
    WorkflowFailed::class => [
        NotifyAdminOfWorkflowFailure::class,
    ],
];
```

Event payloads:

```php
// NodeFailed
$event->nodeRun;    // WorkflowNodeRun model
$event->exception;  // The Throwable

// WorkflowFailed
$event->run;        // WorkflowRun model
$event->exception;  // The Throwable
```

## Best Practices

1. **Always connect error ports** for HTTP requests and external API calls
2. **Use the error handler** to classify and route errors instead of letting them fail the run
3. **Set reasonable retry counts** (2-3) for transient failures like timeouts
4. **Use exponential backoff** for rate-limited APIs
5. **Listen to events** for centralized error logging and alerting
