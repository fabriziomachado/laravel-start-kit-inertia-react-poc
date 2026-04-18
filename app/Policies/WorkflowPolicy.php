<?php

declare(strict_types=1);

namespace App\Policies;

use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use App\Models\User;

final class WorkflowPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function start(User $user, Workflow $workflow): bool
    {
        return $workflow->is_active;
    }
}
