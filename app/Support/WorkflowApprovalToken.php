<?php

declare(strict_types=1);

namespace App\Support;

final class WorkflowApprovalToken
{
    public static function cacheKey(int $workflowRunId, int $workflowNodeId): string
    {
        return "workflow-approval-token:{$workflowRunId}:{$workflowNodeId}";
    }
}
