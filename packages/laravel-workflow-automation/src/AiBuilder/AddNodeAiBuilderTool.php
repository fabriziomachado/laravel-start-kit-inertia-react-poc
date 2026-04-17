<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\AiBuilder;

use Aftandilmmd\WorkflowAutomation\Mcp\Tools\AddNodeTool;
use Aftandilmmd\WorkflowAutomation\Services\WorkflowService;

final class AddNodeAiBuilderTool extends McpToolAdapter
{
    public function __construct(WorkflowService $service)
    {
        parent::__construct(new AddNodeTool($service));
    }
}
