<?php

declare(strict_types=1);

use Aftandilmmd\WorkflowAutomation\DTOs\ExecutionContext;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeInput;
use Aftandilmmd\WorkflowAutomation\Nodes\Actions\RunCommandAction;

beforeEach(function () {
    $this->node = new RunCommandAction;
    $this->context = new ExecutionContext(workflowRunId: 1, workflowId: 1);
});

it('executes an artisan command successfully', function () {
    $input = new NodeInput(
        items: [['task' => 'clear cache']],
        context: $this->context,
    );

    $output = $this->node->execute($input, [
        'command_type' => 'artisan',
        'command' => 'help',
    ]);

    expect($output->items())->toHaveCount(1);
    expect($output->items()[0]['command_result']['success'])->toBeTrue();
    expect($output->items()[0]['command_result']['exit_code'])->toBe(0);
});

it('includes output when include_output is true', function () {
    $input = new NodeInput(
        items: [['data' => 'test']],
        context: $this->context,
    );

    $output = $this->node->execute($input, [
        'command_type' => 'artisan',
        'command' => 'help',
        'include_output' => true,
    ]);

    expect($output->items()[0]['command_result'])->toHaveKey('output');
    expect($output->items()[0]['command_result']['output'])->not->toBeEmpty();
});

it('does not include output when include_output is false', function () {
    $input = new NodeInput(
        items: [['data' => 'test']],
        context: $this->context,
    );

    $output = $this->node->execute($input, [
        'command_type' => 'artisan',
        'command' => 'help',
        'include_output' => false,
    ]);

    expect($output->items()[0]['command_result'])->not->toHaveKey('output');
});

it('executes a shell command successfully', function () {
    $input = new NodeInput(
        items: [['data' => 'test']],
        context: $this->context,
    );

    $output = $this->node->execute($input, [
        'command_type' => 'shell',
        'command' => 'echo "hello world"',
        'include_output' => true,
    ]);

    expect($output->items())->toHaveCount(1);
    expect($output->items()[0]['command_result']['success'])->toBeTrue();
    expect($output->items()[0]['command_result']['output'])->toBe('hello world');
});

it('captures shell command exit code on failure', function () {
    $input = new NodeInput(
        items: [['data' => 'test']],
        context: $this->context,
    );

    $output = $this->node->execute($input, [
        'command_type' => 'shell',
        'command' => 'exit 42',
        'include_output' => true,
    ]);

    expect($output->items()[0]['command_result']['exit_code'])->toBe(42);
    expect($output->items()[0]['command_result']['success'])->toBeFalse();
});

it('passes artisan arguments correctly', function () {
    $input = new NodeInput(
        items: [['data' => 'test']],
        context: $this->context,
    );

    $output = $this->node->execute($input, [
        'command_type' => 'artisan',
        'command' => 'help',
        'arguments' => ['command_name' => 'list'],
        'include_output' => true,
    ]);

    expect($output->items()[0]['command_result']['success'])->toBeTrue();
});

it('rejects commands not in the allowed list', function () {
    config()->set('workflow-automation.run_command.allowed_commands', ['cache:clear', 'queue:restart']);

    $input = new NodeInput(
        items: [['data' => 'test']],
        context: $this->context,
    );

    expect(fn () => $this->node->execute($input, [
        'command_type' => 'artisan',
        'command' => 'migrate:fresh',
    ]))->toThrow(RuntimeException::class, 'not in the allowed commands list');
});

it('allows commands matching a wildcard pattern', function () {
    config()->set('workflow-automation.run_command.allowed_commands', ['cache:*']);

    $input = new NodeInput(
        items: [['data' => 'test']],
        context: $this->context,
    );

    $output = $this->node->execute($input, [
        'command_type' => 'artisan',
        'command' => 'cache:clear',
    ]);

    expect($output->items()[0]['command_result']['success'])->toBeTrue();
});

it('allows all commands when allowed list is empty', function () {
    config()->set('workflow-automation.run_command.allowed_commands', []);

    $input = new NodeInput(
        items: [['data' => 'test']],
        context: $this->context,
    );

    $output = $this->node->execute($input, [
        'command_type' => 'artisan',
        'command' => 'help',
    ]);

    expect($output->items()[0]['command_result']['success'])->toBeTrue();
});

it('rejects shell commands when shell is disabled', function () {
    config()->set('workflow-automation.run_command.shell_enabled', false);

    $input = new NodeInput(
        items: [['data' => 'test']],
        context: $this->context,
    );

    expect(fn () => $this->node->execute($input, [
        'command_type' => 'shell',
        'command' => 'echo hello',
    ]))->toThrow(RuntimeException::class, 'Shell commands are disabled');
});

it('routes to error port on command failure exception', function () {
    $input = new NodeInput(
        items: [['data' => 'test']],
        context: $this->context,
    );

    $output = $this->node->execute($input, [
        'command_type' => 'artisan',
        'command' => 'nonexistent:command:that:does:not:exist',
    ]);

    expect($output->items('error'))->toHaveCount(1);
    expect($output->items('error')[0])->toHaveKey('error');
});

it('processes multiple items sequentially', function () {
    $input = new NodeInput(
        items: [
            ['id' => 1],
            ['id' => 2],
            ['id' => 3],
        ],
        context: $this->context,
    );

    $output = $this->node->execute($input, [
        'command_type' => 'artisan',
        'command' => 'help',
    ]);

    expect($output->items())->toHaveCount(3);
    expect($output->items()[0]['id'])->toBe(1);
    expect($output->items()[1]['id'])->toBe(2);
    expect($output->items()[2]['id'])->toBe(3);
});

it('preserves original item data in output', function () {
    $input = new NodeInput(
        items: [['name' => 'Alice', 'email' => 'alice@example.com']],
        context: $this->context,
    );

    $output = $this->node->execute($input, [
        'command_type' => 'artisan',
        'command' => 'help',
    ]);

    expect($output->items()[0]['name'])->toBe('Alice');
    expect($output->items()[0]['email'])->toBe('alice@example.com');
    expect($output->items()[0])->toHaveKey('command_result');
});
