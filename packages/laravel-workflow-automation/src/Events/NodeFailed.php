<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Events;

use Aftandilmmd\WorkflowAutomation\Models\WorkflowNodeRun;
use Illuminate\Foundation\Events\Dispatchable;
use Throwable;

final class NodeFailed
{
    use Dispatchable;

    public function __construct(
        public readonly WorkflowNodeRun $nodeRun,
        public readonly Throwable $exception,
    ) {}
}
