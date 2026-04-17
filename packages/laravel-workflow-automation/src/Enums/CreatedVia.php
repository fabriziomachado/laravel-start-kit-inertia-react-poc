<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Enums;

enum CreatedVia: string
{
    case Editor = 'editor';
    case Import = 'import';
    case Code = 'code';
    case Api = 'api';
    case Duplicate = 'duplicate';
}
