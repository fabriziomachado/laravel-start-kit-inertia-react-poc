<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Enums;

enum RunStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Waiting = 'waiting';
}
