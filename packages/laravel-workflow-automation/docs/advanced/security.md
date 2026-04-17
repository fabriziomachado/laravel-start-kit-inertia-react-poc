<div v-pre>

# Security

Workflow engines accept user-defined logic — node configs, expressions, command strings, webhook payloads. If your application lets end users create or configure workflows through a UI or API, you must treat every input as untrusted. This page covers the security measures built into the package and the best practices you should follow in production.

::: danger Critical
If your REST API is exposed to end users (not just internal admins), **every workflow input is an attack surface**. Expressions, node configs, command strings, and webhook payloads can all be abused without proper safeguards.
:::

## Authentication & Authorization

The package ships with configurable middleware but **does not enforce authentication by default**. The default `['api']` middleware provides rate limiting and stateless guards — it does **not** verify identity.

### Protect the API

Add authentication middleware in your config:

```php
// config/workflow-automation.php
'middleware' => ['api', 'auth:sanctum'],
```

### Add Authorization

The package's FormRequest classes return `authorize(): true` by default. Override them to add authorization logic:

```php
// app/Providers/AppServiceProvider.php
use Aftandilmmd\WorkflowAutomation\Http\Requests\StoreWorkflowRequest;

public function boot(): void
{
    StoreWorkflowRequest::macro('authorize', function () {
        return $this->user()->can('manage-workflows');
    });
}
```

Or publish and extend the request classes for full control.

::: tip
At a minimum, protect workflow creation, execution, and deletion behind an admin role or permission check. A malicious user who can create workflows can construct dangerous graphs.
:::

## Expression Engine Safety

The expression engine uses a **custom recursive descent parser** — there is no `eval()`, no PHP code execution, no shell access. Expressions are parsed into an AST and evaluated against a controlled variable scope.

### Expression Modes

| Mode | Functions | Variables | Use Case |
| --- | --- | --- | --- |
| `safe` (default) | 38+ whitelisted functions | Dot-notation access | General use |
| `strict` | None — functions disabled | Dot-notation access only | High-security environments |

Configure in `config/workflow-automation.php`:

```php
'expression_mode' => 'strict', // Disable all function calls
```

### What the Parser Blocks

- No `eval()`, `exec()`, `system()`, or any PHP function call
- No file system access
- No class instantiation or method calls
- No variable assignment or mutation
- No access to `$_GET`, `$_POST`, `$_SERVER`, or any superglobal

Even in `safe` mode, only the explicitly whitelisted functions are available (string, number, array, date, and type-casting helpers). See the [Expression Engine](/expressions/) reference for the full list.

::: warning
If users can write expressions, they can access any data in the `item`, `env`, and `node` scopes. Make sure you are not passing sensitive data (secrets, tokens, credentials) through workflow items unless necessary. Use `env()` references sparingly and never expose secrets in item payloads.
:::

## Input Validation

### Built-in Request Validation

The package validates all REST API inputs through Laravel FormRequest classes:

| Request | Validations |
| --- | --- |
| `StoreWorkflowRequest` | `name` required, max 255; `settings` must be array |
| `StoreNodeRequest` | `node_key` verified against NodeRegistry; `config` must be array |
| `StoreEdgeRequest` | Self-loops prevented; port names max 50 chars |
| `UpdateNodeRequest` | Partial updates; config must be array |
| `UpdateWorkflowRequest` | Partial updates; name max 255 |

### Validate Node Config

The built-in validation checks that `config` is an array, but it does **not** validate the contents of each config field against the node's `configSchema()`. If you expose workflow creation to end users, add config-level validation:

```php
use Aftandilmmd\WorkflowAutomation\Facades\NodeRegistry;

$nodeDefinition = NodeRegistry::get($nodeKey);
$schema = $nodeDefinition['config_schema'];

foreach ($schema as $field) {
    if ($field['required'] && empty($config[$field['key']])) {
        // Reject: required config field is missing
    }
}
```

### Sanitize Expression Inputs

If users provide expression strings (e.g. `{{ item.email }}`), validate that they parse correctly before saving:

```php
use Aftandilmmd\WorkflowAutomation\Engine\ExpressionEvaluator;

$evaluator = app(ExpressionEvaluator::class);

try {
    // Dry-run parse — catches syntax errors before the workflow runs
    $evaluator->resolve('{{ ' . $userExpression . ' }}', ['item' => []]);
} catch (\Throwable $e) {
    // Reject: invalid expression
}
```

## Command Execution Security

The `run_command` node can execute artisan commands and shell commands. This is the highest-risk node in the package.

::: danger
Never allow untrusted users to create `run_command` nodes with arbitrary commands. A single misconfigured node can compromise your entire server.
:::

### Restrict Allowed Commands

Always configure the allowlist in production:

```php
// config/workflow-automation.php
'run_command' => [
    'allowed_commands' => [
        'cache:clear',
        'cache:forget',
        'queue:restart',
        'cache:*',       // Wildcard: any cache command
        './scripts/*',   // Wildcard: scripts in scripts/
    ],
],
```

When the list is empty (default), **all commands are allowed**. When it contains entries, only matching commands can run.

### Disable Shell Access

If you don't need shell commands, disable them entirely:

```php
'run_command' => [
    'shell_enabled' => false,
],
```

Or via environment variable:

```ini
WORKFLOW_SHELL_ENABLED=false
```

### Restrict Node Types

If end users can create workflows, consider limiting which node types they can use. The `run_command` and `code` nodes are the most powerful — you may want to restrict them to admin users only.

#### Option 1: Middleware (Recommended)

Create a middleware that checks the `node_key` on node creation requests:

```php
// app/Http/Middleware/RestrictDangerousNodes.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RestrictDangerousNodes
{
    private array $dangerousNodes = ['run_command', 'code', 'dispatch_job'];

    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethod('post') && $request->has('node_key')) {
            if (in_array($request->input('node_key'), $this->dangerousNodes)) {
                if (! $request->user()?->isAdmin()) {
                    abort(403, 'You are not authorized to use this node type.');
                }
            }
        }

        return $next($request);
    }
}
```

Register it in your workflow middleware config:

```php
// config/workflow-automation.php
'middleware' => ['api', 'auth:sanctum', \App\Http\Middleware\RestrictDangerousNodes::class],
```

#### Option 2: FormRequest Macro

Override the `StoreNodeRequest` authorization to check node types:

```php
// app/Providers/AppServiceProvider.php
use Aftandilmmd\WorkflowAutomation\Http\Requests\StoreNodeRequest;

public function boot(): void
{
    StoreNodeRequest::macro('authorize', function () {
        $dangerousNodes = ['run_command', 'code', 'dispatch_job'];

        if (in_array($this->input('node_key'), $dangerousNodes)) {
            return $this->user()->isAdmin();
        }

        return true;
    });
}
```

#### Option 3: Inline Check in Controller

If you override the package's routes and use your own controller, add the check directly in the `store` method:

```php
public function store(StoreNodeRequest $request, Workflow $workflow)
{
    $dangerousNodes = ['run_command', 'code', 'dispatch_job'];

    if (in_array($request->input('node_key'), $dangerousNodes)) {
        if (! $request->user()->isAdmin()) {
            abort(403, 'You are not authorized to use this node type.');
        }
    }

    // ... continue with node creation
}
```

::: tip
The middleware approach is recommended because it works without modifying or overriding any package code, and it applies to both REST API and MCP tool calls.
:::

## Webhook Security

Webhook endpoints are public by design (external services need to reach them). The package provides per-webhook authentication:

| Auth Type | How It Works |
| --- | --- |
| `none` | No authentication (default) |
| `bearer` | Compares `Authorization: Bearer <token>` header |
| `basic` | Compares `Authorization: Basic <base64>` header |
| `header_key` | Compares `X-Webhook-Key` header |

### Best Practices

```php
// Always set auth on production webhooks
$trigger = $workflow->addNode('Stripe Webhook', 'webhook', [
    'method'     => 'POST',
    'auth_type'  => 'bearer',
    'auth_value' => config('services.stripe.webhook_secret'),
]);
```

- **Always enable authentication** on production webhooks — `none` should only be used for development
- **Use environment variables** for auth values — never hardcode secrets in workflow configs
- **Webhook URLs are UUID-based** — they are opaque and not guessable, but this is not a substitute for authentication
- **Validate webhook payloads** — if the sending service provides signature verification (e.g. Stripe HMAC), implement it in a custom node or middleware

## Graph Validation

Before a workflow executes, the engine validates the graph structure:

- **Single trigger** — exactly one trigger node is required
- **Node registration** — all node keys must exist in the NodeRegistry
- **Port validity** — source/target ports must be declared by the node
- **Cycle detection** — DFS-based cycle detection prevents infinite loops
- **Connectivity** — all non-trigger nodes must be reachable from the trigger
- **Required config** — required config fields must be set

Call validation explicitly before activating user-created workflows:

```php
use Aftandilmmd\WorkflowAutomation\Facades\WorkflowEngine;

$errors = WorkflowEngine::validate($workflow);

if (! empty($errors)) {
    // Reject activation — return errors to the user
}
```

Or via REST API:

```http
POST /workflow-engine/workflows/{id}/validate
```

## Data Exposure

### Item Payloads

Workflow items flow through every node in the graph. Be mindful of what data enters the pipeline:

- **Don't pass secrets** (API keys, passwords, tokens) as item fields — use `config()` or `env()` references instead
- **Strip sensitive fields** early in the workflow using a `set_fields` node with `keep_existing: false`
- **Log retention** — workflow run logs store item data. Configure retention to limit exposure:

```php
// config/workflow-automation.php
'log_retention_days' => 7, // Prune old runs regularly
```

### Config Storage

Node configs (including expressions) are stored in the database as JSON. If configs contain secrets:

- **Use the Credential Vault** — store secrets in the `workflow_credentials` table where they are encrypted at rest using AES-256-CBC. Nodes reference credentials by ID, not by value. See [Credential Vault](/advanced/credentials).
- Use environment variable references: `{{ env.STRIPE_KEY }}` instead of hardcoded values
- Restrict database access to the workflow tables

### Credential Vault

The package includes a built-in credential vault for encrypted secret management:

- Secrets are encrypted at rest using Laravel's `Crypt` (AES-256-CBC with `APP_KEY`)
- The REST API **never** returns decrypted credential data
- Credentials are resolved at runtime via middleware — decrypted values only exist in-memory during node execution
- Run logs never contain credential data (the `_credential` key is injected after logging)

```php
// Instead of storing secrets in node config:
// ❌ 'headers' => ['Authorization' => 'Bearer sk-xxx']

// Use credentials:
// ✅ 'credential_id' => 1
```

See the full [Credential Vault guide](/advanced/credentials) for details.

## Production Checklist

| Item | Config / Action |
| --- | --- |
| Add auth middleware | `'middleware' => ['api', 'auth:sanctum']` |
| Add authorization checks | Override FormRequest `authorize()` or use policies |
| Set expression mode | `'expression_mode' => 'strict'` for high-security |
| Configure command allowlist | `'run_command.allowed_commands' => [...]` |
| Disable shell if not needed | `'run_command.shell_enabled' => false` |
| Use credential vault | Store API keys and tokens as encrypted credentials, not in node config |
| Authenticate webhooks | Set `auth_type` + `credential_id` on every webhook node |
| Restrict dangerous nodes | Block `run_command`, `code`, `dispatch_job` for non-admins |
| Set log retention | `'log_retention_days' => 7` |
| Validate before activation | Call `WorkflowEngine::validate()` before activating |
| Don't pass secrets in items | Use credentials or `config()` / `env()` references instead |

</div>
