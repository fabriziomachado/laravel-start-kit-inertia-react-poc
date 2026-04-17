<?php

declare(strict_types=1);

use Aftandilmmd\WorkflowAutomation\Attributes\AsWorkflowNode;
use Aftandilmmd\WorkflowAutomation\Contracts\NodeInterface;
use Aftandilmmd\WorkflowAutomation\Contracts\NodeMiddlewareInterface;
use Aftandilmmd\WorkflowAutomation\DTOs\ExecutionContext;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeInput;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeOutput;
use Aftandilmmd\WorkflowAutomation\Engine\NodeRunner;
use Aftandilmmd\WorkflowAutomation\Enums\NodeType;
use Aftandilmmd\WorkflowAutomation\Exceptions\PluginException;
use Aftandilmmd\WorkflowAutomation\Nodes\BaseNode;
use Aftandilmmd\WorkflowAutomation\Plugin\BasePlugin;
use Aftandilmmd\WorkflowAutomation\Plugin\PluginContext;
use Aftandilmmd\WorkflowAutomation\Plugin\PluginManager;
use Aftandilmmd\WorkflowAutomation\Plugin\PluginRegistry;
use Aftandilmmd\WorkflowAutomation\Registry\NodeRegistry;

// ── PluginRegistry ──────────────────────────────────────────────

it('registers a plugin', function () {
    $registry = new PluginRegistry;
    $plugin = new TestPlugin;

    $registry->add($plugin);

    expect($registry->has('test/plugin'))->toBeTrue();
    expect($registry->get('test/plugin'))->toBe($plugin);
    expect($registry->all())->toHaveCount(1);
});

it('throws on duplicate plugin registration', function () {
    $registry = new PluginRegistry;
    $registry->add(new TestPlugin);
    $registry->add(new TestPlugin);
})->throws(PluginException::class, "Plugin 'test/plugin' is already registered.");

it('tracks boot state', function () {
    $registry = new PluginRegistry;

    expect($registry->isBooted())->toBeFalse();

    $registry->markBooted();

    expect($registry->isBooted())->toBeTrue();
});

// ── PluginManager ───────────────────────────────────────────────

it('calls register on plugin immediately', function () {
    $manager = app(PluginManager::class);
    $plugin = new RegisterTrackingPlugin;

    $manager->plugin($plugin);

    expect($plugin->registered)->toBeTrue();
    expect($plugin->booted)->toBeFalse();
});

it('calls boot on all plugins via bootPlugins()', function () {
    $pluginRegistry = new PluginRegistry;
    $manager = new PluginManager(
        $pluginRegistry,
        app(NodeRegistry::class),
        new NodeRunner,
        app(Aftandilmmd\WorkflowAutomation\Contracts\ExpressionEvaluatorInterface::class),
    );
    $plugin = new RegisterTrackingPlugin;

    $manager->plugin($plugin);
    $manager->bootPlugins();

    expect($plugin->booted)->toBeTrue();
});

it('only boots plugins once', function () {
    $pluginRegistry = new PluginRegistry;
    $manager = new PluginManager(
        $pluginRegistry,
        app(NodeRegistry::class),
        new NodeRunner,
        app(Aftandilmmd\WorkflowAutomation\Contracts\ExpressionEvaluatorInterface::class),
    );
    $plugin = new RegisterTrackingPlugin;

    $manager->plugin($plugin);
    $manager->bootPlugins();
    $manager->bootPlugins(); // second call should be no-op

    expect($plugin->bootCount)->toBe(1);
});

it('exposes plugin registry via plugins()', function () {
    $manager = app(PluginManager::class);

    expect($manager->plugins())->toBeInstanceOf(PluginRegistry::class);
});

// ── PluginContext ───────────────────────────────────────────────

it('registers a node via plugin context', function () {
    $manager = app(PluginManager::class);
    $plugin = new NodeRegisteringPlugin;

    $manager->plugin($plugin);

    $registry = app(NodeRegistry::class);
    expect($registry->has('test_plugin_node'))->toBeTrue();

    $instance = $registry->resolve('test_plugin_node');
    expect($instance)->toBeInstanceOf(TestPluginNode::class);
});

// ── NodeRegistry::registerClass() ──────────────────────────────

it('registers a node class via attribute', function () {
    $registry = app(NodeRegistry::class);
    $registry->registerClass(TestPluginNode::class);

    expect($registry->has('test_plugin_node'))->toBeTrue();

    $meta = $registry->getMeta('test_plugin_node');
    expect($meta['type'])->toBe(NodeType::Action);
    expect($meta['label'])->toBe('Test Plugin Node');
});

it('throws when registerClass receives class without attribute', function () {
    $registry = app(NodeRegistry::class);
    $registry->registerClass(NoAttributeNode::class);
})->throws(InvalidArgumentException::class, 'missing the #[AsWorkflowNode] attribute');

it('throws when registerClass receives non-NodeInterface class', function () {
    $registry = app(NodeRegistry::class);
    $registry->registerClass(stdClass::class);
})->throws(InvalidArgumentException::class, 'does not implement NodeInterface');

// ── NodeRunner Middleware ───────────────────────────────────────

it('executes middleware in order', function () {
    $runner = new NodeRunner;
    $log = [];

    $runner->pushMiddleware(new class($log) implements NodeMiddlewareInterface
    {
        public function __construct(private array &$log) {}

        public function handle(NodeInterface $node, NodeInput $input, array $config, Closure $next): NodeOutput
        {
            $this->log[] = 'before_A';
            $result = $next($node, $input, $config);
            $this->log[] = 'after_A';

            return $result;
        }
    });

    $runner->pushMiddleware(new class($log) implements NodeMiddlewareInterface
    {
        public function __construct(private array &$log) {}

        public function handle(NodeInterface $node, NodeInput $input, array $config, Closure $next): NodeOutput
        {
            $this->log[] = 'before_B';
            $result = $next($node, $input, $config);
            $this->log[] = 'after_B';

            return $result;
        }
    });

    $node = Mockery::mock(NodeInterface::class);
    $node->shouldReceive('execute')->once()->andReturn(NodeOutput::main([['ok' => true]]));
    $node->shouldReceive('outputPorts')->andReturn(['main']);

    $context = new ExecutionContext(workflowRunId: 1, workflowId: 1);
    $input = new NodeInput(items: [['data' => 'test']], context: $context);

    $output = $runner->run($node, $input, []);

    expect($log)->toBe(['before_A', 'before_B', 'after_B', 'after_A']);
    expect($output->items('main'))->toHaveCount(1);
});

it('runs without middleware same as before', function () {
    $runner = new NodeRunner;

    $node = Mockery::mock(NodeInterface::class);
    $node->shouldReceive('execute')->once()->andReturn(NodeOutput::main([['result' => 'ok']]));
    $node->shouldReceive('outputPorts')->andReturn(['main']);

    $context = new ExecutionContext(workflowRunId: 1, workflowId: 1);
    $input = new NodeInput(items: [['data' => 'test']], context: $context);

    $output = $runner->run($node, $input, []);

    expect($output->items('main')[0]['result'])->toBe('ok');
});

it('retries through middleware pipeline', function () {
    $runner = new NodeRunner;
    $counter = new stdClass;
    $counter->value = 0;

    $runner->pushMiddleware(new class($counter) implements NodeMiddlewareInterface
    {
        public function __construct(private stdClass $counter) {}

        public function handle(NodeInterface $node, NodeInput $input, array $config, Closure $next): NodeOutput
        {
            $this->counter->value++;

            return $next($node, $input, $config);
        }
    });

    $callCount = 0;
    $node = Mockery::mock(NodeInterface::class);
    $node->shouldReceive('execute')
        ->twice()
        ->andReturnUsing(function () use (&$callCount) {
            $callCount++;
            if ($callCount === 1) {
                throw new RuntimeException('fail');
            }

            return NodeOutput::main([['ok' => true]]);
        });
    $node->shouldReceive('outputPorts')->andReturn(['main']);

    $context = new ExecutionContext(workflowRunId: 1, workflowId: 1);
    $input = new NodeInput(items: [[]], context: $context);

    $output = $runner->run($node, $input, [], maxRetries: 1, retryDelayMs: 1);

    expect($counter->value)->toBe(2); // middleware called on each retry
    expect($output->items('main'))->toHaveCount(1);
});

// ── Config-based Plugin Registration ────────────────────────────

it('registers plugins from config', function () {
    config()->set('workflow-automation.plugins', [TestPlugin::class]);

    // Re-boot to pick up config plugins
    $manager = app(PluginManager::class);

    // Since the service provider already booted, manually test config registration
    foreach (config('workflow-automation.plugins', []) as $pluginClass) {
        if (is_string($pluginClass) && class_exists($pluginClass) && ! $manager->plugins()->has((new $pluginClass)->getId())) {
            $manager->plugin($pluginClass::make());
        }
    }

    expect($manager->plugins()->has('test/plugin'))->toBeTrue();
});

// ── WorkflowAutomation Facade ───────────────────────────────────

it('resolves PluginManager via facade', function () {
    $manager = Aftandilmmd\WorkflowAutomation\Facades\WorkflowAutomation::getFacadeRoot();

    expect($manager)->toBeInstanceOf(PluginManager::class);
});

// ── Test Doubles ────────────────────────────────────────────────

final class TestPlugin extends BasePlugin
{
    public function getId(): string
    {
        return 'test/plugin';
    }

    public function getName(): string
    {
        return 'Test Plugin';
    }

    public function register(PluginContext $context): void
    {
        //
    }
}

final class RegisterTrackingPlugin extends BasePlugin
{
    public bool $registered = false;

    public bool $booted = false;

    public int $bootCount = 0;

    public function getId(): string
    {
        return 'test/tracking';
    }

    public function getName(): string
    {
        return 'Tracking Plugin';
    }

    public function register(PluginContext $context): void
    {
        $this->registered = true;
    }

    public function boot(PluginContext $context): void
    {
        $this->booted = true;
        $this->bootCount++;
    }
}

final class NodeRegisteringPlugin extends BasePlugin
{
    public function getId(): string
    {
        return 'test/node-registering';
    }

    public function getName(): string
    {
        return 'Node Registering Plugin';
    }

    public function register(PluginContext $context): void
    {
        $context->registerNode(TestPluginNode::class);
    }
}

#[AsWorkflowNode(key: 'test_plugin_node', type: NodeType::Action, label: 'Test Plugin Node')]
final class TestPluginNode extends BaseNode
{
    public function execute(NodeInput $input, array $config): NodeOutput
    {
        return NodeOutput::main($input->items);
    }
}

final class NoAttributeNode implements NodeInterface
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
