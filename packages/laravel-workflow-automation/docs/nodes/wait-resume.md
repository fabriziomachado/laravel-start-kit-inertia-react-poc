# Wait / Resume

Pauses a workflow and waits for an external signal before continuing. Enables human-in-the-loop approvals and third-party callbacks.

**Node key:** `wait_resume` · **Type:** Control

## Config

| Key | Type | Required | Expression | Description |
| --- | --- | --- | --- | --- |
| `timeout_seconds` | integer | No | No | Timeout in seconds (0 or omitted = no timeout) |

## Ports

| Direction | Port | Description |
| --- | --- | --- |
| Input | `main` | Items to pause on |
| Output | `resume` | Items forwarded when resumed externally |
| Output | `timeout` | Items forwarded when timeout elapses |

## Behavior

1. Generates a unique `resume_token` (UUID)
2. Sets run status to `waiting`
3. If `timeout_seconds` > 0, schedules a timeout job
4. Workflow pauses and exits

**Resume:** External system calls the resume API → workflow continues from `resume` port, payload merged into items.

**Timeout:** If not resumed in time → workflow continues from `timeout` port.

Whichever fires first wins; the other is ignored.

## Resuming via PHP

```php
use Aftandilmmd\WorkflowAutomation\Facades\Workflow;

Workflow::resume($runId, $resumeToken, ['approved' => true]);
```

## Resuming via REST API

```http
POST /workflow-engine/runs/{id}/resume
Content-Type: application/json

{
    "resume_token": "550e8400-e29b-41d4-a716-446655440000",
    "payload": {"approved": true, "comment": "Looks good"}
}
```

## Example: Purchase Approval

```php
$notify = $workflow->addNode('Request Approval', 'send_mail', [
    'to'      => '{{ item.manager_email }}',
    'subject' => 'PO #{{ item.id }} needs approval (${{ item.total }})',
    'body'    => 'Please review and approve.',
]);

$wait = $workflow->addNode('Await Approval', 'wait_resume', [
    'timeout_seconds' => 86400, // 24 hours
]);

$approve = $workflow->addNode('Process PO', 'update_model', [
    'model'      => 'App\\Models\\PurchaseOrder',
    'find_by'    => 'id',
    'find_value' => '{{ item.id }}',
    'fields'     => ['status' => 'approved'],
]);

$escalate = $workflow->addNode('Escalate', 'send_mail', [
    'to'      => 'director@example.com',
    'subject' => 'PO #{{ item.id }} timed out',
    'body'    => 'Manager did not approve within 24 hours.',
]);

$notify->connect($wait);
$wait->connect($approve, 'resume');
$wait->connect($escalate, 'timeout');
```

## Finding the Resume Token

```php
$waitNodeRun = $run->nodeRuns()
    ->whereHas('node', fn ($q) => $q->where('node_key', 'wait_resume'))
    ->first();

$token = $waitNodeRun->output['resume'][0]['resume_token'];
```

## Tips

- The resume token is in the node run's output data — query it to build approval links
- Resume payload is merged into items, so approvers can pass context (`approved`, `comments`)
- Use `timeout_seconds` for SLA enforcement — route timed-out items to escalation paths
