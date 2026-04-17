<?php

declare(strict_types=1);

use Aftandilmmd\WorkflowAutomation\DTOs\ExecutionContext;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeInput;
use Aftandilmmd\WorkflowAutomation\Nodes\Actions\SendMailAction;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->node = new SendMailAction;
    $this->context = new ExecutionContext(workflowRunId: 1, workflowId: 1);
    Mail::fake();
});

it('sends a plain text email in inline mode', function () {
    $input = new NodeInput(
        items: [['name' => 'Alice']],
        context: $this->context,
    );

    $output = $this->node->execute($input, [
        'send_mode' => 'inline',
        'to' => 'alice@example.com',
        'subject' => 'Hello',
        'body' => 'Hi Alice',
    ]);

    expect($output->items())->toHaveCount(1);
    expect($output->items()[0]['mail_sent'])->toBeTrue();
    expect($output->items()[0]['name'])->toBe('Alice');
});

it('defaults to inline mode when send_mode is not set (backward compat)', function () {
    $input = new NodeInput(
        items: [['id' => 1]],
        context: $this->context,
    );

    $output = $this->node->execute($input, [
        'to' => 'test@example.com',
        'subject' => 'Test',
        'body' => 'Hello',
    ]);

    expect($output->items())->toHaveCount(1);
    expect($output->items()[0]['mail_sent'])->toBeTrue();
});

it('sends to multiple comma-separated recipients', function () {
    $input = new NodeInput(
        items: [['id' => 1]],
        context: $this->context,
    );

    $output = $this->node->execute($input, [
        'send_mode' => 'inline',
        'to' => 'alice@example.com, bob@example.com',
        'subject' => 'Team update',
        'body' => 'Hello team',
    ]);

    expect($output->items())->toHaveCount(1);
    expect($output->items()[0]['mail_sent'])->toBeTrue();
});

it('processes multiple items sequentially', function () {
    $input = new NodeInput(
        items: [
            ['id' => 1, 'email' => 'a@test.com'],
            ['id' => 2, 'email' => 'b@test.com'],
        ],
        context: $this->context,
    );

    $output = $this->node->execute($input, [
        'send_mode' => 'inline',
        'to' => 'admin@example.com',
        'subject' => 'Notification',
        'body' => 'Item processed',
    ]);

    expect($output->items())->toHaveCount(2);
    expect($output->items()[0]['id'])->toBe(1);
    expect($output->items()[1]['id'])->toBe(2);
});

it('sends via mailable class', function () {
    $input = new NodeInput(
        items: [['name' => 'Alice', 'email' => 'alice@example.com']],
        context: $this->context,
    );

    $output = $this->node->execute($input, [
        'send_mode' => 'mailable',
        'mailable_class' => SendMailTestMailable::class,
        'mailable_to' => 'alice@example.com',
    ]);

    expect($output->items())->toHaveCount(1);
    expect($output->items()[0]['mail_sent'])->toBeTrue();

    Mail::assertSent(SendMailTestMailable::class);
});

it('routes to error port when mailable class does not exist', function () {
    $input = new NodeInput(
        items: [['id' => 1]],
        context: $this->context,
    );

    $output = $this->node->execute($input, [
        'send_mode' => 'mailable',
        'mailable_class' => 'App\\Mail\\NonExistentMailable',
        'mailable_to' => 'test@example.com',
    ]);

    expect($output->items('error'))->toHaveCount(1);
    expect($output->items('error')[0]['error'])->toContain('Mailable class not found');
});

it('routes to error port when mailable_to is empty', function () {
    $input = new NodeInput(
        items: [['id' => 1]],
        context: $this->context,
    );

    $output = $this->node->execute($input, [
        'send_mode' => 'mailable',
        'mailable_class' => SendMailTestMailable::class,
        'mailable_to' => '',
    ]);

    expect($output->items('error'))->toHaveCount(1);
    expect($output->items('error')[0]['error'])->toContain('mailable_to is required');
});

it('routes to error port on send failure', function () {
    Mail::shouldReceive('raw')->andThrow(new RuntimeException('SMTP connection failed'));

    $input = new NodeInput(
        items: [['id' => 1]],
        context: $this->context,
    );

    $output = $this->node->execute($input, [
        'send_mode' => 'inline',
        'to' => 'test@example.com',
        'subject' => 'Test',
        'body' => 'Hello',
    ]);

    expect($output->items('error'))->toHaveCount(1);
    expect($output->items('error')[0]['error'])->toBe('SMTP connection failed');
    expect($output->items())->toBeEmpty();
});

it('preserves original item data in output', function () {
    $input = new NodeInput(
        items: [['name' => 'Alice', 'order_id' => 42]],
        context: $this->context,
    );

    $output = $this->node->execute($input, [
        'send_mode' => 'inline',
        'to' => 'alice@example.com',
        'subject' => 'Order',
        'body' => 'Done',
    ]);

    expect($output->items()[0]['name'])->toBe('Alice');
    expect($output->items()[0]['order_id'])->toBe(42);
    expect($output->items()[0]['mail_sent'])->toBeTrue();
});

it('has correct config schema with show_when fields', function () {
    $schema = SendMailAction::configSchema();

    $sendMode = collect($schema)->firstWhere('key', 'send_mode');
    expect($sendMode['type'])->toBe('select');
    expect($sendMode['options'])->toBe(['inline', 'mailable']);

    $inlineFields = collect($schema)->filter(fn ($f) => ($f['show_when']['value'] ?? null) === 'inline');
    expect($inlineFields)->toHaveCount(9);

    $mailableFields = collect($schema)->filter(fn ($f) => ($f['show_when']['value'] ?? null) === 'mailable');
    expect($mailableFields)->toHaveCount(2);
});

// Test Mailable used in mailable mode tests
final class SendMailTestMailable extends Mailable
{
    public function __construct(public array $item) {}

    public function build(): self
    {
        return $this->subject('Test')->html('<p>Test</p>');
    }
}
