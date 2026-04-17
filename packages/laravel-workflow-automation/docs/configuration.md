# Configuration

Publish the config file:

```bash
php artisan vendor:publish --tag=workflow-automation-config
```

## Full Reference

```php
// config/workflow-automation.php

return [
    // Database table names
    'tables' => [
        'workflows'    => 'workflows',
        'nodes'        => 'workflow_nodes',
        'edges'        => 'workflow_edges',
        'runs'         => 'workflow_runs',
        'node_runs'    => 'workflow_node_runs',
        'credentials'  => 'workflow_credentials',
    ],

    // Override default model classes with your own
    'models' => [
        'workflow'    => Aftandilmmd\WorkflowAutomation\Models\Workflow::class,
        'node'        => Aftandilmmd\WorkflowAutomation\Models\WorkflowNode::class,
        'edge'        => Aftandilmmd\WorkflowAutomation\Models\WorkflowEdge::class,
        'run'         => Aftandilmmd\WorkflowAutomation\Models\WorkflowRun::class,
        'node_run'    => Aftandilmmd\WorkflowAutomation\Models\WorkflowNodeRun::class,
        'credential'  => Aftandilmmd\WorkflowAutomation\Models\WorkflowCredential::class,
    ],

    // Queue configuration
    'async' => env('WORKFLOW_ASYNC', true),
    'queue' => env('WORKFLOW_QUEUE', 'default'),

    // HTTP routes
    'routes'          => true,
    'api_routes'      => env('WORKFLOW_API_ROUTES', true),
    'webhook_routes'  => env('WORKFLOW_WEBHOOK_ROUTES', true),
    'prefix'          => env('WORKFLOW_PREFIX', 'workflow-engine'),
    'middleware'      => ['api'],

    // Webhook URL prefix
    'webhook_prefix' => 'workflow-webhook',

    // Execution limits
    'max_execution_time'     => env('WORKFLOW_MAX_EXECUTION', 300),
    'default_retry_count'    => 0,
    'default_retry_delay_ms' => 1000,
    'retry_backoff'          => 'exponential', // linear | exponential

    // Expression engine mode
    'expression_mode' => 'safe', // safe | strict

    // Auto-discovery paths for custom nodes
    'node_discovery' => [
        'app_paths' => [
            // app_path('Workflow/Nodes'),
        ],
    ],

    // Run Command node security
    'run_command' => [
        'allowed_commands' => [],
        'shell_enabled' => env('WORKFLOW_SHELL_ENABLED', true),
    ],

    // AI node defaults (requires laravel/ai)
    'ai' => [
        'default_provider' => env('WORKFLOW_AI_PROVIDER'),
        'default_model'    => env('WORKFLOW_AI_MODEL'),
        'max_tokens'       => env('WORKFLOW_AI_MAX_TOKENS', 4096),
    ],

    // Workflow chaining
    'chaining' => [
        'max_depth' => env('WORKFLOW_CHAIN_MAX_DEPTH', 10),
    ],

    // Log retention (days, 0 = disabled)
    'log_retention_days' => env('WORKFLOW_LOG_RETENTION', 30),
];
```

## Option Details

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `tables.*` | string | See above | Customize database table names (includes `credentials`) |
| `models.*` | class-string | Package models | Override with your own Eloquent models (includes `credential`) |
| `async` | bool | `true` | Whether workflows run on the queue by default |
| `queue` | string | `'default'` | Queue name for async workflows |
| `routes` | bool | `true` | Master switch — set `false` to disable all package routes |
| `api_routes` | bool | `true` | Enable/disable the CRUD + execution API endpoints |
| `webhook_routes` | bool | `true` | Enable/disable the webhook trigger endpoints |
| `prefix` | string | `'workflow-engine'` | API route prefix |
| `middleware` | array | `['api']` | Middleware applied to API routes |
| `webhook_prefix` | string | `'workflow-webhook'` | URL prefix for webhook endpoints |
| `max_execution_time` | int | `300` | Max execution time in seconds |
| `default_retry_count` | int | `0` | Default retry count for all nodes |
| `default_retry_delay_ms` | int | `1000` | Default retry delay in milliseconds |
| `retry_backoff` | string | `'exponential'` | Backoff strategy: `linear` or `exponential` |
| `expression_mode` | string | `'safe'` | `safe` enables all functions, `strict` disables all |
| `node_discovery.app_paths` | array | `[]` | Directories to scan for custom nodes |
| `run_command.allowed_commands` | array | `[]` | Whitelist of allowed commands (empty = all allowed) |
| `run_command.shell_enabled` | bool | `true` | Set `false` to disable shell commands |
| `ai.default_provider` | string | `null` | Default AI provider (e.g. `openai`, `anthropic`) |
| `ai.default_model` | string | `null` | Default AI model (e.g. `gpt-4o`) |
| `ai.max_tokens` | int | `4096` | Default max tokens for AI responses |
| `chaining.max_depth` | int | `10` | Maximum chain depth to prevent infinite loops (0 = disabled) |
| `log_retention_days` | int | `30` | Prune runs older than this (0 = disabled) |

## Environment Variables

| Variable | Config Key | Default |
|----------|-----------|---------|
| `WORKFLOW_ASYNC` | `async` | `true` |
| `WORKFLOW_QUEUE` | `queue` | `'default'` |
| `WORKFLOW_PREFIX` | `prefix` | `'workflow-engine'` |
| `WORKFLOW_API_ROUTES` | `api_routes` | `true` |
| `WORKFLOW_WEBHOOK_ROUTES` | `webhook_routes` | `true` |
| `WORKFLOW_MAX_EXECUTION` | `max_execution_time` | `300` |
| `WORKFLOW_SHELL_ENABLED` | `run_command.shell_enabled` | `true` |
| `WORKFLOW_AI_PROVIDER` | `ai.default_provider` | `null` |
| `WORKFLOW_AI_MODEL` | `ai.default_model` | `null` |
| `WORKFLOW_AI_MAX_TOKENS` | `ai.max_tokens` | `4096` |
| `WORKFLOW_CHAIN_MAX_DEPTH` | `chaining.max_depth` | `10` |
| `WORKFLOW_LOG_RETENTION` | `log_retention_days` | `30` |

## Extending Models

To add custom behavior, extend the package models and update the config:

```php
// app/Models/CustomWorkflow.php
namespace App\Models;

use Aftandilmmd\WorkflowAutomation\Models\Workflow;

class CustomWorkflow extends Workflow
{
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
```

```php
// config/workflow-automation.php
'models' => [
    'workflow' => App\Models\CustomWorkflow::class,
],
```

## Route Control

### Disable API only (keep webhooks)

If workflows are managed via PHP only and you don't need the REST API:

```php
'api_routes'     => false,
'webhook_routes' => true,
```

Or via `.env`:

```ini
WORKFLOW_API_ROUTES=false
```

### Disable webhooks only (keep API)

If you don't use webhook triggers:

```php
'api_routes'     => true,
'webhook_routes' => false,
```

### Disable all routes

For full control over routing:

```php
'routes' => false,
```

Then register your own routes pointing to the package controllers:

```php
use Aftandilmmd\WorkflowAutomation\Http\Controllers\WorkflowController;

Route::prefix('api/v2/workflows')
    ->middleware(['api', 'auth:sanctum'])
    ->group(function () {
        Route::apiResource('/', WorkflowController::class);
        // ... other routes
    });
```
