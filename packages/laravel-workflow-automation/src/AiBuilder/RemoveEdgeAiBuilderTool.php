<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\AiBuilder;

use Aftandilmmd\WorkflowAutomation\Mcp\Tools\RemoveEdgeTool;
use Aftandilmmd\WorkflowAutomation\Services\WorkflowService;

final class RemoveEdgeAiBuilderTool extends McpToolAdapter
{
    public function __construct(WorkflowService $service)
    {
        parent::__construct(new RemoveEdgeTool($service));
    }
}
