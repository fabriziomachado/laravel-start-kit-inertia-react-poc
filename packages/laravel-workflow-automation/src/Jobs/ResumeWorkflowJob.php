<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Jobs;

use Aftandilmmd\WorkflowAutomation\Engine\GraphExecutor;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ResumeWorkflowJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        public readonly int $workflowRunId,
        public readonly int $resumeFromNodeId,
        public readonly array $payload = [],
        public readonly string $resumePort = 'resume',
    ) {
        $this->timeout = (int) config('workflow-automation.max_execution_time', 300);
    }

    public function handle(GraphExecutor $executor): void
    {
        $run = WorkflowRun::find($this->workflowRunId);

        if (! $run || ! $run->isWaiting()) {
            return;
        }

        $executor->resume($run, $this->resumeFromNodeId, $this->payload, $this->resumePort);
    }

    public function failed(Throwable $exception): void
    {
        Log::error("ResumeWorkflowJob failed: run_id={$this->workflowRunId}", [
            'error' => $exception->getMessage(),
        ]);
    }
}
