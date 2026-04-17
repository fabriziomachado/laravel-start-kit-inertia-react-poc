# Laravel Workflow Automation

> [!WARNING]
> This package is under active development and is not yet recommended for production use. APIs, database schemas, and features may change.

> English | **[Türkçe](README.tr.md)**

Define multi-step business logic as visual, configurable graphs — then let Laravel execute them. Instead of scattering if/else chains, queue jobs, and event listeners across your codebase, you describe the entire flow once: trigger, conditions, actions, loops, delays. The engine handles execution, retries, logging, and human-in-the-loop pauses. Think N8N, but as a Laravel package you own and extend.

**[Full Documentation](https://laravel-workflow.pilyus.com)**

![Workflow Editor](screenshots/ai-workflow.png)

## Installation

```bash
composer require aftandilmmd/laravel-workflow-automation
php artisan vendor:publish --tag=workflow-automation-config --tag=workflow-automation-migrations
php artisan migrate
```

## Quick Start

When a user registers, send a welcome email:

```php
use Aftandilmmd\WorkflowAutomation\Models\Workflow;

$workflow = Workflow::create(['name' => 'Welcome Email']);

$trigger = $workflow->addNode('User Created', 'model_event', [
    'model'  => 'App\\Models\\User',
    'events' => ['created'],
]);

$email = $workflow->addNode('Send Welcome', 'send_mail', [
    'to'      => '{{ item.email }}',
    'subject' => 'Welcome, {{ item.name }}!',
    'body'    => 'Thanks for signing up.',
]);

$trigger->connect($email);
$workflow->activate();
```

Every `User::create()` call now triggers the workflow automatically.

## Features

**Visual Editor** — Drag-and-drop workflow builder with React Flow canvas. Add nodes, connect ports, configure forms, execute and monitor — all from `/workflow-editor`.

**26 Built-in Nodes** — Triggers, actions, conditions, loops, delays, AI, and more. Connect them like building blocks to handle common automation scenarios without writing code.

**Expression Engine** — Use `{{ item.email }}`, arithmetic, ternary, and 30+ built-in functions in any config field. Custom recursive descent parser — no `eval()`.

**5 Trigger Types** — Start workflows manually, on Eloquent model events, Laravel events, incoming webhooks, or cron schedules.

**Human-in-the-Loop** — Pause a running workflow and wait for external approval. Resume via code or REST API with arbitrary payload data.

**Retry & Replay** — Re-run failed workflows from the exact point of failure, replay completed runs with original payload, or retry individual nodes.

**Custom Nodes** — One PHP class with `#[AsWorkflowNode]` attribute. Define input/output ports, config schema, and execution logic — the engine handles the rest.

**Plugin System** — Bundle custom nodes, middleware, and event listeners into reusable plugins. Share across projects or publish as Composer packages.

**Full REST API** — Create, edit, run, and monitor workflows from any frontend, dashboard, or AI agent. Complete CRUD, execution, and registry endpoints.

**Full Observability** — Every run is recorded with per-node input/output, duration, and errors. Trace failures, debug responses, replay any run.

## Testing

```bash
composer test
```

## License

MIT
