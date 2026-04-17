<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Events;

use Aftandilmmd\WorkflowAutomation\Models\WorkflowRun;
use Illuminate\Foundation\Events\Dispatchable;
use Throwable;

final class WorkflowFailed
{
    use Dispatchable;

    public function __construct(
        public readonly WorkflowRun $run,
        public readonly Throwable $exception,
        public readonly array $outputData = [],
    ) {}
}
