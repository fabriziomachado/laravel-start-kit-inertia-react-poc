<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\AiBuilder;

use Aftandilmmd\WorkflowAutomation\Mcp\Tools\ShowNodeTypeTool;
use Aftandilmmd\WorkflowAutomation\Registry\NodeRegistry;

final class ShowNodeTypeAiBuilderTool extends McpToolAdapter
{
    public function __construct(NodeRegistry $registry)
    {
        parent::__construct(new ShowNodeTypeTool($registry));
    }
}
