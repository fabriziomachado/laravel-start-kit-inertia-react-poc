# Artisan Commands

## workflow:schedule-run

Check all active schedule triggers and dispatch due workflows.

```bash
php artisan workflow:schedule-run
```

Add to your Laravel scheduler to run every minute:

```php
// routes/console.php
Schedule::command('workflow:schedule-run')->everyMinute();
```

**What it does:**

1. Queries all `schedule` trigger nodes in active workflows
2. Evaluates whether each trigger is due (cron match or interval check)
3. Dispatches matching workflows to the queue via `ExecuteWorkflowJob`

**Output:**

```
Dispatched workflow #1 (schedule trigger)
Dispatched workflow #5 (schedule trigger)
Done. Dispatched 2 workflow(s).
```

## workflow:clean-runs

Delete old workflow run records based on the configured retention period.

```bash
php artisan workflow:clean-runs
```

**Options:**

| Option | Description |
|--------|-------------|
| `--days=N` | Override the retention period from config |

**Examples:**

```bash
# Use configured retention (default: 30 days)
php artisan workflow:clean-runs

# Delete runs older than 7 days
php artisan workflow:clean-runs --days=7
```

**Output:**

```
Deleted 156 workflow run(s) older than 30 day(s).
```

If `log_retention_days` is `0` in config:

```
Log retention is disabled (0 days). Nothing to clean.
```

::: tip Scheduling Cleanup
Add to your scheduler for automatic cleanup:

```php
Schedule::command('workflow:clean-runs')->daily();
```
:::

## workflow:validate

Validate a workflow graph and print any errors.

```bash
php artisan workflow:validate {workflow}
```

**Arguments:**

| Argument | Description |
|----------|-------------|
| `workflow` | The workflow ID to validate |

**Examples:**

```bash
# Validate workflow #1
php artisan workflow:validate 1
```

**Output (valid):**

```
Workflow #1 "Order Processing" is valid.
```

**Output (invalid):**

```
Workflow #2 "Broken Flow" has 2 error(s):
  • Workflow has no trigger node.
  • Node 'Send Email' has no incoming edges.
```
