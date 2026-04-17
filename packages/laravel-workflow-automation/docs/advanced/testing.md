# Testing Workflows

## Synchronous Execution

For testing, run workflows synchronously to avoid needing a queue worker:

```php
use Aftandilmmd\WorkflowAutomation\Models\Workflow;

$workflow = Workflow::where('name', 'My Workflow')->first();
$run = $workflow->start([['name' => 'Alice', 'email' => 'alice@test.com']]);

$this->assertEquals('completed', $run->status->value);
```

## Testing Node Execution

Check individual node run results:

```php
$run = $workflow->start([['total' => 750]]);

// Check specific node execution
$nodeRun = $run->nodeRuns()
    ->whereHas('node', fn ($q) => $q->where('name', 'Notify VIP Team'))
    ->first();

$this->assertNotNull($nodeRun);
$this->assertEquals('completed', $nodeRun->status->value);
```

## Testing Conditional Branches

Verify the correct branch was taken:

```php
// Test TRUE branch
$run = $workflow->start([['total' => 750]]);
$vipNodeRun = $run->nodeRuns()
    ->whereHas('node', fn ($q) => $q->where('name', 'Notify VIP Team'))
    ->first();
$this->assertNotNull($vipNodeRun);

// Test FALSE branch
$run = $workflow->start([['total' => 100]]);
$vipNodeRun = $run->nodeRuns()
    ->whereHas('node', fn ($q) => $q->where('name', 'Notify VIP Team'))
    ->first();
$this->assertNull($vipNodeRun);
```

## Faking External Services

### HTTP Requests

Use Laravel's HTTP fake:

```php
use Illuminate\Support\Facades\Http;

Http::fake([
    'api.example.com/*' => Http::response(['status' => 'ok'], 200),
]);

$run = $workflow->start([['data' => 'test']]);

Http::assertSent(fn ($request) => $request->url() === 'https://api.example.com/endpoint');
```

### Mail

```php
use Illuminate\Support\Facades\Mail;

Mail::fake();

$run = $workflow->start([['email' => 'test@example.com', 'name' => 'Alice']]);

Mail::assertSent(fn ($mail) => $mail->hasTo('test@example.com'));
```

### Notifications

```php
use Illuminate\Support\Facades\Notification;

Notification::fake();

$run = $workflow->start([['user_id' => 1]]);

Notification::assertSentTo($user, MyNotification::class);
```

## Testing the Full Lifecycle

```php
use Aftandilmmd\WorkflowAutomation\Models\Workflow;

class OrderWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Workflow $workflow;

    protected function setUp(): void
    {
        parent::setUp();

        // Build the workflow
        $this->workflow = Workflow::create(['name' => 'Test Order']);

        $trigger = $this->workflow->addNode('Start', 'manual');
        $check   = $this->workflow->addNode('High Value?', 'if_condition', [
            'field' => 'total', 'operator' => 'greater_than', 'value' => 500,
        ]);
        $email   = $this->workflow->addNode('VIP Email', 'send_mail', [
            'to' => '{{ item.email }}', 'subject' => 'VIP!', 'body' => 'Welcome VIP.',
        ]);

        $trigger->connect($check);
        $check->connect($email, sourcePort: 'true');
        $this->workflow->activate();
    }

    public function test_high_value_order_sends_vip_email(): void
    {
        Mail::fake();

        $run = $this->workflow->start([[
            'total' => 750,
            'email' => 'vip@example.com',
        ]]);

        $this->assertEquals('completed', $run->status->value);
        Mail::assertSent(fn ($mail) => $mail->hasTo('vip@example.com'));
    }

    public function test_low_value_order_skips_vip_email(): void
    {
        Mail::fake();

        $run = $this->workflow->start([[
            'total' => 100,
            'email' => 'regular@example.com',
        ]]);

        $this->assertEquals('completed', $run->status->value);
        Mail::assertNothingSent();
    }
}
```

## Testing Wait/Resume

```php
public function test_approval_workflow(): void
{
    Mail::fake();

    // Start the workflow — it should pause at wait_resume
    $run = $this->workflow->start([[
        'amount' => 2500,
        'requester_email' => 'alice@example.com',
    ]]);

    $this->assertEquals('waiting', $run->fresh()->status->value);

    // Find the resume token
    $waitNodeRun = $run->nodeRuns()
        ->whereHas('node', fn ($q) => $q->where('node_key', 'wait_resume'))
        ->first();

    $resumeToken = $waitNodeRun->output['resume'][0]['resume_token'];

    // Resume with approval
    $run = \Aftandilmmd\WorkflowAutomation\Facades\Workflow::resume(
        $run->id, $resumeToken, ['approved' => true]
    );

    $this->assertEquals('completed', $run->status->value);
}
```

## Pinned Test Data

Pin fixed data to nodes for repeatable, isolated testing. Pinned output skips the node entirely; pinned input replaces computed input.

```php
use Aftandilmmd\WorkflowAutomation\Models\WorkflowNode;

// Pin output — node will be skipped in test mode
$node->update(['pinned_data' => [
    'output' => ['main' => [['name' => 'Alice', 'status' => 'processed']]],
]]);

// Pin input — node executes with this input
$node->update(['pinned_data' => [
    'input' => [['name' => 'Alice', 'email' => 'alice@test.com']],
]]);

// Pin from a previous node run
$nodeRun = $run->nodeRuns()->where('node_id', $node->id)->first();
$node->update(['pinned_data' => [
    'input'  => $nodeRun->input,
    'output' => $nodeRun->output,
    'source_run_id' => $nodeRun->workflow_run_id,
]]);

// Unpin
$node->update(['pinned_data' => null]);
```

Pinned data only takes effect in test mode (`executeUpTo`). Normal runs ignore it:

```php
// Test mode — pinned output is used, node is skipped
$service->executeUpTo($workflow, $node->id, $payload);

// Normal run — pinned data is ignored
$workflow->start($payload);
```

Use factories for testing pinned behavior:

```php
$node = WorkflowNode::factory()
    ->withPinnedOutput(['main' => [['result' => 'ok']]])
    ->create();

$node = WorkflowNode::factory()
    ->withPinnedInput([['name' => 'Test']])
    ->create();
```

## Validating Workflows

Test graph validation:

```php
$errors = $this->workflow->validateGraph();

$this->assertEmpty($errors);
```

Or for expected errors:

```php
// Workflow with no trigger
$workflow = Workflow::create(['name' => 'Invalid']);
$workflow->addNode('Email', 'send_mail', ['to' => 'a@b.com', 'subject' => 'Test', 'body' => 'Test']);

$errors = $workflow->validateGraph();

$this->assertContains('Workflow has no trigger node.', $errors);
```
