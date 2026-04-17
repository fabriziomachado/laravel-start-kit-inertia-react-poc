# Events

The package dispatches events during workflow execution. Listen for them in your `EventServiceProvider` or with closures.

## Available Events

| Event | When | Payload |
|-------|------|---------|
| `WorkflowStarted` | A workflow run begins | `$run` |
| `WorkflowCompleted` | A workflow run finishes successfully | `$run`, `$outputData` |
| `WorkflowFailed` | A workflow run fails with an exception | `$run`, `$exception`, `$outputData` |
| `WorkflowResumed` | A paused workflow is resumed | `$run`, `$payload` |
| `NodeExecuted` | A node finishes successfully | `$nodeRun` |
| `NodeFailed` | A node execution fails | `$nodeRun`, `$exception` |

## Event Classes

### WorkflowStarted

```php
use Aftandilmmd\WorkflowAutomation\Events\WorkflowStarted;

// Properties
$event->run;  // WorkflowRun model
```

### WorkflowCompleted

```php
use Aftandilmmd\WorkflowAutomation\Events\WorkflowCompleted;

// Properties
$event->run;         // WorkflowRun model
$event->outputData;  // array — all node outputs (context)
```

### WorkflowFailed

```php
use Aftandilmmd\WorkflowAutomation\Events\WorkflowFailed;

// Properties
$event->run;         // WorkflowRun model
$event->exception;   // \Throwable
$event->outputData;  // array — node outputs collected before failure
```

### WorkflowResumed

```php
use Aftandilmmd\WorkflowAutomation\Events\WorkflowResumed;

// Properties
$event->run;      // WorkflowRun model
$event->payload;  // array — resume payload data
```

### NodeExecuted

```php
use Aftandilmmd\WorkflowAutomation\Events\NodeExecuted;

// Properties
$event->nodeRun;  // WorkflowNodeRun model
```

### NodeFailed

```php
use Aftandilmmd\WorkflowAutomation\Events\NodeFailed;

// Properties
$event->nodeRun;    // WorkflowNodeRun model
$event->exception;  // \Throwable
```

## Listening to Events

### Via EventServiceProvider

```php
// app/Providers/EventServiceProvider.php

use Aftandilmmd\WorkflowAutomation\Events\WorkflowCompleted;
use Aftandilmmd\WorkflowAutomation\Events\WorkflowFailed;
use Aftandilmmd\WorkflowAutomation\Events\NodeFailed;

protected $listen = [
    WorkflowCompleted::class => [
        \App\Listeners\LogWorkflowCompletion::class,
    ],
    WorkflowFailed::class => [
        \App\Listeners\NotifyAdminOnFailure::class,
    ],
    NodeFailed::class => [
        \App\Listeners\TrackNodeErrors::class,
    ],
];
```

### Via Closure

```php
// app/Providers/AppServiceProvider.php

use Aftandilmmd\WorkflowAutomation\Events\WorkflowFailed;
use Illuminate\Support\Facades\Event;

public function boot(): void
{
    Event::listen(WorkflowFailed::class, function (WorkflowFailed $event) {
        logger()->error("Workflow #{$event->run->workflow_id} failed", [
            'run_id'  => $event->run->id,
            'error'   => $event->exception->getMessage(),
        ]);
    });
}
```

## Example: Audit Logging

```php
namespace App\Listeners;

use Aftandilmmd\WorkflowAutomation\Events\WorkflowCompleted;

class LogWorkflowCompletion
{
    public function handle(WorkflowCompleted $event): void
    {
        $run = $event->run;

        \App\Models\AuditLog::create([
            'event'       => 'workflow.completed',
            'workflow_id' => $run->workflow_id,
            'run_id'      => $run->id,
            'duration_ms' => $run->started_at
                ? $run->finished_at->diffInMilliseconds($run->started_at)
                : null,
        ]);
    }
}
```
