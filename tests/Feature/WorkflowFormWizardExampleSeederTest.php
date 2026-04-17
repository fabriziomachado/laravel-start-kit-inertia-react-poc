<?php

declare(strict_types=1);

use Aftandilmmd\WorkflowAutomation\Engine\GraphExecutor;
use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use Database\Seeders\WorkflowFormWizardExampleSeeder;

it('cria o workflow exemplo com manual, três form_step e set_fields', function (): void {
    $this->seed(WorkflowFormWizardExampleSeeder::class);

    $workflow = Workflow::query()->where('name', WorkflowFormWizardExampleSeeder::WORKFLOW_NAME)->first();

    expect($workflow)->not->toBeNull()
        ->and($workflow->is_active)->toBeTrue();

    $nodes = $workflow->nodes()->orderBy('id')->get();
    expect($nodes)->toHaveCount(5)
        ->and($nodes[0]->node_key)->toBe('manual')
        ->and($nodes[1]->node_key)->toBe('form_step')
        ->and($nodes[2]->node_key)->toBe('form_step')
        ->and($nodes[3]->node_key)->toBe('form_step')
        ->and($nodes[4]->node_key)->toBe('set_fields');

    expect($workflow->edges()->count())->toBe(4);
});

it('pode voltar a correr o seeder após existirem execuções do fluxo (recria o grafo com Ingresso)', function (): void {
    $this->seed(WorkflowFormWizardExampleSeeder::class);

    $workflow = Workflow::query()->where('name', WorkflowFormWizardExampleSeeder::WORKFLOW_NAME)->firstOrFail();
    app(GraphExecutor::class)->execute($workflow, [[]]);

    $this->seed(WorkflowFormWizardExampleSeeder::class);

    $again = Workflow::query()->where('name', WorkflowFormWizardExampleSeeder::WORKFLOW_NAME)->firstOrFail();
    $edgePairs = $again->edges()->get()->map(function ($e) use ($again): string {
        $s = $again->nodes()->where('id', $e->source_node_id)->value('name');
        $t = $again->nodes()->where('id', $e->target_node_id)->value('name');

        return "{$s}->{$t}";
    })->all();

    expect($edgePairs)->toContain('Passo1->Ingresso')->toContain('Ingresso->Passo2');
});
