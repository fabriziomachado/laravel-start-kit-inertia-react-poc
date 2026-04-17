<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Nodes;

use Aftandilmmd\WorkflowAutomation\Contracts\NodeInterface;

abstract class BaseNode implements NodeInterface
{
    use HasDocumentation;

    public static function configSchema(): array
    {
        return [];
    }

    public static function outputSchema(): array
    {
        return [];
    }

    public function inputPorts(): array
    {
        return ['main'];
    }

    public function outputPorts(): array
    {
        return ['main', 'error'];
    }
}
