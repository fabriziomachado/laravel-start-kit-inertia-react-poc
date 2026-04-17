<?php

declare(strict_types=1);

use Aftandilmmd\WorkflowAutomation\Contracts\NodeInterface;
use Aftandilmmd\WorkflowAutomation\DTOs\ExecutionContext;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeInput;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeOutput;
use Aftandilmmd\WorkflowAutomation\Engine\NodeRunner;

beforeEach(function () {
    $this->runner = new NodeRunner;
    $this->context = new ExecutionContext(workflowRunId: 1, workflowId: 1);
});

it('runs a node successfully', function () {
    $node = Mockery::mock(NodeInterface::class);
    $node->shouldReceive('execute')->once()->andReturn(NodeOutput::main([['result' => 'ok']]));
    $node->shouldReceive('outputPorts')->andReturn(['main']);

    $input = new NodeInput(items: [['data' => 'test']], context: $this->context);

    $output = $this->runner->run($node, $input, []);

    expect($output->items('main'))->toHaveCount(1);
    expect($output->items('main')[0]['result'])->toBe('ok');
});

it('retries on failure and succeeds', function () {
    $node = Mockery::mock(NodeInterface::class);
    $node->shouldReceive('execute')
        ->times(2)
        ->andThrow(new RuntimeException('fail'))
        ->andReturn(NodeOutput::main([['ok' => true]]));
    $node->shouldReceive('outputPorts')->andReturn(['main']);

    $input = new NodeInput(items: [[]], context: $this->context);

    $output = $this->runner->run($node, $input, [], maxRetries: 1, retryDelayMs: 1);

    expect($output->items('main'))->toHaveCount(1);
});

it('routes to error port when node has one and all retries exhausted', function () {
    $node = Mockery::mock(NodeInterface::class);
    $node->shouldReceive('execute')->once()->andThrow(new RuntimeException('boom'));
    $node->shouldReceive('outputPorts')->andReturn(['main', 'error']);

    $input = new NodeInput(items: [['value' => 1]], context: $this->context);

    $output = $this->runner->run($node, $input, [], maxRetries: 0, retryDelayMs: 1);

    expect($output->items('error'))->toHaveCount(1);
    expect($output->items('error')[0]['error'])->toBe('boom');
    expect($output->items('main'))->toBeEmpty();
});

it('throws when no error port and all retries exhausted', function () {
    $node = Mockery::mock(NodeInterface::class);
    $node->shouldReceive('execute')->andThrow(new RuntimeException('fatal'));
    $node->shouldReceive('outputPorts')->andReturn(['main']);

    $input = new NodeInput(items: [[]], context: $this->context);

    $this->runner->run($node, $input, [], maxRetries: 0, retryDelayMs: 1);
})->throws(RuntimeException::class, 'fatal');
