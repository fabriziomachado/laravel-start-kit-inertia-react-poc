# Error Handler

Routes error items to different ports based on regex pattern matching against the error message.

**Node key:** `error_handler` · **Type:** Control

## Config

| Key | Type | Required | Expression | Description |
| --- | --- | --- | --- | --- |
| `rules` | array_of_objects | Yes | No | Routing rules (see below) |
| `default_route` | select | No | No | Default port when no rule matches (default: `notify`) |

### Rule Definition

| Key | Type | Description |
| --- | --- | --- |
| `match` | string | Regex pattern to test against the error message |
| `route` | select | Destination port: `notify`, `retry`, `ignore`, or `stop` |

## Ports

| Direction | Port | Description |
| --- | --- | --- |
| Input | `main` | Error items to evaluate |
| Output | `notify` | Errors that should trigger a notification |
| Output | `retry` | Errors that should be retried |
| Output | `ignore` | Errors that can be safely ignored |
| Output | `stop` | Errors that should halt processing |

## Behavior

Rules are evaluated in order — first match wins:

```text
Error: "Connection timed out after 30s"

  Rule 1: /timeout|timed out/ → retry  ← MATCH
  Rule 2: /validation/        → notify
  Default: stop

  → Routed to 'retry' port
```

Regex patterns are tested case-insensitively.

## Example

```php
$errorHandler = $workflow->addNode('Handle Errors', 'error_handler', [
    'rules' => [
        ['match' => 'timeout|timed out',    'route' => 'retry'],
        ['match' => 'validation|invalid',   'route' => 'notify'],
        ['match' => 'rate.?limit|throttle', 'route' => 'retry'],
        ['match' => '404|not.found',        'route' => 'ignore'],
    ],
    'default_route' => 'stop',
]);

// Connect error ports from other nodes to this handler
$httpNode->connect($errorHandler, 'error');

// Connect handler outputs to appropriate actions
$errorHandler->connect($retryNode, 'retry');
$errorHandler->connect($alertEmail, 'notify');
```

## Tips

- Connect other nodes' `error` output ports to centralize error handling
- Leave `ignore` and `stop` ports unconnected to discard those items
- Regex patterns are case-insensitive: `timeout` matches "Timeout", "TIMEOUT", etc.
