<?php

declare(strict_types=1);

use Aftandilmmd\WorkflowAutomation\Mcp\Tools\AddNodeTool;
use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowNode;
use Aftandilmmd\WorkflowAutomation\Services\WorkflowService;
use Laravel\Mcp\Request;

it('aceita config como array ao adicionar nó', function (): void {
    $workflow = Workflow::query()->create([
        'name' => 'Test',
        'description' => null,
        'is_active' => false,
        'run_async' => true,
    ]);

    $tool = new AddNodeTool(app(WorkflowService::class));
    $response = $tool->handle(new Request([
        'workflow_id' => $workflow->id,
        'node_key' => 'manual',
        'name' => 'Gatilho',
        'config' => [],
    ]));

    expect($response->content())->not->toBeEmpty();
    expect(WorkflowNode::query()->where('workflow_id', $workflow->id)->count())->toBe(1);
});

it('aceita config como string JSON', function (): void {
    $workflow = Workflow::query()->create([
        'name' => 'Test 2',
        'description' => null,
        'is_active' => false,
        'run_async' => true,
    ]);

    $tool = new AddNodeTool(app(WorkflowService::class));
    $response = $tool->handle(new Request([
        'workflow_id' => $workflow->id,
        'node_key' => 'manual',
        'name' => 'Gatilho 2',
        'config' => '{}',
    ]));

    expect($response->content())->not->toBeEmpty();
    expect(WorkflowNode::query()->where('workflow_id', $workflow->id)->count())->toBe(1);
});
