<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Plugin;

use Aftandilmmd\WorkflowAutomation\Contracts\PluginInterface;

abstract class BasePlugin implements PluginInterface
{
    final public static function make(): static
    {
        return new static;
    }

    final public function boot(PluginContext $context): void
    {
        //
    }

    final public function editorScripts(): array
    {
        return [];
    }
}
