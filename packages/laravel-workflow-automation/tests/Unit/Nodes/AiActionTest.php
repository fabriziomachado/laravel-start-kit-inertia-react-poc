<?php

declare(strict_types=1);

use Aftandilmmd\WorkflowAutomation\DTOs\ExecutionContext;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeInput;
use Aftandilmmd\WorkflowAutomation\Nodes\Actions\AiAction;

beforeEach(function () {
    $this->context = new ExecutionContext(workflowRunId: 1, workflowId: 1);
});

function createMockAiNode(array $mockResponse): AiAction
{
    $node = Mockery::mock(AiAction::class)->makePartial();
    $node->shouldAllowMockingProtectedMethods();
    $node->shouldReceive('callAi')->andReturn($mockResponse);
    $node->shouldReceive('ensureLaravelAiInstalled')->andReturnNull();

    return $node;
}

it('returns ai response text in the configured output key', function () {
    $node = createMockAiNode([
        'text' => 'Paris is the capital of France.',
        'usage' => ['input_tokens' => 10, 'output_tokens' => 8],
    ]);

    $input = new NodeInput(
        items: [['question' => 'What is the capital of France?']],
        context: $this->context,
    );

    $output = $node->execute($input, [
        'prompt' => 'Answer: {{ item.question }}',
    ]);

    expect($output->items())->toHaveCount(1);
    expect($output->items()[0]['ai_response'])->toBe('Paris is the capital of France.');
    expect($output->items()[0]['ai_usage']['input_tokens'])->toBe(10);
    expect($output->items()[0]['ai_usage']['output_tokens'])->toBe(8);
});

it('uses custom output_key when provided', function () {
    $node = createMockAiNode([
        'text' => 'Positive sentiment',
        'usage' => ['input_tokens' => 5, 'output_tokens' => 3],
    ]);

    $input = new NodeInput(
        items: [['review' => 'Great product!']],
        context: $this->context,
    );

    $output = $node->execute($input, [
        'prompt' => 'Classify sentiment: {{ item.review }}',
        'output_key' => 'sentiment',
    ]);

    expect($output->items()[0])->toHaveKey('sentiment');
    expect($output->items()[0]['sentiment'])->toBe('Positive sentiment');
    expect($output->items()[0])->not->toHaveKey('ai_response');
});

it('preserves original item data in output', function () {
    $node = createMockAiNode([
        'text' => 'Summary here',
        'usage' => ['input_tokens' => 20, 'output_tokens' => 10],
    ]);

    $input = new NodeInput(
        items: [['name' => 'Alice', 'email' => 'alice@example.com', 'text' => 'Long article...']],
        context: $this->context,
    );

    $output = $node->execute($input, [
        'prompt' => 'Summarize: {{ item.text }}',
    ]);

    expect($output->items()[0]['name'])->toBe('Alice');
    expect($output->items()[0]['email'])->toBe('alice@example.com');
    expect($output->items()[0]['text'])->toBe('Long article...');
    expect($output->items()[0])->toHaveKey('ai_response');
});

it('processes multiple items sequentially', function () {
    $responses = [
        ['text' => 'Response 1', 'usage' => ['input_tokens' => 5, 'output_tokens' => 3]],
        ['text' => 'Response 2', 'usage' => ['input_tokens' => 5, 'output_tokens' => 3]],
        ['text' => 'Response 3', 'usage' => ['input_tokens' => 5, 'output_tokens' => 3]],
    ];

    $node = Mockery::mock(AiAction::class)->makePartial();
    $node->shouldAllowMockingProtectedMethods();
    $node->shouldReceive('ensureLaravelAiInstalled')->andReturnNull();
    $node->shouldReceive('callAi')
        ->andReturn($responses[0], $responses[1], $responses[2]);

    $input = new NodeInput(
        items: [['id' => 1], ['id' => 2], ['id' => 3]],
        context: $this->context,
    );

    $output = $node->execute($input, [
        'prompt' => 'Process item {{ item.id }}',
    ]);

    expect($output->items())->toHaveCount(3);
    expect($output->items()[0]['ai_response'])->toBe('Response 1');
    expect($output->items()[1]['ai_response'])->toBe('Response 2');
    expect($output->items()[2]['ai_response'])->toBe('Response 3');
});

it('routes to error port when ai call fails', function () {
    $node = Mockery::mock(AiAction::class)->makePartial();
    $node->shouldAllowMockingProtectedMethods();
    $node->shouldReceive('ensureLaravelAiInstalled')->andReturnNull();
    $node->shouldReceive('callAi')
        ->andThrow(new RuntimeException('API rate limit exceeded'));

    $input = new NodeInput(
        items: [['text' => 'Summarize this']],
        context: $this->context,
    );

    $output = $node->execute($input, [
        'prompt' => '{{ item.text }}',
    ]);

    expect($output->items('error'))->toHaveCount(1);
    expect($output->items('error')[0]['error'])->toBe('API rate limit exceeded');
    expect($output->items('error')[0]['text'])->toBe('Summarize this');
});

it('routes partially processed items to main and failed item to error', function () {
    $callCount = 0;
    $node = Mockery::mock(AiAction::class)->makePartial();
    $node->shouldAllowMockingProtectedMethods();
    $node->shouldReceive('ensureLaravelAiInstalled')->andReturnNull();
    $node->shouldReceive('callAi')
        ->andReturnUsing(function () use (&$callCount) {
            $callCount++;
            if ($callCount === 1) {
                return ['text' => 'OK', 'usage' => ['input_tokens' => 5, 'output_tokens' => 3]];
            }
            throw new RuntimeException('Timeout');
        });

    $input = new NodeInput(
        items: [['id' => 1], ['id' => 2]],
        context: $this->context,
    );

    $output = $node->execute($input, [
        'prompt' => 'Process {{ item.id }}',
    ]);

    expect($output->items('main'))->toHaveCount(1);
    expect($output->items('main')[0]['id'])->toBe(1);
    expect($output->items('error'))->toHaveCount(1);
    expect($output->items('error')[0]['id'])->toBe(2);
});

it('throws when laravel/ai is not installed', function () {
    $node = new AiAction;

    $input = new NodeInput(
        items: [['text' => 'test']],
        context: $this->context,
    );

    expect(fn () => $node->execute($input, [
        'prompt' => 'test',
    ]))->toThrow(RuntimeException::class, 'laravel/ai');
});

it('resolves valid provider names', function () {
    $node = new AiAction;
    $method = new ReflectionMethod($node, 'resolveProvider');

    expect($method->invoke($node, 'openai'))->toBe(Laravel\Ai\Enums\Lab::OpenAI);
    expect($method->invoke($node, 'anthropic'))->toBe(Laravel\Ai\Enums\Lab::Anthropic);
    expect($method->invoke($node, 'OpenAI'))->toBe(Laravel\Ai\Enums\Lab::OpenAI);
})->skip(! class_exists(Laravel\Ai\Enums\Lab::class), 'laravel/ai not installed');

it('throws for unknown provider name', function () {
    $node = new AiAction;
    $method = new ReflectionMethod($node, 'resolveProvider');

    expect(fn () => $method->invoke($node, 'unknown_provider'))
        ->toThrow(InvalidArgumentException::class, 'Unknown AI provider');
})->skip(! class_exists(Laravel\Ai\Enums\Lab::class), 'laravel/ai not installed');

it('throws runtime exception when resolving provider without laravel/ai', function () {
    $node = new AiAction;
    $method = new ReflectionMethod($node, 'resolveProvider');

    expect(fn () => $method->invoke($node, 'openai'))
        ->toThrow(RuntimeException::class, 'laravel/ai');
})->skip(class_exists(Laravel\Ai\Enums\Lab::class), 'laravel/ai is installed');

it('uses default output_key ai_response when not specified', function () {
    $node = createMockAiNode([
        'text' => 'Hello',
        'usage' => ['input_tokens' => 3, 'output_tokens' => 1],
    ]);

    $input = new NodeInput(
        items: [['data' => 'test']],
        context: $this->context,
    );

    $output = $node->execute($input, [
        'prompt' => 'Say hello',
    ]);

    expect($output->items()[0])->toHaveKey('ai_response');
    expect($output->items()[0])->toHaveKey('ai_usage');
});

it('returns correct config schema', function () {
    $schema = AiAction::configSchema();

    $keys = array_column($schema, 'key');

    expect($keys)->toContain('prompt');
    expect($keys)->toContain('system_prompt');
    expect($keys)->toContain('provider');
    expect($keys)->toContain('model');
    expect($keys)->toContain('temperature');
    expect($keys)->toContain('max_tokens');
    expect($keys)->toContain('output_key');

    $promptField = collect($schema)->firstWhere('key', 'prompt');
    expect($promptField['required'])->toBeTrue();
    expect($promptField['supports_expression'])->toBeTrue();
});
