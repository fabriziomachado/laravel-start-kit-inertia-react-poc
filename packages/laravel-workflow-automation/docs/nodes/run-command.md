<div v-pre>

# Run Command

Executes artisan commands or shell commands from within a workflow. Supports allowlist-based security, configurable timeouts, and output capture.

**Node key:** `run_command` · **Type:** Action

## Config

| Key | Type | Required | Expression | Description |
| --- | --- | --- | --- | --- |
| `command_type` | select | Yes | No | `artisan` or `shell` |
| `command` | string | Yes | Yes | The command to execute (e.g. `cache:clear` or `./scripts/backup.sh`) |
| `arguments` | keyvalue | No | Yes | Arguments/options for artisan, or environment variables for shell |
| `timeout` | integer | No | No | Timeout in seconds (default: 60 for shell, unlimited for artisan) |
| `working_directory` | string | No | Yes | Working directory for shell commands (default: `base_path()`) |
| `include_output` | boolean | No | No | Include stdout/stderr in the output item |

## Ports

| Direction | Port | Description |
| --- | --- | --- |
| Input | `main` | Items to process |
| Output | `main` | Items with `command_result` field (exit code, success flag) |
| Output | `error` | Items where the command threw an exception |

## Command Types

### Artisan Commands

Runs Laravel artisan commands via `Artisan::call()`. The command runs synchronously within the current process — no separate shell is spawned.

```php
$node = $workflow->addNode('Clear Cache', 'run_command', [
    'command_type' => 'artisan',
    'command'      => 'cache:clear',
]);
```

With arguments:

```php
$node = $workflow->addNode('Seed Users', 'run_command', [
    'command_type'   => 'artisan',
    'command'        => 'db:seed',
    'arguments'      => ['--class' => 'UserSeeder'],
    'include_output' => true,
]);
```

### Shell Commands

Runs shell commands via Symfony Process. The command runs in a separate process with its own timeout.

```php
$node = $workflow->addNode('Run Backup', 'run_command', [
    'command_type'      => 'shell',
    'command'           => './scripts/db-backup.sh',
    'timeout'           => 120,
    'working_directory' => '/var/www/app',
    'include_output'    => true,
]);
```

The `arguments` field sets environment variables for shell commands:

```php
$node = $workflow->addNode('Export Data', 'run_command', [
    'command_type' => 'shell',
    'command'      => './scripts/export.sh',
    'arguments'    => [
        'DB_NAME'   => '{{ item.database }}',
        'OUTPUT_DIR' => '/tmp/exports',
    ],
]);
```

## Security

::: warning Important
The `run_command` node can execute arbitrary commands on your server. Always configure the allowlist in production.
:::

### Allowed Commands

Restrict which commands can be executed by setting an allowlist in `config/workflow-automation.php`:

```php
'run_command' => [
    'allowed_commands' => [
        'cache:clear',
        'cache:forget',
        'queue:restart',
        'cache:*',           // Wildcard: any cache command
        './scripts/*',       // Wildcard: any script in scripts/
    ],
],
```

When the list is **empty** (default), all commands are allowed. When it contains entries, only matching commands can run — everything else throws a `RuntimeException`.

Wildcard patterns use PHP's `fnmatch()` syntax.

### Disabling Shell Commands

To allow only artisan commands and block all shell execution:

```php
'run_command' => [
    'shell_enabled' => false,
],
```

Or via environment variable:

```ini
WORKFLOW_SHELL_ENABLED=false
```

## Example: Cache Clear After Deploy

```php
$trigger = $workflow->addNode('Deploy Webhook', 'webhook', [
    'method'     => 'POST',
    'auth_type'  => 'bearer',
    'auth_value' => config('services.deploy.webhook_secret'),
]);

$clearCache = $workflow->addNode('Clear Cache', 'run_command', [
    'command_type' => 'artisan',
    'command'      => 'cache:clear',
]);

$clearConfig = $workflow->addNode('Clear Config', 'run_command', [
    'command_type' => 'artisan',
    'command'      => 'config:clear',
]);

$restartQueue = $workflow->addNode('Restart Queue', 'run_command', [
    'command_type' => 'artisan',
    'command'      => 'queue:restart',
]);

$trigger->connect($clearCache);
$clearCache->connect($clearConfig);
$clearConfig->connect($restartQueue);
```

## Example: Database Backup with Error Handling

```php
$backup = $workflow->addNode('DB Backup', 'run_command', [
    'command_type'      => 'shell',
    'command'           => 'mysqldump -u $DB_USER -p$DB_PASS $DB_NAME > /backups/$(date +%Y%m%d).sql',
    'arguments'         => [
        'DB_USER' => '{{ env.DB_USERNAME }}',
        'DB_PASS' => '{{ env.DB_PASSWORD }}',
        'DB_NAME' => '{{ env.DB_DATABASE }}',
    ],
    'timeout'           => 300,
    'include_output'    => true,
]);

$errorHandler = $workflow->addNode('Handle Error', 'error_handler', [
    'rules'         => [
        ['match' => 'timeout', 'route' => 'retry'],
    ],
    'default_route' => 'notify',
]);

$notify = $workflow->addNode('Alert Team', 'send_mail', [
    'to'      => 'ops@company.com',
    'subject' => 'Backup failed',
    'body'    => 'Database backup failed: {{ item.error }}',
]);

$backup->connect($errorHandler, 'error');
$errorHandler->connect($notify, 'notify');
```

## Input / Output Example

**Input:**

```php
[
    ['task' => 'deploy', 'version' => '2.1.0'],
]
```

**Output (on `main` with `include_output: true`):**

```php
[
    [
        'task'    => 'deploy',
        'version' => '2.1.0',
        'command_result' => [
            'exit_code'    => 0,
            'success'      => true,
            'output'       => 'Application cache cleared successfully.',
            'error_output' => '',  // Only present when non-empty
        ],
    ],
]
```

**Output (on `main` with `include_output: false`):**

```php
[
    [
        'task'    => 'deploy',
        'version' => '2.1.0',
        'command_result' => [
            'exit_code' => 0,
            'success'   => true,
        ],
    ],
]
```

## Tips

- Use `command_type: artisan` whenever possible — it runs in-process and is safer than shell execution
- Always configure `allowed_commands` in production to prevent unauthorized command execution
- Set `WORKFLOW_SHELL_ENABLED=false` in environments where shell access is not needed
- Shell commands use Symfony Process — the timeout is enforced at the process level
- The `arguments` field serves different purposes: **artisan** passes them as command arguments, **shell** sets environment variables
- Downstream nodes can check `{{ item.command_result.success }}` to branch on success/failure
- Use expressions in the `command` field for dynamic commands: `cache:forget {{ item.cache_key }}`

</div>
