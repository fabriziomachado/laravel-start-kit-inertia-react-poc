<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Events;

use Aftandilmmd\WorkflowAutomation\Models\WorkflowNodeRun;
use Illuminate\Foundation\Events\Dispatchable;

final class NodeExecuted
{
    use Dispatchable;

    public function __construct(
        public readonly WorkflowNodeRun $nodeRun,
    ) {}
}
