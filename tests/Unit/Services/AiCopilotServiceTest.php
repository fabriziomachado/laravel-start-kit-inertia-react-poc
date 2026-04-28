<?php

declare(strict_types=1);

use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowRun;
use App\Services\Workflow\AiCopilotService;
use Illuminate\Support\Facades\Http;

it('lança exceção quando indisponível', function (): void {
    config()->set('services.openai.api_key', null);
    $svc = new AiCopilotService;

    $run = WorkflowRun::factory()->create(['context' => []]);
    expect(fn () => $svc->answer($run, 'oi'))->toThrow(RuntimeException::class);
});

it('lança exceção quando resposta HTTP falha', function (): void {
    config()->set('services.openai.api_key', 'test-key');
    config()->set('services.openai.timeout', 10);

    Http::fake([
        'https://api.openai.com/v1/chat/completions' => Http::response(['err' => true], 500),
    ]);

    $svc = new AiCopilotService;
    $run = WorkflowRun::factory()->create(['context' => []]);

    expect(fn () => $svc->answer($run, 'oi'))->toThrow(RuntimeException::class);
});

it('lança exceção quando content não é string', function (): void {
    config()->set('services.openai.api_key', 'test-key');
    config()->set('services.openai.timeout', 10);

    Http::fake([
        'https://api.openai.com/v1/chat/completions' => Http::response([
            'choices' => [
                ['message' => ['content' => null]],
            ],
        ], 200),
    ]);

    $svc = new AiCopilotService;
    $run = WorkflowRun::factory()->create(['context' => []]);

    expect(fn () => $svc->answer($run, 'oi'))->toThrow(RuntimeException::class);
});

it('trunca context grande e retorna resposta', function (): void {
    config()->set('services.openai.api_key', 'test-key');
    config()->set('services.openai.timeout', 10);

    Http::fake([
        'https://api.openai.com/v1/chat/completions' => Http::response([
            'choices' => [
                ['message' => ['content' => "ok\n"]],
            ],
        ], 200),
    ]);

    $svc = new AiCopilotService;
    $run = WorkflowRun::factory()->create([
        'context' => ['big' => str_repeat('a', 7000)],
    ]);
    $run->workflow()->associate(Workflow::factory()->create(['name' => 'Fluxo']))->save();

    expect($svc->answer($run, 'oi'))->toBe('ok');
});

