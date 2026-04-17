<?php

declare(strict_types=1);

use Aftandilmmd\WorkflowAutomation\Contracts\ExpressionEvaluatorInterface;
use Aftandilmmd\WorkflowAutomation\DTOs\ExecutionContext;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeInput;
use Aftandilmmd\WorkflowAutomation\Nodes\Utilities\CodeUtility;

beforeEach(function () {
    $this->evaluator = app(ExpressionEvaluatorInterface::class);
    $this->node = new CodeUtility($this->evaluator);
    $this->context = new ExecutionContext(workflowRunId: 1, workflowId: 1);
});

it('transforms items using expression', function () {
    $input = new NodeInput(
        items: [['price' => 10, 'qty' => 3]],
        context: $this->context,
    );

    $output = $this->node->execute($input, [
        'mode' => 'transform',
        'expression' => '{{ item.price * item.qty }}',
    ]);

    expect($output->items())->toHaveCount(1);
    expect($output->items()[0]['_result'])->toBe(30);
});

it('filters items using expression', function () {
    $input = new NodeInput(
        items: [['amount' => 50], ['amount' => 150], ['amount' => 80]],
        context: $this->context,
    );

    $output = $this->node->execute($input, [
        'mode' => 'filter',
        'expression' => '{{ item.amount > 100 }}',
    ]);

    expect($output->items())->toHaveCount(1);
    expect($output->items()[0]['amount'])->toBe(150);
});

it('routes to error port on expression failure', function () {
    $input = new NodeInput(
        items: [['data' => 'test']],
        context: $this->context,
    );

    $output = $this->node->execute($input, [
        'mode' => 'transform',
        'expression' => '{{ unknown_func() }}',
    ]);

    expect($output->items('error'))->toHaveCount(1);
    expect($output->items('error')[0])->toHaveKey('error');
});
