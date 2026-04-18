<?php

declare(strict_types=1);

namespace App\Policies;

use Aftandilmmd\WorkflowAutomation\Models\WorkflowRun;
use App\Flows\WorkflowStarterPayload;
use App\Models\User;

final class WorkflowRunPolicy
{
    public function view(User $user, WorkflowRun $run): bool
    {
        if ($user->is_admin) {
            return true;
        }

        $starterId = WorkflowStarterPayload::starterUserId($run);

        return $starterId !== null && $starterId === (string) $user->getKey();
    }
}
