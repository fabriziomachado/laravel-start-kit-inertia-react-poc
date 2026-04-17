<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Jobs;

use Aftandilmmd\WorkflowAutomation\Engine\GraphExecutor;
use Aftandilmmd\WorkflowAutomation\Exceptions\RateLimitExceededException;
use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ExecuteWorkflowJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        public readonly int $workflowId,
        public readonly array $payload = [],
        public readonly ?int $triggerNodeId = null,
    ) {
        $this->timeout = (int) config('workflow-automation.max_execution_time', 300);
    }

    public function handle(GraphExecutor $executor): void
    {
        $workflow = Workflow::find($this->workflowId);

        if (! $workflow) {
            Log::warning("WorkflowJob skipped: workflow_id={$this->workflowId} not found.");

            return;
        }

        if (! $workflow->is_active) {
            Log::info("WorkflowJob skipped: workflow_id={$this->workflowId} is inactive.");

            return;
        }

        try {
            $executor->execute($workflow, $this->payload, $this->triggerNodeId);
        } catch (RateLimitExceededException $e) {
            $strategy = config('workflow-automation.rate_limiting.strategy', 'exception');

            if ($strategy === 'queue') {
                $delay = (int) config('workflow-automation.rate_limiting.queue_retry_delay', 30);

                Log::info("WorkflowJob rate-limited: workflow_id={$this->workflowId}, retrying in {$delay}s.", [
                    'scope' => $e->scope,
                    'current_runs' => $e->currentRuns,
                    'max_concurrent' => $e->maxConcurrent,
                ]);

                $this->release($delay);

                return;
            }

            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error("WorkflowJob failed: workflow_id={$this->workflowId}", [
            'error' => $exception->getMessage(),
        ]);
    }
}
