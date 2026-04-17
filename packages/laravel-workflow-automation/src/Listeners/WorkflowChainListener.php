<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Listeners;

use Aftandilmmd\WorkflowAutomation\Enums\NodeType;
use Aftandilmmd\WorkflowAutomation\Events\WorkflowCompleted;
use Aftandilmmd\WorkflowAutomation\Events\WorkflowFailed;
use Aftandilmmd\WorkflowAutomation\Jobs\ExecuteWorkflowJob;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowNode;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

final class WorkflowChainListener
{
    /**
     * Register listeners for workflow completion and failure events.
     */
    public static function register(): void
    {
        Event::listen(WorkflowCompleted::class, [self::class, 'handleCompleted']);
        Event::listen(WorkflowFailed::class, [self::class, 'handleFailed']);
    }

    public static function handleCompleted(WorkflowCompleted $event): void
    {
        self::triggerChainedWorkflows($event->run, 'completed', $event->outputData);
    }

    public static function handleFailed(WorkflowFailed $event): void
    {
        self::triggerChainedWorkflows($event->run, 'failed', $event->outputData, $event->exception->getMessage());
    }

    /**
     * Clear the cached workflow triggers (call after workflow activation/deactivation).
     */
    public static function clearCache(): void
    {
        Cache::forget('workflow:workflow_triggers');
    }

    private static function triggerChainedWorkflows(
        \Aftandilmmd\WorkflowAutomation\Models\WorkflowRun $run,
        string $status,
        array $outputData,
        ?string $errorMessage = null,
    ): void {
        // Prevent infinite chain loops
        $chainDepth = $run->initial_payload['_chain_depth'] ?? 0;
        $maxDepth = (int) config('workflow-automation.chaining.max_depth', 10);

        if ($chainDepth >= $maxDepth) {
            Log::warning("Workflow chain depth limit reached ({$maxDepth}). Stopping chain.", [
                'workflow_id' => $run->workflow_id,
                'run_id' => $run->id,
            ]);

            return;
        }

        $triggers = self::getWorkflowTriggers();

        foreach ($triggers as $trigger) {
            $config = $trigger['config'];
            $triggerOn = $config['trigger_on'] ?? 'completed';

            // Check if this trigger should fire for the current status
            if ($triggerOn !== 'any' && $triggerOn !== $status) {
                continue;
            }

            // Check source workflow filter
            $sourceWorkflowId = $config['source_workflow_id'] ?? null;
            if ($sourceWorkflowId && (int) $sourceWorkflowId !== $run->workflow_id) {
                continue;
            }

            // Don't trigger self (prevent direct self-loop)
            if ($trigger['workflow_id'] === $run->workflow_id) {
                continue;
            }

            $payload = [[
                'source_workflow_id' => $run->workflow_id,
                'source_run_id' => $run->id,
                'source_status' => $status,
                'error_message' => $errorMessage,
                'data' => $outputData,
                '_chain_depth' => $chainDepth + 1,
            ]];

            ExecuteWorkflowJob::dispatch(
                workflowId: $trigger['workflow_id'],
                payload: $payload,
                triggerNodeId: $trigger['node_id'],
            )->onQueue(config('workflow-automation.queue', 'default'));
        }
    }

    /**
     * @return array<int, array{workflow_id: int, node_id: int, config: array}>
     */
    private static function getWorkflowTriggers(): array
    {
        try {
            return Cache::remember('workflow:workflow_triggers', 60, function () {
                return WorkflowNode::query()
                    ->where('type', NodeType::Trigger)
                    ->where('node_key', 'workflow')
                    ->whereHas('workflow', fn ($q) => $q->where('is_active', true))
                    ->get()
                    ->map(fn (WorkflowNode $node) => [
                        'workflow_id' => $node->workflow_id,
                        'node_id' => $node->id,
                        'config' => $node->config ?? [],
                    ])
                    ->toArray();
            });
        } catch (\Illuminate\Database\QueryException) {
            return [];
        }
    }
}
