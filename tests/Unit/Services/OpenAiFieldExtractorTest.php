<?php

declare(strict_types=1);

use App\Services\Workflow\OpenAiFieldExtractor;
use Illuminate\Support\Facades\Http;

it('retorna vazio quando indisponível', function (): void {
    config()->set('services.openai.api_key', null);
    $svc = new OpenAiFieldExtractor;

    expect($svc->extract([['key' => 'x']], 'txt'))->toBe([]);
});

it('ignora fields inválidos ao montar schema e filtra output por keys', function (): void {
    config()->set('services.openai.api_key', 'test-key');
    config()->set('services.openai.timeout', 10);

    Http::fake([
        'https://api.openai.com/v1/chat/completions' => Http::response([
            'choices' => [
                ['message' => ['content' => '{"name":"Ana","extra":"x"}']],
            ],
        ], 200),
    ]);

    $svc = new OpenAiFieldExtractor;
    $out = $svc->extract([
        ['nope' => true],
        ['key' => 'name', 'label' => 'Nome', 'type' => 'string', 'required' => true],
    ], 'Nome: Ana');

    expect($out)->toBe(['name' => 'Ana']);
});

it('lança exceção quando http falha', function (): void {
    config()->set('services.openai.api_key', 'test-key');
    config()->set('services.openai.timeout', 10);

    Http::fake([
        'https://api.openai.com/v1/chat/completions' => Http::response(['err' => true], 500),
    ]);

    $svc = new OpenAiFieldExtractor;
    expect(fn () => $svc->extract([['key' => 'x']], 't'))->toThrow(RuntimeException::class);
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

    $svc = new OpenAiFieldExtractor;
    expect(fn () => $svc->extract([['key' => 'x']], 't'))->toThrow(RuntimeException::class);
});

