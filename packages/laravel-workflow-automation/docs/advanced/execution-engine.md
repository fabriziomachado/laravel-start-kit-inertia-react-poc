<div v-pre>

# Execution Engine

This page explains how the workflow engine executes graphs internally.

## Architecture

The execution engine consists of four main components:

| Component | Class | Role |
|-----------|-------|------|
| Graph Executor | `GraphExecutor` | Orchestrates BFS execution |
| Node Runner | `NodeRunner` | Runs individual nodes with retry |
| Expression Evaluator | `ExpressionEvaluator` | Resolves `{{ }}` in config |
| Graph Validator | `GraphValidator` | Validates graph structure |

## BFS Execution

The engine uses **breadth-first search** to process nodes:

1. Find the trigger node and execute it
2. Seed a queue with the trigger's downstream nodes
3. While the queue is not empty:
   - Dequeue the next task (node ID + items)
   - If the node has multiple input ports, accumulate items until all connected inputs have data
   - Resolve `{{ expressions }}` in the node's config
   - Execute the node
   - Enqueue downstream nodes based on the output ports

```
Queue: [A, B]

Process A → outputs to C via 'main' port
Queue: [B, C]

Process B → outputs to C via 'true' port
Queue: [C]

Process C (merge node, waits for both inputs) → outputs to D
Queue: [D]

Process D → no downstream nodes
Queue: [] → done
```

## Edge Map

Edges are pre-compiled into a lookup map for fast traversal:

```
"{sourceNodeId}_{sourcePort}" → [targetNodeId, ...]
```

Example:
```
"1_main"  → [2]      // Node 1's main port → Node 2
"2_true"  → [3]      // Node 2's true port → Node 3
"2_false" → [4]      // Node 2's false port → Node 4
```

## Expression Resolution

Before executing each node, the engine resolves all `{{ expression }}` blocks in the config:

```php
$variables = $context->toVariables($nodeNameMap, $currentItem);
$resolvedConfig = $expressionEvaluator->resolveConfig($node->config, $variables);
```

The variable context includes:
- `item` — the current item being processed
- `payload` — the initial workflow payload
- `trigger` — the trigger node's output
- `node.{id}.{port}` — output from any previously executed node

## Multi-Input Nodes

Nodes with multiple input ports (like `merge`) require special handling:

1. When items arrive at a multi-input node, they're accumulated in a `$pendingInputs` buffer
2. The engine checks which ports have incoming edges
3. Only when **all** connected input ports have data does the node execute
4. Items from all ports are merged into a single items array

```php
// Simplified logic
if (count($inputPorts) > 1) {
    $pendingInputs[$nodeId][$fromPort] = $items;

    foreach ($incomingPorts as $port) {
        if (!isset($pendingInputs[$nodeId][$port])) {
            continue 2; // Skip — not all inputs received
        }
    }

    $allItems = array_merge(...$pendingInputs[$nodeId]);
}
```

## Context Persistence

The `ExecutionContext` stores node outputs during execution. When a workflow pauses (via `delay` or `wait_resume`), the context is serialized to the `workflow_runs.context` JSON column. When resuming, the context is restored:

```php
// Pause
$run->update(['context' => $context->getAllOutputs()]);

// Resume
$context->restoreOutputs($run->context);
```

This allows the engine to know which nodes have already executed and what data they produced.

## Run Lifecycle

```
pending → running → completed
                  → failed
                  → waiting → (resume) → running → completed
                                                  → failed
                  → cancelled
```

| Status | Meaning |
|--------|---------|
| `pending` | Run created, not yet started |
| `running` | Actively executing nodes |
| `waiting` | Paused by `delay` or `wait_resume` |
| `completed` | All nodes finished successfully |
| `failed` | A node threw an unhandled exception |
| `cancelled` | Manually cancelled via API |

## Node Run Lifecycle

```
pending → running → completed
                  → failed
                  → skipped
```

Each node execution creates a `WorkflowNodeRun` record with:
- Input items
- Output items (by port)
- Duration in milliseconds
- Error message (if failed)
- Number of attempts

## Synchronous vs Async

```php
// Synchronous — blocks until complete
$run = $workflow->start($payload);

// Async — dispatches to queue, returns immediately
$workflow->startAsync($payload);
```

Async execution dispatches `ExecuteWorkflowJob` to the configured queue. The job calls `GraphExecutor::execute()` in the worker process.

```php
// config/workflow-automation.php
'async' => true,
'queue' => 'default',
```

## Retry Logic

The `NodeRunner` handles retries with configurable backoff:

```php
// Per-node config
'retry_count' => 3,
'retry_delay_ms' => 1000,

// Or global config
'default_retry_count' => 0,
'default_retry_delay_ms' => 1000,
'retry_backoff' => 'exponential', // or 'linear'
```

Backoff calculation:
- **Exponential**: `baseDelay * 2^(attempt-1)` + jitter (±25%)
- **Linear**: `baseDelay * attempt` + jitter (±25%)

If retries are exhausted and the node has an `error` output port, the error is routed there instead of failing the entire run.

## Resume Flow

When a `wait_resume` or `delay` node pauses execution:

1. The run status is set to `waiting`
2. The execution context is saved
3. For `wait_resume`: a resume token is generated and stored in the node run output
4. For `delay`: a `ResumeWorkflowJob` is scheduled with the appropriate delay

When resumed:

1. The context is restored from the saved state
2. Execution continues from the resume node's output port
3. The BFS queue is seeded with the resume node's downstream nodes


</div>
