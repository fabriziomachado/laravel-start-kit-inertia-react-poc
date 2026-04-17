<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\AiBuilder;

use Aftandilmmd\WorkflowAutomation\Mcp\Tools\ListNodeTypesTool;
use Aftandilmmd\WorkflowAutomation\Registry\NodeRegistry;

final class ListNodeTypesAiBuilderTool extends McpToolAdapter
{
    public function __construct(NodeRegistry $registry)
    {
        parent::__construct(new ListNodeTypesTool($registry));
    }
}
