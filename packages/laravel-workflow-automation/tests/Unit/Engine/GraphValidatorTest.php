<?php

declare(strict_types=1);

use Aftandilmmd\WorkflowAutomation\Engine\GraphValidator;
use Aftandilmmd\WorkflowAutomation\Exceptions\WorkflowValidationException;
use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowEdge;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowNode;

beforeEach(function () {
    $this->validator = app(GraphValidator::class);
});

it('validates a valid linear workflow', function () {
    $workflow = Workflow::factory()->create();

    $trigger = WorkflowNode::factory()->trigger()->create(['workflow_id' => $workflow->id, 'name' => 'trigger']);
    $action = WorkflowNode::factory()->action('set_fields')->create([
        'workflow_id' => $workflow->id,
        'name' => 'set_fields',
        'config' => ['fields' => ['status' => 'done']],
    ]);

    WorkflowEdge::factory()->create([
        'workflow_id' => $workflow->id,
        'source_node_id' => $trigger->id,
        'target_node_id' => $action->id,
    ]);

    expect(fn () => $this->validator->validate($workflow))->not->toThrow(WorkflowValidationException::class);
});

it('fails when there is no trigger node', function () {
    $workflow = Workflow::factory()->create();

    WorkflowNode::factory()->action('set_fields')->create([
        'workflow_id' => $workflow->id,
        'name' => 'action',
        'config' => ['fields' => ['x' => 'y']],
    ]);

    $errors = $this->validator->errors($workflow);

    expect($errors)->toContain('Workflow must have at least one trigger node.');
});

it('fails when there are multiple trigger nodes', function () {
    $workflow = Workflow::factory()->create();

    WorkflowNode::factory()->trigger()->create(['workflow_id' => $workflow->id, 'name' => 'trigger1']);
    WorkflowNode::factory()->trigger()->create(['workflow_id' => $workflow->id, 'name' => 'trigger2']);

    $errors = $this->validator->errors($workflow);

    expect($errors)->toContain('Workflow must have exactly one trigger node, found 2.');
});

it('fails when a node uses an unregistered key', function () {
    $workflow = Workflow::factory()->create();

    WorkflowNode::factory()->trigger()->create(['workflow_id' => $workflow->id, 'name' => 'trigger']);
    WorkflowNode::factory()->create([
        'workflow_id' => $workflow->id,
        'node_key' => 'completely_fake_node',
        'name' => 'fake',
    ]);

    $errors = $this->validator->errors($workflow);

    expect($errors)->toHaveCount(fn ($c) => $c >= 1);
    expect(collect($errors)->first(fn ($e) => str_contains($e, 'unregistered key')))->not->toBeNull();
});

it('detects cycles in the graph', function () {
    $workflow = Workflow::factory()->create();

    $trigger = WorkflowNode::factory()->trigger()->create(['workflow_id' => $workflow->id, 'name' => 'trigger']);
    $nodeA = WorkflowNode::factory()->action('set_fields')->create([
        'workflow_id' => $workflow->id,
        'name' => 'A',
        'config' => ['fields' => ['x' => 'y']],
    ]);
    $nodeB = WorkflowNode::factory()->action('set_fields')->create([
        'workflow_id' => $workflow->id,
        'name' => 'B',
        'config' => ['fields' => ['x' => 'y']],
    ]);

    WorkflowEdge::factory()->create([
        'workflow_id' => $workflow->id,
        'source_node_id' => $trigger->id,
        'target_node_id' => $nodeA->id,
    ]);
    WorkflowEdge::factory()->create([
        'workflow_id' => $workflow->id,
        'source_node_id' => $nodeA->id,
        'target_node_id' => $nodeB->id,
    ]);
    WorkflowEdge::factory()->create([
        'workflow_id' => $workflow->id,
        'source_node_id' => $nodeB->id,
        'target_node_id' => $nodeA->id,
    ]);

    $errors = $this->validator->errors($workflow);

    expect(collect($errors)->first(fn ($e) => str_contains($e, 'cycle')))->not->toBeNull();
});

it('detects unreachable nodes', function () {
    $workflow = Workflow::factory()->create();

    $trigger = WorkflowNode::factory()->trigger()->create(['workflow_id' => $workflow->id, 'name' => 'trigger']);
    $connected = WorkflowNode::factory()->action('set_fields')->create([
        'workflow_id' => $workflow->id,
        'name' => 'connected',
        'config' => ['fields' => ['x' => 'y']],
    ]);
    $orphan = WorkflowNode::factory()->action('set_fields')->create([
        'workflow_id' => $workflow->id,
        'name' => 'orphan',
        'config' => ['fields' => ['x' => 'y']],
    ]);

    WorkflowEdge::factory()->create([
        'workflow_id' => $workflow->id,
        'source_node_id' => $trigger->id,
        'target_node_id' => $connected->id,
    ]);

    $errors = $this->validator->errors($workflow);

    expect(collect($errors)->first(fn ($e) => str_contains($e, 'unreachable')))->not->toBeNull();
});

it('returns empty errors for valid workflow', function () {
    $workflow = Workflow::factory()->create();

    $trigger = WorkflowNode::factory()->trigger()->create(['workflow_id' => $workflow->id, 'name' => 'trigger']);
    $action = WorkflowNode::factory()->action('set_fields')->create([
        'workflow_id' => $workflow->id,
        'name' => 'action',
        'config' => ['fields' => ['status' => 'done']],
    ]);

    WorkflowEdge::factory()->create([
        'workflow_id' => $workflow->id,
        'source_node_id' => $trigger->id,
        'target_node_id' => $action->id,
    ]);

    expect($this->validator->errors($workflow))->toBeEmpty();
});
