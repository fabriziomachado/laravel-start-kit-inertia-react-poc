<div v-pre>

# Schedule Trigger

The `schedule` trigger runs a workflow automatically on a time-based schedule using cron expressions or simple intervals.

**Node key:** `schedule`

## Config

| Key | Type | Required | Expression | Description |
| --- | --- | --- | --- | --- |
| `interval_type` | select | Yes | No | `minutes`, `hours`, `days`, or `custom_cron` |
| `interval_value` | integer | No | No | Interval count (for `minutes`, `hours`, `days`) |
| `cron` | string | No | No | Cron expression (for `custom_cron` type) |

## Ports

| Direction | Port | Description |
| --- | --- | --- |
| Output | `main` | Single item with `triggered_at` timestamp |

## Setup

Add the schedule runner to your Laravel scheduler:

```php
// routes/console.php (Laravel 11+)
Schedule::command('workflow:schedule-run')->everyMinute();
```

Make sure the Laravel scheduler itself is running:

```bash
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

The `workflow:schedule-run` command checks all active schedule triggers every minute and dispatches due workflows to the queue.

## How It Works

```text
Every minute, the scheduler runs:
  php artisan workflow:schedule-run
          │
          │ (checks if cron/interval matches current time)
          ▼
   ┌───────────────────┐
   │ Schedule Trigger  │
   └──────┬────────────┘
          │ main
          ▼
   [{"triggered_at": "2024-01-15T08:00:00Z"}]
```

## Examples

### Custom Cron Expressions

```php
// Every day at 8:00 AM
$trigger = $workflow->addNode('Daily 8 AM', 'schedule', [
    'interval_type' => 'custom_cron',
    'cron'          => '0 8 * * *',
]);

// Weekdays at 9:00 AM
$trigger = $workflow->addNode('Weekday 9 AM', 'schedule', [
    'interval_type' => 'custom_cron',
    'cron'          => '0 9 * * 1-5',
]);

// First day of each month at midnight
$trigger = $workflow->addNode('Monthly', 'schedule', [
    'interval_type' => 'custom_cron',
    'cron'          => '0 0 1 * *',
]);
```

### Simple Intervals

```php
// Every 5 minutes
$trigger = $workflow->addNode('Every 5 Min', 'schedule', [
    'interval_type'  => 'minutes',
    'interval_value' => 5,
]);

// Every 2 hours
$trigger = $workflow->addNode('Every 2 Hours', 'schedule', [
    'interval_type'  => 'hours',
    'interval_value' => 2,
]);
```

## Cron Expression Reference

```text
┌───────────── minute (0 - 59)
│ ┌───────────── hour (0 - 23)
│ │ ┌───────────── day of month (1 - 31)
│ │ │ ┌───────────── month (1 - 12)
│ │ │ │ ┌───────────── day of week (0 - 7, Sun = 0 or 7)
│ │ │ │ │
* * * * *
```

| Pattern | Meaning |
| --- | --- |
| `*` | Every value |
| `*/n` | Every n-th value |
| `1,15` | Specific values |
| `1-5` | Range of values |
| `0 8 * * *` | Daily at 8:00 AM |
| `0 */2 * * *` | Every 2 hours |
| `0 9 * * 1-5` | Weekdays at 9:00 AM |

## Input / Output

**Input:** None (triggers have no input ports)

**Output on `main` port:**

```php
[
    ['triggered_at' => '2024-01-15T08:00:00.000000Z'],
]
```

## Tips

- The schedule trigger payload is always `[['triggered_at' => '...']]` — use downstream HTTP or database nodes to fetch actual data
- The `workflow:schedule-run` command dispatches workflows to the queue by default
- Multiple workflows can share the same cron schedule — they all fire independently
- Use `{{ date_format(now(), "Y-m-d") }}` in downstream node configs to reference the current date


</div>
