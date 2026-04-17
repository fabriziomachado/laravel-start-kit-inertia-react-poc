# Content Moderation

Automatically screen new posts for prohibited keywords as soon as they are created. Clean posts are published immediately. Flagged posts are sent to an AI moderation job for deeper review and set to "under review" status until a human or AI makes a final decision.

## What This Covers

```text
+------------------------------------------------------+
|  Nodes used in this example:                         |
|                                                      |
|  - model_event     Trigger on Post::created          |
|  - code            Check prohibited keywords         |
|  - if_condition    Route flagged vs clean posts      |
|  - dispatch_job    Send flagged posts to AI queue    |
|  - update_model    Set status (published/review)     |
|                                                      |
|  Concepts: model event auto-trigger, code node in    |
|  transform mode, expression functions (contains,     |
|  lower), boolean logic, dispatch_job with with_item, |
|  dual-branch IF workflow                             |
+------------------------------------------------------+
```

## Workflow Diagram

```text
                          +-------------------------+
                          |  Model Event: Post      |
                          |  (created)              |
                          +------------+------------+
                                       |
                                       v
                          +------------+------------+
                          |  Code: check keywords   |
                          |  (transform mode)       |
                          +------------+------------+
                                       |
                                       v
                          +------------+------------+
                          |  IF: _result == true    |
                          |  (flagged?)             |
                          +----+--------------+-----+
                               |              |
                            true            false
                          (flagged)        (clean)
                               |              |
                               v              v
                   +-----------+---+  +-------+----------+
                   | Dispatch Job: |  | Update Model:    |
                   | AI moderation |  | status=published |
                   +-------+-------+  +------------------+
                           |
                           v
                   +-------+-----------+
                   | Update Model:     |
                   | status=under_     |
                   | review            |
                   +-------------------+
```

## Workflow Setup

```php
// app/Console/Commands/SetupContentModeration.php

use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use Illuminate\Console\Command;

class SetupContentModeration extends Command
{
    protected $signature = 'workflow:setup-content-moderation';
    protected $description = 'Create the content moderation workflow';

    public function handle(): void
    {
        $workflow = Workflow::create(['name' => 'Content Moderation']);

        // 1. Trigger when a new Post model is created
        $trigger = $workflow->addNode('Post Created', 'model_event', [
            'model'  => 'App\\Models\\Post',
            'events' => ['created'],
        ]);

        // 2. Code node — check for prohibited keywords
        //    In transform mode, the expression result is stored as `_result` on the item
        $keywordCheck = $workflow->addNode('Check Keywords', 'code', [
            'mode'       => 'transform',
            'expression' => 'contains(lower(item.content), "spam") || contains(lower(item.content), "scam") || contains(lower(item.content), "buy now") || contains(lower(item.content), "free money")',
        ]);

        // 3. IF condition — check whether the code node flagged the post
        $flaggedCheck = $workflow->addNode('Flagged?', 'if_condition', [
            'field'    => '{{ item._result }}',
            'operator' => 'equals',
            'value'    => 'true',
        ]);

        // 4a. Flagged — dispatch an AI moderation job for deeper analysis
        $aiReview = $workflow->addNode('AI Moderation', 'dispatch_job', [
            'job_class' => 'App\\Jobs\\AIModerationJob',
            'queue'     => 'moderation',
            'with_item' => true,
        ]);

        // 4a-cont. Update the post status to "under_review"
        $underReview = $workflow->addNode('Set Under Review', 'update_model', [
            'model'      => 'App\\Models\\Post',
            'find_by'    => 'id',
            'find_value' => '{{ item.id }}',
            'fields'     => [
                'status'      => 'under_review',
                'flagged_at'  => '{{ now() }}',
            ],
        ]);

        // 4b. Clean — publish the post immediately
        $publish = $workflow->addNode('Publish Post', 'update_model', [
            'model'      => 'App\\Models\\Post',
            'find_by'    => 'id',
            'find_value' => '{{ item.id }}',
            'fields'     => [
                'status'       => 'published',
                'published_at' => '{{ now() }}',
            ],
        ]);

        // Wire the graph
        $trigger->connect($keywordCheck);
        $keywordCheck->connect($flaggedCheck);
        $flaggedCheck->connect($aiReview, 'true');        // Flagged posts
        $flaggedCheck->connect($publish, 'false');          // Clean posts
        $aiReview->connect($underReview);

        $workflow->activate();

        $this->info("Content moderation workflow created (ID: {$workflow->id})");
    }
}
```

## AI Moderation Job

Create the job class that receives the flagged post data for deeper analysis:

```php
// app/Jobs/AIModerationJob.php

namespace App\Jobs;

use App\Models\Post;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AIModerationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public array $data)
    {
    }

    public function handle(): void
    {
        $post = Post::find($this->data['id']);

        if (! $post) {
            return;
        }

        // Call your AI moderation service here
        // $result = AIModerationService::review($post->content);

        // Update the post based on AI decision
        // $post->update([
        //     'status' => $result->safe ? 'published' : 'rejected',
        //     'moderation_notes' => $result->reason,
        // ]);
    }
}
```

## Registering the Model Event Listener

Add the listener in your `AppServiceProvider` so Post model events trigger the workflow:

```php
// app/Providers/AppServiceProvider.php

use Aftandilmmd\WorkflowAutomation\Listeners\ModelEventListener;

public function boot(): void
{
    ModelEventListener::register();
}
```

## Triggering the Workflow

The workflow fires automatically when a `Post` is created. No manual `start()` call is needed:

```php
use App\Models\Post;

// Clean post — gets published immediately
Post::create([
    'title'   => 'My First Blog Post',
    'content' => 'Here are my thoughts on Laravel workflow automation...',
    'user_id' => 1,
    'status'  => 'draft',
]);

// Flagged post — dispatches AI moderation job, set to under_review
Post::create([
    'title'   => 'Amazing Deal',
    'content' => 'Buy now and get free money! This is not a scam!',
    'user_id' => 2,
    'status'  => 'draft',
]);
```

## What Happens

1. **Model Event trigger** fires when `Post::create()` is called. The post's `toArray()` data becomes the workflow item.
2. **Code node** evaluates the expression in transform mode. It checks if the lowercase content contains any prohibited keywords (`"spam"`, `"scam"`, `"buy now"`, `"free money"`). The boolean result is stored as `_result` on the item.
3. **IF Condition** reads `item._result`. If `true`, the post is flagged; if `false`, it is clean.
4. **Flagged path**: The `dispatch_job` node dispatches `AIModerationJob` to the `moderation` queue with the full item data (because `with_item: true`). Then the `update_model` node sets the post status to `"under_review"` and records a `flagged_at` timestamp.
5. **Clean path**: The `update_model` node sets the post status to `"published"` and records a `published_at` timestamp.

The AI moderation job runs asynchronously on the queue. The workflow does not wait for it to complete -- it dispatches and moves on. Your AI service can then approve or reject the post independently.

## Concepts Demonstrated

| Concept | How It Is Used |
|---------|----------------|
| Model event trigger | Workflow starts automatically on `Post::created` |
| Code node (transform mode) | Evaluates a keyword-checking expression; result stored as `_result` |
| Expression functions | `contains()` and `lower()` are built-in expression functions for string operations |
| Boolean expression logic | `\|\|` (OR) chains multiple keyword checks into a single expression |
| IF condition on computed field | Checks the `_result` field produced by the code node |
| Dispatch job node | Sends flagged posts to an async AI moderation queue job |
| with_item flag | Passes the full item array to the job constructor |
| Update model node | Sets post status (`published` or `under_review`) and timestamps |
| Dual-branch workflow | Clean and flagged posts follow completely separate paths |
