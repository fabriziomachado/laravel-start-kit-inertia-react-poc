<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Contracts;

use Aftandilmmd\WorkflowAutomation\DTOs\NodeInput;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeOutput;
use Closure;

interface NodeMiddlewareInterface
{
    /**
     * Handle node execution, optionally modifying input/output or adding behavior.
     *
     * @param  Closure(NodeInterface, NodeInput, array): NodeOutput  $next
     */
    public function handle(
        NodeInterface $node,
        NodeInput $input,
        array $config,
        Closure $next,
    ): NodeOutput;
}
