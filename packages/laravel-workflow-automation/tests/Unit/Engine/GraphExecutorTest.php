<?php

declare(strict_types=1);

use Aftandilmmd\WorkflowAutomation\Engine\GraphExecutor;
use Aftandilmmd\WorkflowAutomation\Enums\NodeType;
use Aftandilmmd\WorkflowAutomation\Enums\RunStatus;
use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowEdge;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowNode;

beforeEach(function () {
    $this->executor = app(GraphExecutor::class);
});

it('executes a simple trigger → action workflow', function () {
    $workflow = Workflow::factory()->active()->create();

    $trigger = WorkflowNode::factory()->trigger()->create([
        'workflow_id' => $workflow->id,
        'name' => 'trigger',
    ]);

    $action = WorkflowNode::factory()->create([
        'workflow_id' => $workflow->id,
        'type' => NodeType::Transformer,
        'node_key' => 'set_fields',
        'name' => 'set_status',
        'config' => ['fields' => ['status' => 'processed'], 'keep_existing' => true],
    ]);

    WorkflowEdge::factory()->create([
        'workflow_id' => $workflow->id,
        'source_node_id' => $trigger->id,
        'target_node_id' => $action->id,
    ]);

    $run = $this->executor->execute($workflow, [['name' => 'Alice']]);

    expect($run->status)->toBe(RunStatus::Completed);

    $nodeRuns = $run->nodeRuns;
    expect($nodeRuns)->toHaveCount(2);
});

it('executes a branching workflow with if_condition', function () {
    $workflow = Workflow::factory()->active()->create();

    $trigger = WorkflowNode::factory()->trigger()->create([
        'workflow_id' => $workflow->id,
        'name' => 'trigger',
    ]);

    $condition = WorkflowNode::factory()->condition()->create([
        'workflow_id' => $workflow->id,
        'name' => 'check_age',
        'config' => ['field' => 'age', 'operator' => 'greater_or_equal', 'value' => 18],
    ]);

    $trueNode = WorkflowNode::factory()->create([
        'workflow_id' => $workflow->id,
        'type' => NodeType::Transformer,
        'node_key' => 'set_fields',
        'name' => 'adult',
        'config' => ['fields' => ['label' => 'adult']],
    ]);

    $falseNode = WorkflowNode::factory()->create([
        'workflow_id' => $workflow->id,
        'type' => NodeType::Transformer,
        'node_key' => 'set_fields',
        'name' => 'minor',
        'config' => ['fields' => ['label' => 'minor']],
    ]);

    WorkflowEdge::factory()->create([
        'workflow_id' => $workflow->id,
        'source_node_id' => $trigger->id,
        'target_node_id' => $condition->id,
    ]);

    WorkflowEdge::factory()->create([
        'workflow_id' => $workflow->id,
        'source_node_id' => $condition->id,
        'source_port' => 'true',
        'target_node_id' => $trueNode->id,
    ]);

    WorkflowEdge::factory()->create([
        'workflow_id' => $workflow->id,
        'source_node_id' => $condition->id,
        'source_port' => 'false',
        'target_node_id' => $falseNode->id,
    ]);

    $run = $this->executor->execute($workflow, [['age' => 25]]);

    expect($run->status)->toBe(RunStatus::Completed);

    // trigger + condition + true branch executed = 3 node runs
    // false branch has empty items so it won't execute
    $nodeRuns = $run->nodeRuns;
    expect($nodeRuns)->toHaveCount(3);
});

it('marks run as failed when validation fails', function () {
    $workflow = Workflow::factory()->create();

    // No trigger node — validation should fail
    WorkflowNode::factory()->action('set_fields')->create([
        'workflow_id' => $workflow->id,
        'name' => 'orphan',
        'config' => ['fields' => ['x' => 'y']],
    ]);

    $run = $this->executor->execute($workflow, [['data' => 'test']]);

    expect($run->status)->toBe(RunStatus::Failed);
    expect($run->error_message)->not->toBeNull();
});
