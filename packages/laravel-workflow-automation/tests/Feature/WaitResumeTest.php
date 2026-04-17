<?php

declare(strict_types=1);

use Aftandilmmd\WorkflowAutomation\Enums\NodeType;
use Aftandilmmd\WorkflowAutomation\Enums\RunStatus;
use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowEdge;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowNode;
use Aftandilmmd\WorkflowAutomation\Services\WorkflowService;
use Illuminate\Support\Facades\Queue;

it('pauses workflow at wait_resume node', function () {
    Queue::fake();

    $workflow = Workflow::factory()->active()->create();

    $trigger = WorkflowNode::factory()->trigger()->create([
        'workflow_id' => $workflow->id,
        'name' => 'Start',
    ]);

    $waitNode = WorkflowNode::factory()->create([
        'workflow_id' => $workflow->id,
        'type' => NodeType::Control,
        'node_key' => 'wait_resume',
        'name' => 'Wait for Approval',
        'config' => [],
    ]);

    $afterWait = WorkflowNode::factory()->create([
        'workflow_id' => $workflow->id,
        'type' => NodeType::Transformer,
        'node_key' => 'set_fields',
        'name' => 'After Approval',
        'config' => ['fields' => ['approved' => true]],
    ]);

    WorkflowEdge::factory()->create([
        'workflow_id' => $workflow->id,
        'source_node_id' => $trigger->id,
        'target_node_id' => $waitNode->id,
    ]);
    WorkflowEdge::factory()->create([
        'workflow_id' => $workflow->id,
        'source_node_id' => $waitNode->id,
        'source_port' => 'resume',
        'target_node_id' => $afterWait->id,
    ]);

    $service = app(WorkflowService::class);
    $run = $service->run($workflow, [['request_id' => 42]]);

    expect($run->status)->toBe(RunStatus::Waiting);
    expect($run->context)->not->toBeNull();

    // The wait node should have generated a resume_token in its output
    $waitNodeRun = $run->nodeRuns->firstWhere('node_id', $waitNode->id);
    expect($waitNodeRun)->not->toBeNull();
});

it('can cancel a waiting run via API', function () {
    Queue::fake();

    $workflow = Workflow::factory()->active()->create();
    $trigger = WorkflowNode::factory()->trigger()->create(['workflow_id' => $workflow->id, 'name' => 'Start']);
    $waitNode = WorkflowNode::factory()->create([
        'workflow_id' => $workflow->id,
        'type' => NodeType::Control,
        'node_key' => 'wait_resume',
        'name' => 'Wait',
        'config' => [],
    ]);
    WorkflowEdge::factory()->create([
        'workflow_id' => $workflow->id,
        'source_node_id' => $trigger->id,
        'target_node_id' => $waitNode->id,
    ]);

    $service = app(WorkflowService::class);
    $run = $service->run($workflow, [['data' => 1]]);

    expect($run->status)->toBe(RunStatus::Waiting);

    $this->postJson("/workflow-engine/runs/{$run->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    expect($run->fresh()->status)->toBe(RunStatus::Cancelled);
});
