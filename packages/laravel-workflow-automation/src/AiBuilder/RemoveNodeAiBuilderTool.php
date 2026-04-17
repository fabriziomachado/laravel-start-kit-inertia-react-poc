<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\AiBuilder;

use Aftandilmmd\WorkflowAutomation\Mcp\Tools\RemoveNodeTool;
use Aftandilmmd\WorkflowAutomation\Services\WorkflowService;

final class RemoveNodeAiBuilderTool extends McpToolAdapter
{
    public function __construct(WorkflowService $service)
    {
        parent::__construct(new RemoveNodeTool($service));
    }
}
