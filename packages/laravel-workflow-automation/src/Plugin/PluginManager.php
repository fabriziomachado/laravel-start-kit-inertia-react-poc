<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Plugin;

use Aftandilmmd\WorkflowAutomation\Contracts\PluginInterface;
use Aftandilmmd\WorkflowAutomation\Credentials\CredentialTypeRegistry;
use Aftandilmmd\WorkflowAutomation\Engine\ExpressionEvaluator;
use Aftandilmmd\WorkflowAutomation\Engine\NodeRunner;
use Aftandilmmd\WorkflowAutomation\Registry\NodeRegistry;

final class PluginManager
{
    private PluginContext $context;

    public function __construct(
        private readonly PluginRegistry $registry,
        private readonly NodeRegistry $nodeRegistry,
        private readonly NodeRunner $nodeRunner,
        private readonly ExpressionEvaluator $expressionEvaluator,
        private readonly CredentialTypeRegistry $credentialTypeRegistry,
    ) {
        $this->context = new PluginContext(
            $this->nodeRegistry,
            $this->nodeRunner,
            $this->expressionEvaluator,
            $this->credentialTypeRegistry,
        );
    }

    /**
     * Register a plugin. Calls plugin->register() immediately.
     */
    public function plugin(PluginInterface $plugin): void
    {
        $this->registry->add($plugin);
        $plugin->register($this->context);
    }

    /**
     * Boot all registered plugins. Called once during ServiceProvider::boot().
     */
    public function bootPlugins(): void
    {
        if ($this->registry->isBooted()) {
            return;
        }

        foreach ($this->registry->all() as $plugin) {
            $plugin->boot($this->context);
        }

        $this->registry->markBooted();
    }

    /**
     * Get the plugin registry.
     */
    public function plugins(): PluginRegistry
    {
        return $this->registry;
    }

    /**
     * Get the plugin context.
     */
    public function context(): PluginContext
    {
        return $this->context;
    }
}
