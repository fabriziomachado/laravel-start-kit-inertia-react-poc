<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\AiBuilder;

use Aftandilmmd\WorkflowAutomation\Mcp\Tools\ShowWorkflowTool;

final class ShowWorkflowAiBuilderTool extends McpToolAdapter
{
    public function __construct()
    {
        parent::__construct(new ShowWorkflowTool);
    }
}
