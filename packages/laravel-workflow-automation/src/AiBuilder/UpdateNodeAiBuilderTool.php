<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\AiBuilder;

use Aftandilmmd\WorkflowAutomation\Mcp\Tools\UpdateNodeTool;

final class UpdateNodeAiBuilderTool extends McpToolAdapter
{
    public function __construct()
    {
        parent::__construct(new UpdateNodeTool);
    }
}
