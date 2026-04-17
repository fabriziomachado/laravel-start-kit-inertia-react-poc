<?php

declare(strict_types=1);

use Aftandilmmd\WorkflowAutomation\AiBuilder\WorkflowBuilderAgent;
use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use Aftandilmmd\WorkflowAutomation\Registry\NodeRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('expõe nomes de classe únicos para as tools do AI builder (OpenAI strict)', function (): void {
    $workflow = Workflow::query()->create([
        'name' => 'AI Builder Tool Test',
        'description' => null,
        'is_active' => false,
        'run_async' => true,
    ]);
    $registry = app(NodeRegistry::class);
    $agent = new WorkflowBuilderAgent($workflow, $registry);

    $tools = iterator_to_array($agent->tools());
    $basenames = array_map(fn ($tool) => class_basename($tool), $tools);

    expect($basenames)->not->toBeEmpty()
        ->and(count($basenames))->toBe(count(array_unique($basenames)));
});
