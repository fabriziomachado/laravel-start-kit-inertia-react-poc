<?php

declare(strict_types=1);

use Aftandilmmd\WorkflowAutomation\Jobs\ExecuteWorkflowJob;
use Aftandilmmd\WorkflowAutomation\Listeners\EventListener;
use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowNode;
use Aftandilmmd\WorkflowAutomation\Nodes\Triggers\EventTrigger;
use Illuminate\Support\Facades\Queue;

// Test event class
final class OrderPlaced
{
    public function __construct(
        public int $orderId,
        public string $customerName,
    ) {}
}

final class PaymentProcessed
{
    public function __construct(
        public int $amount,
        public string $currency,
    ) {}

    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency,
            'processed' => true,
        ];
    }
}

it('dispatches a job when a configured event is fired', function () {
    Queue::fake();

    $workflow = Workflow::factory()->active()->create();
    $trigger = WorkflowNode::factory()->trigger('event')->create([
        'workflow_id' => $workflow->id,
        'name' => 'Order Placed',
        'config' => [
            'event_class' => OrderPlaced::class,
        ],
    ]);

    EventListener::register();

    event(new OrderPlaced(orderId: 42, customerName: 'Alice'));

    Queue::assertPushed(ExecuteWorkflowJob::class, function ($job) use ($workflow, $trigger) {
        return $job->workflowId === $workflow->id
            && $job->triggerNodeId === $trigger->id
            && $job->payload === [['orderId' => 42, 'customerName' => 'Alice']];
    });
});

it('does not dispatch for inactive workflows', function () {
    Queue::fake();

    $workflow = Workflow::factory()->create(['is_active' => false]);
    WorkflowNode::factory()->trigger('event')->create([
        'workflow_id' => $workflow->id,
        'name' => 'Order Placed',
        'config' => [
            'event_class' => OrderPlaced::class,
        ],
    ]);

    EventListener::register();

    event(new OrderPlaced(orderId: 1, customerName: 'Bob'));

    Queue::assertNotPushed(ExecuteWorkflowJob::class);
});

it('uses toArray when available on the event', function () {
    Queue::fake();

    $workflow = Workflow::factory()->active()->create();
    WorkflowNode::factory()->trigger('event')->create([
        'workflow_id' => $workflow->id,
        'name' => 'Payment Processed',
        'config' => [
            'event_class' => PaymentProcessed::class,
        ],
    ]);

    EventListener::register();

    event(new PaymentProcessed(amount: 100, currency: 'USD'));

    Queue::assertPushed(ExecuteWorkflowJob::class, function ($job) {
        return $job->payload === [['amount' => 100, 'currency' => 'USD', 'processed' => true]];
    });
});

it('extracts public properties from event objects', function () {
    $trigger = new EventTrigger;

    $event = new OrderPlaced(orderId: 99, customerName: 'Charlie');
    $payload = $trigger->extractPayload($event);

    expect($payload)->toBe([['orderId' => 99, 'customerName' => 'Charlie']]);
});

it('prefers toArray over public properties', function () {
    $trigger = new EventTrigger;

    $event = new PaymentProcessed(amount: 50, currency: 'EUR');
    $payload = $trigger->extractPayload($event);

    expect($payload)->toBe([['amount' => 50, 'currency' => 'EUR', 'processed' => true]]);
});
