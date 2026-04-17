<div v-pre>

# Quick Start

Build a working workflow in under 5 minutes.

## Goal

Create a workflow that automatically sends a welcome email and notifies the admin when a new user registers.

::: info Workflows are database records
Creating a workflow is just inserting rows into the database — a workflow definition, its nodes, and edges. You only need to run this code **once**. After that, the workflow lives in the database and runs automatically based on its trigger.

You can create workflows from anywhere: an Artisan command, a seeder, a migration, a controller, Tinker, or the REST API. The code below is **setup code**, not application code — think of it like a migration that you run once, not a controller action that runs on every request.
:::

## Step 1 — Create the Workflow

```php
use App\Models\User;
use Aftandilmmd\WorkflowAutomation\Models\Workflow;

$workflow = Workflow::create(['name' => 'Welcome Email']);
```

## Step 2 — Add Nodes

```php
// Trigger — fires when a new User is created
$trigger = $workflow->addNode('User Registered', 'model_event', [
    'model'  => User::class,
    'events' => ['created'],
]);

// Action — sends a welcome email to the user
$sendMail = $workflow->addNode('Send Welcome', 'send_mail', [
    'to'      => '{{ item.email }}',
    'subject' => 'Welcome, {{ item.name }}!',
    'body'    => 'Thanks for joining us.',
]);

// Action — notifies the admin about the new signup
$notifyAdmin = $workflow->addNode('Notify Admin', 'send_notification', [
    'notification_class' => \App\Notifications\NewUserSignup::class,
    'notifiable_class'   => User::class,
    'notifiable_id'      => '{{ item.id }}',
]);
```

The `{{ item.field }}` syntax is the [Expression Engine](/expressions/) — it resolves values from the current data item at runtime.

## Step 3 — Connect Nodes

```php
$trigger->connect($sendMail);
$sendMail->connect($notifyAdmin);
```

Each `connect()` call creates an edge from one node's `main` output port to the next node's `main` input port.

## Step 4 — Activate

```php
$workflow->activate();
// That's it — every new user gets a welcome email
// and the admin is notified automatically.
```

Once activated, the workflow fires automatically whenever a `User` model is created. No manual trigger needed.

## Step 5 — Check the Result

```php
// After a user registers, inspect the run:
$run = $workflow->runs()->latest()->first();

echo $run->status->value; // "completed"

foreach ($run->nodeRuns as $nodeRun) {
    echo "{$nodeRun->node->name}: {$nodeRun->status->value}";
    echo " ({$nodeRun->duration_ms}ms)\n";
}
```

## What Happened

```
┌─────────────────┐      ┌───────────────┐      ┌───────────────┐
│ User Registered │─────▶│  Send Welcome │─────▶│ Notify Admin  │
│ (model_event)   │ main │  (send_mail)  │ main │(notification) │
└─────────────────┘      └───────────────┘      └───────────────┘
```

1. A new `User` was created — the **Model Event** trigger fired
2. Items flowed through the `main` port to **Send Welcome**
3. The expression engine resolved `{{ item.email }}` to the user's email
4. Laravel's Mail facade sent the welcome email
5. Items continued to **Notify Admin** — the admin received a notification
6. The run completed

## Using the Facade

```php
use Aftandilmmd\WorkflowAutomation\Facades\Workflow;

$wf = Workflow::create(['name' => 'Welcome Email']);

$trigger  = Workflow::addNode($wf, 'manual', name: 'Start');
$sendMail = Workflow::addNode($wf, 'send_mail', [
    'to'      => '{{ item.email }}',
    'subject' => 'Welcome, {{ item.name }}!',
    'body'    => 'Hi {{ item.name }}, thanks for joining!',
], name: 'Send Welcome');

Workflow::connect($trigger, $sendMail);
Workflow::activate($wf);
Workflow::run($wf, [['name' => 'Alice', 'email' => 'alice@example.com']]);
```

## Using the REST API

```bash
# Create workflow
curl -X POST /workflow-engine/workflows \
  -H "Content-Type: application/json" \
  -d '{"name": "Welcome Email"}'

# Add trigger node
curl -X POST /workflow-engine/workflows/1/nodes \
  -H "Content-Type: application/json" \
  -d '{"node_key": "manual", "name": "Start"}'

# Add action node
curl -X POST /workflow-engine/workflows/1/nodes \
  -H "Content-Type: application/json" \
  -d '{
    "node_key": "send_mail",
    "name": "Send Welcome",
    "config": {
      "to": "{{ item.email }}",
      "subject": "Welcome!",
      "body": "Hi {{ item.name }}!"
    }
  }'

# Connect nodes
curl -X POST /workflow-engine/workflows/1/edges \
  -H "Content-Type: application/json" \
  -d '{"source_node_id": 1, "target_node_id": 2}'

# Activate & run
curl -X POST /workflow-engine/workflows/1/activate
curl -X POST /workflow-engine/workflows/1/run \
  -H "Content-Type: application/json" \
  -d '{"payload": [{"name": "Alice", "email": "alice@example.com"}]}'
```

## Next Steps

- [Concepts](/getting-started/concepts) — understand workflows, nodes, edges, items, and execution
- [Triggers](/triggers/manual) — learn about the 4 trigger types
- [Nodes](/nodes/send-mail) — explore all 25 built-in nodes
- [Examples](/examples/ecommerce-order) — see real-world workflow patterns


</div>
