<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\AiBuilder;

use Aftandilmmd\WorkflowAutomation\Mcp\Tools\ConnectNodesTool;
use Aftandilmmd\WorkflowAutomation\Services\WorkflowService;

final class ConnectNodesAiBuilderTool extends McpToolAdapter
{
    public function __construct(WorkflowService $service)
    {
        parent::__construct(new ConnectNodesTool($service));
    }
}
