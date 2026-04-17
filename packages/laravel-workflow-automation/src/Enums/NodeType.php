<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Enums;

enum NodeType: string
{
    case Trigger = 'trigger';
    case Action = 'action';
    case Condition = 'condition';
    case Transformer = 'transformer';
    case Control = 'control';
    case Utility = 'utility';
    case Code = 'code';
    case Annotation = 'annotation';
}
