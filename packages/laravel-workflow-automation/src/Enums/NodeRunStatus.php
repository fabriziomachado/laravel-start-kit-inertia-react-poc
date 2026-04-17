<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Enums;

enum NodeRunStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case Skipped = 'skipped';
}
