<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Services;

use Aftandilmmd\WorkflowAutomation\Enums\RunStatus;
use Aftandilmmd\WorkflowAutomation\Exceptions\RateLimitExceededException;
use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowRun;
use Illuminate\Support\Facades\Cache;

final class ConcurrencyGuard
{
    /**
     * Check if a workflow can be executed within concurrency limits.
     *
     * @throws RateLimitExceededException
     */
    public function acquire(Workflow $workflow): void
    {
        $this->checkGlobalLimit($workflow);
        $this->checkWorkflowLimit($workflow);
    }

    /**
     * Check if a workflow can run without throwing — returns true/false.
     */
    public function canRun(Workflow $workflow): bool
    {
        try {
            $this->acquire($workflow);

            return true;
        } catch (RateLimitExceededException) {
            return false;
        }
    }

    /**
     * Get the current concurrency status for a workflow.
     */
    public function status(Workflow $workflow): array
    {
        $globalMax = $this->globalLimit();
        $workflowMax = $this->workflowLimit($workflow);

        $globalActive = $globalMax > 0 ? $this->countGlobalActiveRuns() : 0;
        $workflowActive = $workflowMax > 0 ? $this->countWorkflowActiveRuns($workflow->id) : 0;

        return [
            'global' => [
                'max_concurrent' => $globalMax,
                'active_runs' => $globalActive,
                'available' => $globalMax > 0 ? max(0, $globalMax - $globalActive) : null,
            ],
            'workflow' => [
                'max_concurrent' => $workflowMax,
                'active_runs' => $workflowActive,
                'available' => $workflowMax > 0 ? max(0, $workflowMax - $workflowActive) : null,
            ],
            'can_run' => $this->canRun($workflow),
        ];
    }

    private function checkGlobalLimit(Workflow $workflow): void
    {
        $limit = $this->globalLimit();

        if ($limit <= 0) {
            return;
        }

        $lockKey = 'workflow:concurrency:global';

        Cache::lock($lockKey, 10)->block(5, function () use ($limit, $workflow) {
            $activeRuns = $this->countGlobalActiveRuns();

            if ($activeRuns >= $limit) {
                throw new RateLimitExceededException(
                    workflowId: $workflow->id,
                    currentRuns: $activeRuns,
                    maxConcurrent: $limit,
                    scope: 'global',
                );
            }
        });
    }

    private function checkWorkflowLimit(Workflow $workflow): void
    {
        $limit = $this->workflowLimit($workflow);

        if ($limit <= 0) {
            return;
        }

        $lockKey = "workflow:concurrency:workflow:{$workflow->id}";

        Cache::lock($lockKey, 10)->block(5, function () use ($limit, $workflow) {
            $activeRuns = $this->countWorkflowActiveRuns($workflow->id);

            if ($activeRuns >= $limit) {
                throw new RateLimitExceededException(
                    workflowId: $workflow->id,
                    currentRuns: $activeRuns,
                    maxConcurrent: $limit,
                    scope: 'workflow',
                );
            }
        });
    }

    private function countGlobalActiveRuns(): int
    {
        return WorkflowRun::whereIn('status', [RunStatus::Running, RunStatus::Pending])->count();
    }

    private function countWorkflowActiveRuns(int $workflowId): int
    {
        return WorkflowRun::where('workflow_id', $workflowId)
            ->whereIn('status', [RunStatus::Running, RunStatus::Pending])
            ->count();
    }

    private function globalLimit(): int
    {
        return (int) config('workflow-automation.rate_limiting.global_max_concurrent', 0);
    }

    private function workflowLimit(Workflow $workflow): int
    {
        // Per-workflow setting takes precedence over config default
        $perWorkflow = $workflow->settings['max_concurrent_runs'] ?? null;

        if ($perWorkflow !== null) {
            return (int) $perWorkflow;
        }

        return (int) config('workflow-automation.rate_limiting.max_concurrent_per_workflow', 0);
    }
}
