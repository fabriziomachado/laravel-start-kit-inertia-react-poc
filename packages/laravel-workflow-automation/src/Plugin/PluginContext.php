<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Plugin;

use Aftandilmmd\WorkflowAutomation\Contracts\NodeMiddlewareInterface;
use Aftandilmmd\WorkflowAutomation\Credentials\CredentialTypeInterface;
use Aftandilmmd\WorkflowAutomation\Credentials\CredentialTypeRegistry;
use Aftandilmmd\WorkflowAutomation\Engine\ExpressionEvaluator;
use Aftandilmmd\WorkflowAutomation\Engine\NodeRunner;
use Aftandilmmd\WorkflowAutomation\Registry\NodeRegistry;

final class PluginContext
{
    public function __construct(
        private readonly NodeRegistry $nodeRegistry,
        private readonly NodeRunner $nodeRunner,
        private readonly ExpressionEvaluator $expressionEvaluator,
        private readonly CredentialTypeRegistry $credentialTypeRegistry,
    ) {}

    /**
     * Register a single node class.
     * The class must have the #[AsWorkflowNode] attribute.
     *
     * @param  class-string<\Aftandilmmd\WorkflowAutomation\Contracts\NodeInterface>  $class
     */
    public function registerNode(string $class): void
    {
        $this->nodeRegistry->registerClass($class);
    }

    /**
     * Discover all nodes in a directory.
     */
    public function discoverNodes(string $directory): void
    {
        $this->nodeRegistry->discoverNodes($directory);
    }

    /**
     * Register a custom expression function.
     */
    public function registerExpressionFunction(string $name, callable $fn): void
    {
        $this->expressionEvaluator->registerFunction($name, $fn);
    }

    /**
     * Register node execution middleware.
     *
     * @param  class-string<NodeMiddlewareInterface>|NodeMiddlewareInterface  $middleware
     */
    public function registerMiddleware(string|NodeMiddlewareInterface $middleware): void
    {
        $this->nodeRunner->pushMiddleware($middleware);
    }

    /**
     * Register a custom credential type.
     *
     * @param  class-string<CredentialTypeInterface>  $class
     */
    public function registerCredentialType(string $class): void
    {
        $this->credentialTypeRegistry->register($class);
    }
}
