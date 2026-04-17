# Installation

## Requirements

- PHP 8.3+
- Laravel 10, 11, or 12

## Install via Composer

```bash
composer require aftandilmmd/laravel-workflow-automation
```

The package auto-discovers its service provider. No manual registration needed.

## Publish Configuration

```bash
php artisan vendor:publish --provider="Aftandilmmd\WorkflowAutomation\WorkflowAutomationServiceProvider"
```

This creates `config/workflow-automation.php` where you can customize table names, queue settings, routes, and more.

## Run Migrations

```bash
php artisan migrate
```

This creates nine tables:

| Table | Purpose |
|-------|---------|
| `workflows` | Workflow definitions |
| `workflow_nodes` | Nodes within each workflow |
| `workflow_edges` | Connections between nodes |
| `workflow_runs` | Execution records |
| `workflow_node_runs` | Per-node execution logs |
| `workflow_credentials` | Encrypted credential storage |
| `workflow_tags` | Tags for categorizing workflows |
| `workflow_tag_pivot` | Many-to-many workflow ↔ tag |
| `workflow_folders` | Hierarchical folder organization |

::: tip Customizing Table Names
You can change table names in the config **before** running migrations:

```php
'tables' => [
    'workflows'  => 'my_workflows',
    'nodes'      => 'my_workflow_nodes',
    // ...
],
```
:::

## Publish Migrations (Optional)

If you need to modify migration files:

```bash
php artisan vendor:publish --tag=workflow-automation-migrations
```

## Verify Installation

Check that the package routes are registered:

```bash
php artisan route:list --path=workflow-engine
```

You should see endpoints for workflows, nodes, edges, runs, and the registry.

## Visual Editor

The package includes a built-in visual workflow editor at `/workflow-editor`:

```txt
http://myapp.test/workflow-editor
```

No extra setup needed — it works out of the box. See the [Workflow UI Editor](/ui-editor) docs for details.

## Next Steps

Head to [Quick Start](/getting-started/quick-start) to build your first workflow in under 5 minutes.
