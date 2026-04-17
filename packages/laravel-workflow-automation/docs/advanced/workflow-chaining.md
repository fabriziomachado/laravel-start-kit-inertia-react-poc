<div v-pre>

# Workflow Chaining

Workflow chaining connects multiple workflows together so the output of one becomes the input of another. This enables modular, reusable workflow architectures.

## Two Ways to Connect Workflows

There are two distinct mechanisms for connecting workflows. They solve **different problems** and are **not interchangeable**.

| | [Sub Workflow](/nodes/sub-workflow) | [Workflow Trigger](/triggers/workflow) |
| --- | --- | --- |
| **What it is** | A control node inside a workflow | A trigger node that starts a workflow |
| **Direction** | Parent **calls** child | Child **listens to** parent |
| **Analogy** | Function call | Event listener |
| **Coupling** | Tight — parent knows child's ID | Loose — parent doesn't know who's listening |
| **Execution** | Sync or async | Always async (queue) |
| **Output access** | Direct (sync mode returns child output) | Via payload metadata (`item.data`) |
| **Parent-child tracking** | Yes (`parent_run_id`) | No (independent runs) |
| **Fan-out** | Must add one node per child | Multiple workflows can listen to same source |
| **Error handling** | `error` port in parent | Separate workflow with `trigger_on: failed` |

## When to Use Sub Workflow

Use the Sub Workflow control node when the **parent workflow needs the child's result to continue**. The parent is in control — it decides which child to call, when, and what to do with the result.

### Sub Workflow scenarios

- **Validation step**: Run a validation workflow, check the result, branch accordingly
- **Reusable logic**: Extract common node sequences into a shared workflow
- **Orchestration**: Parent coordinates multiple steps, some of which are sub-workflows

### Example: Order with Validation

```php
$orderWorkflow = Workflow::create(['name' => 'Process Order']);

$trigger = $orderWorkflow->addNode('New Order', 'manual');

// Call validation workflow and wait for result
$validate = $orderWorkflow->addNode('Validate', 'sub_workflow', [
    'workflow_id'     => $validationWorkflow->id,
    'pass_items'      => true,
    'wait_for_result' => true,
]);

// Use validation result to decide next step
$check = $orderWorkflow->addNode('Check Result', 'if_condition', [
    'field'    => 'status',
    'operator' => 'equals',
    'value'    => 'completed',
]);

$process = $orderWorkflow->addNode('Process', 'set_fields', [
    'fields' => ['status' => 'processing'],
]);

$reject = $orderWorkflow->addNode('Reject', 'send_mail', [
    'to'      => '{{ item.output.customer_email }}',
    'subject' => 'Order rejected',
    'body'    => 'Your order could not be validated.',
]);

$trigger->connect($validate);
$validate->connect($check);                           // main → check
$check->connect($process, 'true');                    // valid → process
$check->connect($reject, 'false');                    // invalid → reject
```

**Key point**: The parent workflow **waits** for the child, **reads its output**, and **branches based on the result**. This is impossible with the Workflow Trigger.

### What Sub Workflow returns

**Sync mode** (`wait_for_result: true`):

```json
{
  "sub_workflow_run_id": 42,
  "status": "completed",
  "output": { "node_3": { "main": [{"validated": true}] } }
}
```

**Sync mode on failure** (routed to `error` port):

```json
{
  "sub_workflow_run_id": 42,
  "status": "failed",
  "error_message": "Validation rule X failed"
}
```

**Async mode** (`wait_for_result: false`): Original items pass through immediately. The child runs in the background — fire and forget.

## When to Use Workflow Trigger

Use the Workflow Trigger when **workflows should be independent**. The source workflow doesn't know (or care) who's listening. The triggered workflow decides for itself when to run.

### Workflow Trigger scenarios

- **Pipeline stages**: Import → Process → Export, each managed independently
- **Fan-out**: One workflow completing triggers multiple independent follow-ups
- **Cross-team workflows**: Team A owns the order workflow, Team B owns shipping, Team C owns invoicing
- **Error handling**: A dedicated workflow catches failures from other workflows
- **Audit/logging**: Record workflow completions without modifying the source

### Example: Independent Pipeline

```php
// Team A builds the import workflow — they don't know or care about downstream
$importWf = Workflow::create(['name' => 'Data Import']);
$importTrigger = $importWf->addNode('Start', 'schedule', [
    'expression' => '0 * * * *', // hourly
]);
$fetch = $importWf->addNode('Fetch Data', 'http_request', [
    'url' => 'https://api.example.com/data',
]);
$importTrigger->connect($fetch);
$importWf->activate();

// Team B builds the processing workflow — listens to import
$processWf = Workflow::create(['name' => 'Data Processing']);
$processTrigger = $processWf->addNode('Import Done', 'workflow', [
    'source_workflow_id' => $importWf->id,
    'trigger_on'         => 'completed',
]);
$transform = $processWf->addNode('Transform', 'set_fields', [
    'fields' => ['processed_at' => '{{ now() }}'],
]);
$processTrigger->connect($transform);
$processWf->activate();

// Team C builds the export workflow — listens to processing
$exportWf = Workflow::create(['name' => 'Data Export']);
$exportTrigger = $exportWf->addNode('Processing Done', 'workflow', [
    'source_workflow_id' => $processWf->id,
    'trigger_on'         => 'completed',
]);
$export = $exportWf->addNode('Export', 'http_request', [
    'url'    => 'https://warehouse.example.com/import',
    'method' => 'POST',
    'body'   => '{{ item.data }}',
]);
$exportTrigger->connect($export);
$exportWf->activate();
```

**Key point**: Each team works on their own workflow independently. Adding, removing, or modifying a downstream workflow **never touches the upstream workflow**.

### Example: Fan-out

```php
// When the daily report workflow completes, notify 3 different channels
// Each notification workflow is independent

$slackNotify = Workflow::create(['name' => 'Slack Notification']);
$slackNotify->addNode('Report Done', 'workflow', [
    'source_workflow_id' => $reportWorkflow->id,
    'trigger_on'         => 'completed',
]);
// ... slack notification nodes

$emailNotify = Workflow::create(['name' => 'Email Digest']);
$emailNotify->addNode('Report Done', 'workflow', [
    'source_workflow_id' => $reportWorkflow->id,
    'trigger_on'         => 'completed',
]);
// ... email nodes

$dashboardUpdate = Workflow::create(['name' => 'Update Dashboard']);
$dashboardUpdate->addNode('Report Done', 'workflow', [
    'source_workflow_id' => $reportWorkflow->id,
    'trigger_on'         => 'completed',
]);
// ... dashboard update nodes
```

With Sub Workflow, the report workflow would need 3 sub_workflow nodes and would need to be modified every time a new consumer is added.

### Example: Global Error Handler

```php
// One workflow catches ALL failures across the system
$errorHandler = Workflow::create(['name' => 'Error Alert']);

$trigger = $errorHandler->addNode('Any Failure', 'workflow', [
    'source_workflow_id' => null, // null = listen to ALL workflows
    'trigger_on'         => 'failed',
]);

$alert = $errorHandler->addNode('Send Alert', 'send_notification', [
    'channel' => 'slack',
    'message' => 'Workflow #{{ item.source_workflow_id }} failed (run #{{ item.source_run_id }}): {{ item.error_message }}',
]);

$trigger->connect($alert);
$errorHandler->activate();
```

This is impossible with Sub Workflow — you'd have to add error handling to every single workflow.

## Decision Guide

Ask yourself these questions in order:

### 1. Does the parent need the child's output to continue?

**Yes** → Use **Sub Workflow** with `wait_for_result: true`

```
Parent: [Trigger] → [SubWorkflow(B)] → [Use B's result] → [Done]
```

**No** → Go to question 2.

### 2. Should the source workflow know about downstream workflows?

**Yes, it's orchestrating** → Use **Sub Workflow** (sync or async)

```
Orchestrator: [Trigger] → [SubWorkflow(A)] → [SubWorkflow(B)] → [SubWorkflow(C)]
```

**No, they should be independent** → Use **Workflow Trigger**

```
A: [Trigger] → [Work] → [Done]  (doesn't know about B)
B: [WorkflowTrigger(A)] → [Work] → [Done]  (listens independently)
```

### 3. Will multiple workflows need to react to the same source?

**Yes (fan-out)** → Use **Workflow Trigger**

```
A completes → B, C, D all start independently
```

**No (1-to-1)** → Either works. Use Sub Workflow if you need output, Workflow Trigger if you want independence.

### 4. Do you need a global error handler?

**Yes** → Use **Workflow Trigger** with `source_workflow_id: null` and `trigger_on: failed`

## Accessing Source Data in Workflow Trigger

When a Workflow Trigger fires, the payload contains:

```text
{{ item.source_workflow_id }}    → ID of the source workflow
{{ item.source_run_id }}         → Run ID of the source execution
{{ item.source_status }}         → "completed" or "failed"
{{ item.error_message }}         → Error message (null if completed)
{{ item.data }}                  → Full context/output from source workflow
```

## Safety

### Chain Depth Limit

A maximum chain depth prevents infinite loops (A → B → A → ...). Default: 10 levels.

```php
// config/workflow-automation.php
'chaining' => [
    'max_depth' => env('WORKFLOW_CHAIN_MAX_DEPTH', 10),
],
```

When the limit is reached, the chain stops and a warning is logged.

### Self-Trigger Prevention

A workflow cannot trigger itself via the Workflow Trigger. This is enforced automatically.

### Circular Chain Detection

If A triggers B and B triggers A, the chain depth counter increments with each step and will stop at `max_depth`.

## Summary

| I want to... | Use |
| --- | --- |
| Call another workflow and use its result | Sub Workflow (`wait_for_result: true`) |
| Fire-and-forget another workflow from within mine | Sub Workflow (`wait_for_result: false`) |
| React when another workflow completes | Workflow Trigger (`trigger_on: completed`) |
| React when another workflow fails | Workflow Trigger (`trigger_on: failed`) |
| Build a pipeline of independent workflows | Workflow Trigger (each stage listens to the previous) |
| Trigger multiple workflows from one source | Workflow Trigger (fan-out) |
| Catch errors from all workflows | Workflow Trigger (`source_workflow_id: null`, `trigger_on: failed`) |
| Track parent-child relationship | Sub Workflow (`parent_run_id`) |

</div>
