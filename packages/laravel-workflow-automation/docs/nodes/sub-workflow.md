<div v-pre>

# Sub Workflow

Triggers another workflow from within the current one. Enables reusable, modular workflow composition with tight parent-child coupling.

**Node key:** `sub_workflow` · **Type:** Control

## Config

| Key | Type | Required | Expression | Description |
| --- | --- | --- | --- | --- |
| `workflow_id` | workflow_select | Yes | No | ID of the sub-workflow to trigger |
| `pass_items` | boolean | No | No | Pass current items as the sub-workflow's payload |
| `wait_for_result` | boolean | No | No | Execute synchronously and wait for completion |

## Ports

| Direction | Port | Description |
| --- | --- | --- |
| Input | `main` | Items to process |
| Output | `main` | Items after sub-workflow execution |
| Output | `error` | Error details when the sub-workflow fails (sync mode only) |

## Behavior

| Mode | What Happens |
| --- | --- |
| **Async** (default) | Sub-workflow is dispatched to the queue; current items forwarded immediately |
| **Sync** (`wait_for_result: true`) | Sub-workflow executes inline; result returned to `main` port |

Each invocation creates its own `WorkflowRun` record with `parent_run_id` set to the current run (sync mode).

## Output

### Sync Mode (success)

```json
{
  "sub_workflow_run_id": 42,
  "status": "completed",
  "output": { /* child workflow's full context */ }
}
```

### Sync Mode (failure)

Routed to the `error` port:

```json
{
  "sub_workflow_run_id": 42,
  "status": "failed",
  "error_message": "Node XYZ failed: ..."
}
```

### Async Mode

Original items are forwarded immediately (fire-and-forget).

## Example

```php
$sub = $workflow->addNode('Validate Order', 'sub_workflow', [
    'workflow_id'     => $validationWorkflow->id,
    'pass_items'      => true,
    'wait_for_result' => true,
]);

// Access child output in downstream nodes:
// {{ nodes['Validate Order'].sub_workflow_run_id }}
// {{ nodes['Validate Order'].output }}
```

## Parent-Child Tracking

In sync mode, the child `WorkflowRun` has a `parent_run_id` linking back to the parent run. This enables:

```php
$parentRun = $childRun->parentRun;  // Navigate up
$childRuns = $parentRun->childRuns; // Navigate down
```

## Sub Workflow vs Workflow Trigger

| | Sub Workflow | [Workflow Trigger](/triggers/workflow) |
| --- | --- | --- |
| **Coupling** | Tight (parent-child) | Loose (event-driven) |
| **Execution** | Sync or async | Always async |
| **Output access** | Direct (sync mode) | Via payload metadata |
| **Use case** | Reusable logic within a workflow | Independent workflow pipelines |

For loose coupling and event-driven chaining, see [Workflow Chaining](/advanced/workflow-chaining).

## Advanced: Workflow Chaining

The Sub Workflow node is one of two mechanisms for connecting workflows together. For a comprehensive comparison of both approaches (Sub Workflow vs Workflow Trigger), decision guides, and advanced patterns, see the **[Workflow Chaining](/advanced/workflow-chaining)** guide.

## Tips

- Sub-workflows have independent run records for separate tracking and debugging
- Use `wait_for_result: true` only for short-running sub-workflows
- In sync mode, failed sub-workflows route to the `error` port with details
- In async mode, the parent continues immediately without waiting

</div>
