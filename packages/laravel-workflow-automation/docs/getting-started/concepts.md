<div v-pre>

# Core Concepts

Understanding the building blocks of the workflow engine.

## Workflows Are Graphs

A workflow is a **directed acyclic graph (DAG)** of nodes connected by edges. Data flows from a single trigger node through action, condition, and control nodes until there are no more edges to follow.

```
[Trigger] → [Condition] → true  → [Action A] → [Action B]
                        → false → [Action C]
```

## Core Components

### Workflow

The top-level container. Has a name, active/inactive status, and settings. A workflow must have exactly **one trigger node**.

```php
$workflow = Workflow::create([
    'name'        => 'Order Processing',
    'description' => 'Handles incoming orders',
    'is_active'   => true,
    'run_async'   => true,
]);
```

### Node

A single step in the workflow. Every node has:

| Property | Description |
|----------|-------------|
| **Type** | `trigger`, `action`, `condition`, `transformer`, `control`, `utility`, or `code` |
| **Node key** | Identifies the implementation (e.g. `send_mail`, `if_condition`) |
| **Config** | JSON settings specific to that node |
| **Input ports** | Named entry points where data arrives |
| **Output ports** | Named exit points where data leaves |

### Edge

A connection from one node's output port to another node's input port.

```php
$sourceNode->connect(
    $targetNode,
    sourcePort: 'main',   // default
    targetPort: 'main',   // default
);
```

Some nodes have multiple output ports. For example, the IF Condition node has `true` and `false` ports:

```
                    ┌─ true ──▶ [Send VIP Email]
[IF Condition] ─────┤
                    └─ false ─▶ [Send Standard Email]
```

### Run

A single execution of a workflow. Tracks status, payload, per-node logs, and timing.

### Node Run

A record of one node's execution within a run. Stores input, output, status, duration, and error messages.

## Node Categories

| Category | Count | Purpose | Nodes |
|----------|-------|---------|-------|
| **Trigger** | 5 | Start the workflow | Manual, Model Event, Event, Webhook, Schedule |
| **Action** | 7 | Perform side effects | Send Mail, HTTP Request, Update Model, Dispatch Job, Send Notification, AI, Run Command |
| **Condition** | 2 | Branch the flow | IF Condition, Switch |
| **Transformer** | 2 | Reshape data | Set Fields, Parse Data |
| **Control** | 6 | Control execution | Loop, Merge, Delay, Sub Workflow, Error Handler, Wait/Resume |
| **Utility** | 3 | Process data | Filter, Aggregate, Code |

## Data Model: Items Array

Data flows through the workflow as an **array of items**. Each item is an associative array representing one unit of data.

```php
// Single item
$workflow->start([
    ['name' => 'Alice', 'email' => 'alice@example.com'],
]);

// Multiple items — each flows through every node
$workflow->start([
    ['name' => 'Alice', 'email' => 'alice@example.com'],
    ['name' => 'Bob',   'email' => 'bob@example.com'],
]);
```

Nodes transform items as they pass through:
- **Filter** reduces the items array
- **Loop** expands it (one array → many items)
- **Conditions** split items into different output ports
- **Set Fields** adds or modifies fields on each item

## Ports

Nodes communicate through **ports**:

```
Input Ports          Node           Output Ports
                ┌─────────────┐
  main ────────▶│  Send Mail  │────────▶ main
                │             │────────▶ error
                └─────────────┘
```

| Node Type | Input Ports | Output Ports |
|-----------|-------------|--------------|
| Triggers | *(none)* | `main` |
| Actions | `main` | `main`, `error` |
| IF Condition | `main` | `true`, `false` |
| Switch | `main` | `case_*`, `default` |
| Loop | `main` | `loop_item`, `loop_done` |
| Merge | `main_1` .. `main_4` | `main` |
| Error Handler | `main` | `notify`, `retry`, `ignore`, `stop` |
| Wait/Resume | `main` | `resume`, `timeout` |

## Expressions

The `{{ expression }}` syntax lets you reference dynamic data in node configs:

```php
$workflow->addNode('Greet', 'send_mail', [
    'to'      => '{{ item.email }}',
    'subject' => 'Order #{{ item.order_id }} confirmed',
    'body'    => '{{ item.quantity > 10 ? "Bulk" : "Standard" }} order',
]);
```

Available variables:

| Variable | Description |
|----------|-------------|
| `item` | The current item being processed |
| `item.field` | A specific field on the current item |
| `trigger` | The trigger node's output items |
| `node.{id}.main` | Output from a specific node and port |
| `nodes` | All node outputs |
| `payload` | The initial workflow payload |

See the [Expression Engine](/expressions/) reference for all operators and functions.

## Run Statuses

| Status | Meaning |
|--------|---------|
| `Pending` | Created but not yet started |
| `Running` | Currently executing |
| `Completed` | All nodes finished successfully |
| `Failed` | A node threw an unhandled error |
| `Cancelled` | Manually cancelled |
| `Waiting` | Paused by a Delay or Wait/Resume node |

## Execution Flow

The engine uses **Breadth-First Search (BFS)** to traverse the graph:

```
1. Execute trigger node
2. Enqueue downstream nodes
3. While queue is not empty:
   a. Dequeue next node
   b. Resolve {{ expressions }} in config
   c. Execute node with input items
   d. Store output in execution context
   e. Enqueue downstream nodes based on output ports
4. Mark run as completed
```

Key behaviors:
- **Multi-input nodes** (Merge) wait until all connected input ports have data
- **Condition nodes** only enqueue ports that have items
- **Delay/WaitResume** nodes pause execution (status → `Waiting`)
- **Error ports** catch node failures without stopping the workflow

## Synchronous vs Async

```php
// Sync — blocks until complete, returns WorkflowRun
$run = $workflow->start($payload);

// Async — dispatches to queue, returns immediately
$workflow->startAsync($payload);
```

Configure globally in `config/workflow-automation.php`:

```php
'async' => env('WORKFLOW_ASYNC', true),
'queue' => env('WORKFLOW_QUEUE', 'default'),
```

## Fluent API vs Facade

::: code-group
```php [Fluent API]
$workflow = Workflow::create(['name' => 'My Flow']);
$trigger = $workflow->addNode('Start', 'manual');
$email = $workflow->addNode('Send', 'send_mail', [...]);
$trigger->connect($email);
$workflow->activate();
$workflow->start($payload);
```

```php [Facade]
use Aftandilmmd\WorkflowAutomation\Facades\Workflow;

$wf = Workflow::create(['name' => 'My Flow']);
$trigger = Workflow::addNode($wf, 'manual', name: 'Start');
$email = Workflow::addNode($wf, 'send_mail', [...], name: 'Send');
Workflow::connect($trigger, $email);
Workflow::activate($wf);
Workflow::run($wf, $payload);
```
:::

Both are fully supported. Examples in this documentation use the fluent API.

## Next Steps

- [Triggers](/triggers/manual) — learn how workflows start
- [Action Nodes](/nodes/send-mail) — see what workflows can do
- [Expression Engine](/expressions/) — master the template syntax


</div>
