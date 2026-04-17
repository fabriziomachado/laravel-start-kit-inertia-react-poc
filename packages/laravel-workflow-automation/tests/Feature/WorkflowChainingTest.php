<?php

declare(strict_types=1);

use Aftandilmmd\WorkflowAutomation\Enums\NodeType;
use Aftandilmmd\WorkflowAutomation\Enums\RunStatus;
use Aftandilmmd\WorkflowAutomation\Events\WorkflowCompleted;
use Aftandilmmd\WorkflowAutomation\Events\WorkflowFailed;
use Aftandilmmd\WorkflowAutomation\Listeners\WorkflowChainListener;
use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowEdge;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowNode;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowRun;
use Aftandilmmd\WorkflowAutomation\Services\WorkflowService;
use Illuminate\Support\Facades\Queue;

// ── WorkflowTrigger Node ─────────────────────────────────────────

it('registers workflow trigger node in the registry', function () {
    $registry = app(Aftandilmmd\WorkflowAutomation\Registry\NodeRegistry::class);

    expect($registry->has('workflow'))->toBeTrue();

    $meta = $registry->getMeta('workflow');
    expect($meta['type'])->toBe(NodeType::Trigger);
    expect($meta['label'])->toBe('Workflow Trigger');
});

it('workflow trigger node passes items through', function () {
    $node = new Aftandilmmd\WorkflowAutomation\Nodes\Triggers\WorkflowTrigger();

    $context = new Aftandilmmd\WorkflowAutomation\DTOs\ExecutionContext(
        workflowRunId: 1,
        workflowId: 1,
        initialPayload: [],
    );

    $input = new Aftandilmmd\WorkflowAutomation\DTOs\NodeInput(
        items: [['source_workflow_id' => 1, 'data' => ['foo' => 'bar']]],
        context: $context,
    );

    $output = $node->execute($input, ['trigger_on' => 'completed']);

    expect($output->items('main'))->toHaveCount(1);
    expect($output->items('main')[0]['source_workflow_id'])->toBe(1);
});

// ── Workflow Chaining (Event-driven) ─────────────────────────────

it('triggers chained workflow when source workflow completes', function () {
    Queue::fake();

    // Source workflow (A)
    $workflowA = Workflow::factory()->active()->create(['name' => 'Source']);
    WorkflowNode::factory()->trigger()->create([
        'workflow_id' => $workflowA->id,
        'name' => 'Start',
    ]);

    // Target workflow (B) with workflow trigger
    $workflowB = Workflow::factory()->active()->create(['name' => 'Target']);
    WorkflowNode::factory()->create([
        'workflow_id' => $workflowB->id,
        'type' => NodeType::Trigger,
        'node_key' => 'workflow',
        'name' => 'Workflow Trigger',
        'config' => [
            'source_workflow_id' => $workflowA->id,
            'trigger_on' => 'completed',
        ],
    ]);

    // Clear cache so listener picks up new triggers
    WorkflowChainListener::clearCache();

    // Simulate workflow A completing
    $runA = WorkflowRun::factory()->create([
        'workflow_id' => $workflowA->id,
        'status' => RunStatus::Completed,
    ]);

    event(new WorkflowCompleted($runA, ['node_1' => ['main' => [['result' => 'ok']]]]));

    Queue::assertPushed(Aftandilmmd\WorkflowAutomation\Jobs\ExecuteWorkflowJob::class, function ($job) use ($workflowB) {
        return $job->workflowId === $workflowB->id
            && $job->payload[0]['source_workflow_id'] !== null
            && $job->payload[0]['source_status'] === 'completed';
    });
});

it('triggers chained workflow when source workflow fails', function () {
    Queue::fake();

    $workflowA = Workflow::factory()->active()->create(['name' => 'Source']);
    WorkflowNode::factory()->trigger()->create(['workflow_id' => $workflowA->id]);

    $workflowB = Workflow::factory()->active()->create(['name' => 'Error Handler']);
    WorkflowNode::factory()->create([
        'workflow_id' => $workflowB->id,
        'type' => NodeType::Trigger,
        'node_key' => 'workflow',
        'name' => 'Workflow Trigger',
        'config' => [
            'source_workflow_id' => $workflowA->id,
            'trigger_on' => 'failed',
        ],
    ]);

    WorkflowChainListener::clearCache();

    $runA = WorkflowRun::factory()->create([
        'workflow_id' => $workflowA->id,
        'status' => RunStatus::Failed,
    ]);

    event(new WorkflowFailed($runA, new RuntimeException('Something broke'), []));

    Queue::assertPushed(Aftandilmmd\WorkflowAutomation\Jobs\ExecuteWorkflowJob::class, function ($job) use ($workflowB) {
        return $job->workflowId === $workflowB->id
            && $job->payload[0]['source_status'] === 'failed'
            && $job->payload[0]['error_message'] === 'Something broke';
    });
});

it('does not trigger chain when trigger_on does not match', function () {
    Queue::fake();

    $workflowA = Workflow::factory()->active()->create();
    WorkflowNode::factory()->trigger()->create(['workflow_id' => $workflowA->id]);

    $workflowB = Workflow::factory()->active()->create();
    WorkflowNode::factory()->create([
        'workflow_id' => $workflowB->id,
        'type' => NodeType::Trigger,
        'node_key' => 'workflow',
        'config' => ['source_workflow_id' => $workflowA->id, 'trigger_on' => 'failed'],
    ]);

    WorkflowChainListener::clearCache();

    $runA = WorkflowRun::factory()->create([
        'workflow_id' => $workflowA->id,
        'status' => RunStatus::Completed,
    ]);

    event(new WorkflowCompleted($runA, []));

    Queue::assertNotPushed(Aftandilmmd\WorkflowAutomation\Jobs\ExecuteWorkflowJob::class);
});

it('triggers chain with trigger_on any for both completed and failed', function () {
    Queue::fake();

    $workflowA = Workflow::factory()->active()->create();
    WorkflowNode::factory()->trigger()->create(['workflow_id' => $workflowA->id]);

    $workflowB = Workflow::factory()->active()->create();
    WorkflowNode::factory()->create([
        'workflow_id' => $workflowB->id,
        'type' => NodeType::Trigger,
        'node_key' => 'workflow',
        'config' => ['source_workflow_id' => $workflowA->id, 'trigger_on' => 'any'],
    ]);

    WorkflowChainListener::clearCache();

    $runA = WorkflowRun::factory()->create([
        'workflow_id' => $workflowA->id,
        'status' => RunStatus::Completed,
    ]);

    event(new WorkflowCompleted($runA, []));

    Queue::assertPushed(Aftandilmmd\WorkflowAutomation\Jobs\ExecuteWorkflowJob::class);
});

it('does not trigger chain for inactive target workflow', function () {
    Queue::fake();

    $workflowA = Workflow::factory()->active()->create();
    WorkflowNode::factory()->trigger()->create(['workflow_id' => $workflowA->id]);

    $workflowB = Workflow::factory()->create(['is_active' => false]);
    WorkflowNode::factory()->create([
        'workflow_id' => $workflowB->id,
        'type' => NodeType::Trigger,
        'node_key' => 'workflow',
        'config' => ['source_workflow_id' => $workflowA->id, 'trigger_on' => 'completed'],
    ]);

    WorkflowChainListener::clearCache();

    $runA = WorkflowRun::factory()->create([
        'workflow_id' => $workflowA->id,
        'status' => RunStatus::Completed,
    ]);

    event(new WorkflowCompleted($runA, []));

    Queue::assertNotPushed(Aftandilmmd\WorkflowAutomation\Jobs\ExecuteWorkflowJob::class);
});

it('does not trigger chain when source_workflow_id does not match', function () {
    Queue::fake();

    $workflowA = Workflow::factory()->active()->create();
    $workflowC = Workflow::factory()->active()->create();

    $workflowB = Workflow::factory()->active()->create();
    WorkflowNode::factory()->create([
        'workflow_id' => $workflowB->id,
        'type' => NodeType::Trigger,
        'node_key' => 'workflow',
        'config' => ['source_workflow_id' => $workflowC->id, 'trigger_on' => 'completed'],
    ]);

    WorkflowChainListener::clearCache();

    $runA = WorkflowRun::factory()->create([
        'workflow_id' => $workflowA->id,
        'status' => RunStatus::Completed,
    ]);

    event(new WorkflowCompleted($runA, []));

    Queue::assertNotPushed(Aftandilmmd\WorkflowAutomation\Jobs\ExecuteWorkflowJob::class);
});

it('triggers chain from any workflow when source_workflow_id is null', function () {
    Queue::fake();

    $workflowA = Workflow::factory()->active()->create();
    WorkflowNode::factory()->trigger()->create(['workflow_id' => $workflowA->id]);

    $workflowB = Workflow::factory()->active()->create();
    WorkflowNode::factory()->create([
        'workflow_id' => $workflowB->id,
        'type' => NodeType::Trigger,
        'node_key' => 'workflow',
        'config' => ['source_workflow_id' => null, 'trigger_on' => 'completed'],
    ]);

    WorkflowChainListener::clearCache();

    $runA = WorkflowRun::factory()->create([
        'workflow_id' => $workflowA->id,
        'status' => RunStatus::Completed,
    ]);

    event(new WorkflowCompleted($runA, []));

    Queue::assertPushed(Aftandilmmd\WorkflowAutomation\Jobs\ExecuteWorkflowJob::class);
});

// ── Chain Depth Protection ───────────────────────────────────────

it('respects max chain depth to prevent infinite loops', function () {
    Queue::fake();

    $workflowA = Workflow::factory()->active()->create();
    WorkflowNode::factory()->trigger()->create(['workflow_id' => $workflowA->id]);

    $workflowB = Workflow::factory()->active()->create();
    WorkflowNode::factory()->create([
        'workflow_id' => $workflowB->id,
        'type' => NodeType::Trigger,
        'node_key' => 'workflow',
        'config' => ['source_workflow_id' => $workflowA->id, 'trigger_on' => 'completed'],
    ]);

    WorkflowChainListener::clearCache();
    config()->set('workflow-automation.chaining.max_depth', 3);

    $runA = WorkflowRun::factory()->create([
        'workflow_id' => $workflowA->id,
        'status' => RunStatus::Completed,
        'initial_payload' => ['_chain_depth' => 3],
    ]);

    event(new WorkflowCompleted($runA, []));

    Queue::assertNotPushed(Aftandilmmd\WorkflowAutomation\Jobs\ExecuteWorkflowJob::class);
});

it('prevents self-triggering loops', function () {
    Queue::fake();

    $workflow = Workflow::factory()->active()->create();
    WorkflowNode::factory()->create([
        'workflow_id' => $workflow->id,
        'type' => NodeType::Trigger,
        'node_key' => 'workflow',
        'config' => ['source_workflow_id' => $workflow->id, 'trigger_on' => 'completed'],
    ]);

    WorkflowChainListener::clearCache();

    $run = WorkflowRun::factory()->create([
        'workflow_id' => $workflow->id,
        'status' => RunStatus::Completed,
    ]);

    event(new WorkflowCompleted($run, []));

    Queue::assertNotPushed(Aftandilmmd\WorkflowAutomation\Jobs\ExecuteWorkflowJob::class);
});

// ── SubWorkflowControl Enhancements ──────────────────────────────

it('sub workflow control returns output data in sync mode', function () {
    // Create parent workflow
    $parentWorkflow = Workflow::factory()->active()->create(['name' => 'Parent']);
    $parentTrigger = WorkflowNode::factory()->trigger()->create([
        'workflow_id' => $parentWorkflow->id,
        'name' => 'Start',
    ]);

    // Create child workflow
    $childWorkflow = Workflow::factory()->active()->create(['name' => 'Child']);
    $childTrigger = WorkflowNode::factory()->trigger()->create([
        'workflow_id' => $childWorkflow->id,
        'name' => 'Child Start',
    ]);
    $childSetFields = WorkflowNode::factory()->create([
        'workflow_id' => $childWorkflow->id,
        'type' => NodeType::Transformer,
        'node_key' => 'set_fields',
        'name' => 'Child Transform',
        'config' => ['fields' => ['child_result' => 'done']],
    ]);
    WorkflowEdge::factory()->create([
        'workflow_id' => $childWorkflow->id,
        'source_node_id' => $childTrigger->id,
        'target_node_id' => $childSetFields->id,
    ]);

    // Sub workflow node in parent
    $subNode = WorkflowNode::factory()->create([
        'workflow_id' => $parentWorkflow->id,
        'type' => NodeType::Control,
        'node_key' => 'sub_workflow',
        'name' => 'Run Child',
        'config' => [
            'workflow_id' => $childWorkflow->id,
            'pass_items' => true,
            'wait_for_result' => true,
        ],
    ]);

    WorkflowEdge::factory()->create([
        'workflow_id' => $parentWorkflow->id,
        'source_node_id' => $parentTrigger->id,
        'target_node_id' => $subNode->id,
    ]);

    $service = app(WorkflowService::class);
    $run = $service->run($parentWorkflow, [['test' => true]]);

    expect($run->status)->toBe(RunStatus::Completed);

    // Check sub workflow node output has the child's context
    $subNodeRun = $run->nodeRuns()->where('node_id', $subNode->id)->first();
    expect($subNodeRun->output['main'][0])->toHaveKeys(['sub_workflow_run_id', 'status', 'output']);
    expect($subNodeRun->output['main'][0]['status'])->toBe('completed');
    expect($subNodeRun->output['main'][0]['output'])->not->toBeEmpty();
});

// ── Parent Run ID Tracking ───────────────────────────────────────

it('sub workflow sets parent_run_id in sync mode', function () {
    $parentWorkflow = Workflow::factory()->active()->create();
    $parentTrigger = WorkflowNode::factory()->trigger()->create(['workflow_id' => $parentWorkflow->id]);

    $childWorkflow = Workflow::factory()->active()->create();
    $childTrigger = WorkflowNode::factory()->trigger()->create(['workflow_id' => $childWorkflow->id]);

    $subNode = WorkflowNode::factory()->create([
        'workflow_id' => $parentWorkflow->id,
        'type' => NodeType::Control,
        'node_key' => 'sub_workflow',
        'name' => 'Run Child',
        'config' => [
            'workflow_id' => $childWorkflow->id,
            'wait_for_result' => true,
        ],
    ]);

    WorkflowEdge::factory()->create([
        'workflow_id' => $parentWorkflow->id,
        'source_node_id' => $parentTrigger->id,
        'target_node_id' => $subNode->id,
    ]);

    $service = app(WorkflowService::class);
    $parentRun = $service->run($parentWorkflow);

    // Find child run
    $childRun = WorkflowRun::where('workflow_id', $childWorkflow->id)->first();

    expect($childRun)->not->toBeNull();
    expect($childRun->parent_run_id)->toBe($parentRun->id);
    expect($childRun->parentRun->id)->toBe($parentRun->id);
});

// ── WorkflowRun Relations ────────────────────────────────────────

it('workflow run has parent and child run relations', function () {
    $parentRun = WorkflowRun::factory()->create();
    $childRun = WorkflowRun::factory()->create([
        'parent_run_id' => $parentRun->id,
    ]);

    expect($childRun->parentRun->id)->toBe($parentRun->id);
    expect($parentRun->childRuns)->toHaveCount(1);
    expect($parentRun->childRuns->first()->id)->toBe($childRun->id);
});

// ── WorkflowCompleted Event carries output data ──────────────────

it('workflow completed event includes output data', function () {
    $workflow = Workflow::factory()->active()->create();
    $trigger = WorkflowNode::factory()->trigger()->create([
        'workflow_id' => $workflow->id,
        'name' => 'Start',
    ]);

    $setFields = WorkflowNode::factory()->create([
        'workflow_id' => $workflow->id,
        'type' => NodeType::Transformer,
        'node_key' => 'set_fields',
        'name' => 'Transform',
        'config' => ['fields' => ['done' => true]],
    ]);

    WorkflowEdge::factory()->create([
        'workflow_id' => $workflow->id,
        'source_node_id' => $trigger->id,
        'target_node_id' => $setFields->id,
    ]);

    $capturedEvent = null;
    Illuminate\Support\Facades\Event::listen(WorkflowCompleted::class, function ($event) use (&$capturedEvent) {
        $capturedEvent = $event;
    });

    $service = app(WorkflowService::class);
    $service->run($workflow, [['input' => 'data']]);

    expect($capturedEvent)->not->toBeNull();
    expect($capturedEvent->outputData)->not->toBeEmpty();
    expect($capturedEvent->run->status)->toBe(RunStatus::Completed);
});
