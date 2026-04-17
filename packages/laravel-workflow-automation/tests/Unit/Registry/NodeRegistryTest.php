<?php

declare(strict_types=1);

use Aftandilmmd\WorkflowAutomation\Contracts\NodeInterface;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeInput;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeOutput;
use Aftandilmmd\WorkflowAutomation\Enums\NodeType;
use Aftandilmmd\WorkflowAutomation\Exceptions\NodeNotFoundException;
use Aftandilmmd\WorkflowAutomation\Registry\NodeRegistry;

beforeEach(function () {
    $this->registry = app(NodeRegistry::class);
});

it('auto-discovers built-in nodes', function () {
    expect($this->registry->has('manual'))->toBeTrue();
    expect($this->registry->has('if_condition'))->toBeTrue();
    expect($this->registry->has('set_fields'))->toBeTrue();
    expect($this->registry->has('loop'))->toBeTrue();
    expect($this->registry->has('filter'))->toBeTrue();
    expect($this->registry->has('webhook'))->toBeTrue();
});

it('resolves a node to a NodeInterface instance', function () {
    $node = $this->registry->resolve('manual');

    expect($node)->toBeInstanceOf(NodeInterface::class);
});

it('throws on unknown node key', function () {
    $this->registry->resolve('does_not_exist');
})->throws(NodeNotFoundException::class);

it('returns all registered nodes with metadata', function () {
    $all = $this->registry->all();

    expect($all)->toBeArray();
    expect(count($all))->toBeGreaterThanOrEqual(10);

    $manual = collect($all)->firstWhere('key', 'manual');
    expect($manual)->not->toBeNull();
    expect($manual['type'])->toBe('trigger');
    expect($manual['input_ports'])->toBe([]);
    expect($manual['output_ports'])->toContain('main');
    expect($manual)->toHaveKey('config_schema');
});

it('filters by node type', function () {
    $triggers = $this->registry->ofType(NodeType::Trigger);

    expect(count($triggers))->toBeGreaterThanOrEqual(3);
    expect(collect($triggers)->every(fn ($n) => $n['type'] === 'trigger'))->toBeTrue();
});

it('allows manual registration', function () {
    $this->registry->register('custom_dummy', DummyRegistryNode::class);

    expect($this->registry->has('custom_dummy'))->toBeTrue();

    $instance = $this->registry->resolve('custom_dummy');
    expect($instance)->toBeInstanceOf(NodeInterface::class);
});

it('returns metadata for a single key', function () {
    $meta = $this->registry->getMeta('manual');

    expect($meta)->not->toBeNull();
    expect($meta)->toHaveKeys(['class', 'label', 'type']);
});

it('returns null for unknown key metadata', function () {
    expect($this->registry->getMeta('nonexistent'))->toBeNull();
});

it('checks key existence with has()', function () {
    expect($this->registry->has('manual'))->toBeTrue();
    expect($this->registry->has('nope'))->toBeFalse();
});

// ── Dummy Node ───────────────────────────────────────────────────

final class DummyRegistryNode implements NodeInterface
{
    public static function outputSchema(): array
    {
        return [];
    }

    public static function configSchema(): array
    {
        return [];
    }

    public function inputPorts(): array
    {
        return ['main'];
    }

    public function outputPorts(): array
    {
        return ['main'];
    }

    public function execute(NodeInput $input, array $config): NodeOutput
    {
        return NodeOutput::main($input->items);
    }
}
