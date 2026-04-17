<?php

declare(strict_types=1);

use Aftandilmmd\WorkflowAutomation\Engine\GraphExecutor;
use Aftandilmmd\WorkflowAutomation\Enums\RunStatus;
use Aftandilmmd\WorkflowAutomation\Models\Workflow;

it('coloca o run em waiting e grava resume_token no output do node', function (): void {
    $workflow = Workflow::query()->create([
        'name' => 'Teste form_step mínimo',
        'description' => null,
        'is_active' => true,
    ]);

    $trigger = $workflow->addNode('T', 'manual', []);
    $form = $workflow->addNode('F', 'form_step', [
        'title' => 'Um passo',
        'submit_label' => 'OK',
        'fields' => [
            ['key' => 'note', 'label' => 'Nota', 'type' => 'string', 'required' => false],
        ],
    ]);
    $trigger->connect($form);

    $run = app(GraphExecutor::class)->execute($workflow, [[]]);

    expect($run->status)->toBe(RunStatus::Waiting);

    $nodeRun = $run->nodeRuns()->orderByDesc('id')->first();
    expect($nodeRun)->not->toBeNull()
        ->and($nodeRun->output['main'][0]['resume_token'] ?? null)->toBeString()
        ->and($nodeRun->output['main'][0]['title'] ?? null)->toBe('Um passo');
});
