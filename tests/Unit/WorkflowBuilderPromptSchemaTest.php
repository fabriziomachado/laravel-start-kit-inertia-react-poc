<?php

declare(strict_types=1);

use Aftandilmmd\WorkflowAutomation\Mcp\Prompts\WorkflowBuilderPrompt;

it('formata options select com pares value e label', function (): void {
    $method = (new ReflectionClass(WorkflowBuilderPrompt::class))->getMethod('formatConfigSchema');
    $schema = [
        [
            'key' => 'trigger_on',
            'type' => 'select',
            'label' => 'Trigger When',
            'required' => true,
            'options' => [
                ['value' => 'completed', 'label' => 'Completed'],
                ['value' => 'failed', 'label' => 'Failed'],
            ],
        ],
    ];

    $out = $method->invoke(null, $schema);

    expect($out)->toContain('completed')
        ->and($out)->toContain('failed')
        ->and($out)->not->toContain('Array');
});

it('formata options select com lista de strings', function (): void {
    $method = (new ReflectionClass(WorkflowBuilderPrompt::class))->getMethod('formatConfigSchema');
    $schema = [
        [
            'key' => 'method',
            'type' => 'select',
            'label' => 'Method',
            'required' => true,
            'options' => ['GET', 'POST'],
        ],
    ];

    $out = $method->invoke(null, $schema);

    expect($out)->toBe('`method` (select, required, options: GET|POST, Method)');
});
