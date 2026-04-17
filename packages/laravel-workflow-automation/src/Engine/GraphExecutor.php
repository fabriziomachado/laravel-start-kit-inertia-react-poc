<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Engine;

use Aftandilmmd\WorkflowAutomation\Contracts\ExpressionEvaluatorInterface;
use Aftandilmmd\WorkflowAutomation\DTOs\ExecutionContext;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeInput;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeOutput;
use Aftandilmmd\WorkflowAutomation\Enums\NodeRunStatus;
use Aftandilmmd\WorkflowAutomation\Enums\NodeType;
use Aftandilmmd\WorkflowAutomation\Enums\RunStatus;
use Aftandilmmd\WorkflowAutomation\Events\NodeExecuted;
use Aftandilmmd\WorkflowAutomation\Events\NodeFailed;
use Aftandilmmd\WorkflowAutomation\Events\WorkflowCompleted;
use Aftandilmmd\WorkflowAutomation\Events\WorkflowFailed;
use Aftandilmmd\WorkflowAutomation\Events\WorkflowStarted;
use Aftandilmmd\WorkflowAutomation\Exceptions\WorkflowException;
use Aftandilmmd\WorkflowAutomation\Exceptions\WorkflowValidationException;
use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowNode;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowNodeRun;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowRun;
use Aftandilmmd\WorkflowAutomation\Registry\NodeRegistry;
use Aftandilmmd\WorkflowAutomation\Services\ConcurrencyGuard;
use Illuminate\Support\Collection;
use Throwable;

final class GraphExecutor
{
    public function __construct(
        private readonly NodeRegistry $registry,
        private readonly NodeRunner $nodeRunner,
        private readonly ExpressionEvaluatorInterface $expressionEvaluator,
        private readonly GraphValidator $graphValidator,
        private readonly ConcurrencyGuard $concurrencyGuard,
    ) {}

    /**
     * Execute a workflow synchronously and return the run record.
     */
    public function execute(Workflow $workflow, array $initialPayload = [], ?int $triggerNodeId = null, ?int $parentRunId = null): WorkflowRun
    {
        $this->concurrencyGuard->acquire($workflow);

        $run = WorkflowRun::create([
            'workflow_id' => $workflow->id,
            'status' => RunStatus::Pending,
            'trigger_node_id' => $triggerNodeId,
            'initial_payload' => $initialPayload,
            'parent_run_id' => $parentRunId,
        ]);

        try {
            $this->graphValidator->validate($workflow);
            $this->doExecute($workflow, $run, $initialPayload);
        } catch (Throwable $e) {
            $errorMessage = $e instanceof WorkflowValidationException
                ? json_encode(['message' => $e->getMessage(), 'errors' => $e->errors])
                : $e->getMessage();

            $run->update([
                'status' => RunStatus::Failed,
                'error_message' => $errorMessage,
                'finished_at' => now(),
            ]);

            event(new WorkflowFailed($run, $e, $run->context ?? []));
        }

        return $run->fresh();
    }

    /**
     * Resume a paused (waiting) workflow from a specific node.
     */
    public function resume(WorkflowRun $run, int $resumeFromNodeId, array $resumePayload = [], string $resumePort = 'resume'): WorkflowRun
    {
        if (! $run->isWaiting()) {
            throw new WorkflowException("Cannot resume run {$run->id}: status is {$run->status->value}");
        }

        $workflow = $run->workflow;
        $nodes = $workflow->nodes()->get()->keyBy('id');
        $edges = $workflow->edges()->get();

        $context = new ExecutionContext(
            workflowRunId: $run->id,
            workflowId: $workflow->id,
            initialPayload: $run->initial_payload ?? [],
        );

        // Restore previous context
        if ($run->context) {
            $context->restoreOutputs($run->context);
        }

        $run->update(['status' => RunStatus::Running]);

        try {
            // Store resume payload as the node's output
            $context->setNodeOutput($resumeFromNodeId, $resumePort, $resumePayload ? [$resumePayload] : [[]]);

            $edgeMap = $this->buildEdgeMap($edges);

            // Queue up edges from the resume port
            $queue = [];
            $key = "{$resumeFromNodeId}_{$resumePort}";
            $items = $resumePayload ? [$resumePayload] : [[]];

            foreach ($edgeMap[$key] ?? [] as $targetNodeId) {
                $queue[] = ['node_id' => $targetNodeId, 'from_port' => $resumePort, 'items' => $items];
            }

            $this->processQueue($queue, $nodes, $edges, $edgeMap, $context, $run);

            $run->update([
                'status' => RunStatus::Completed,
                'context' => $context->getAllOutputs(),
                'finished_at' => now(),
            ]);

            event(new WorkflowCompleted($run, $context->getAllOutputs()));
        } catch (Throwable $e) {
            $run->update([
                'status' => RunStatus::Failed,
                'error_message' => $e->getMessage(),
                'finished_at' => now(),
            ]);

            event(new WorkflowFailed($run, $e, $context->getAllOutputs()));
        }

        return $run->fresh();
    }

    /**
     * Retry a failed run from the point of failure.
     * Creates a new run, restores context up to the failed node, re-executes from there.
     */
    public function retryFromFailure(WorkflowRun $failedRun): WorkflowRun
    {
        $workflow = $failedRun->workflow;
        $this->graphValidator->validate($workflow);

        $failedNodeRun = $failedRun->nodeRuns()
            ->where('status', NodeRunStatus::Failed)
            ->oldest('executed_at')
            ->first();

        if (! $failedNodeRun) {
            throw new WorkflowException("No failed node found in run {$failedRun->id}.");
        }

        return $this->retryFromNode($failedRun, $failedNodeRun);
    }

    /**
     * Retry a specific failed node within a run.
     * Creates a new run, restores context, re-executes from the given node.
     */
    public function retryNode(WorkflowRun $run, int $nodeId): WorkflowRun
    {
        $this->graphValidator->validate($run->workflow);

        $failedNodeRun = $run->nodeRuns()
            ->where('node_id', $nodeId)
            ->where('status', NodeRunStatus::Failed)
            ->latest()
            ->first();

        if (! $failedNodeRun) {
            throw new WorkflowException("No failed node run found for node {$nodeId} in run {$run->id}.");
        }

        return $this->retryFromNode($run, $failedNodeRun);
    }

    /**
     * Execute a workflow up to (and including) a specific node, then stop.
     */
    public function executeUpTo(Workflow $workflow, int $targetNodeId, array $initialPayload = []): WorkflowRun
    {
        $run = WorkflowRun::create([
            'workflow_id' => $workflow->id,
            'status' => RunStatus::Pending,
            'initial_payload' => $initialPayload,
        ]);

        try {
            $this->graphValidator->validate($workflow);
            $this->doExecute($workflow, $run, $initialPayload, $targetNodeId);
        } catch (Throwable $e) {
            $errorMessage = $e instanceof WorkflowValidationException
                ? json_encode(['message' => $e->getMessage(), 'errors' => $e->errors])
                : $e->getMessage();

            $run->update([
                'status' => RunStatus::Failed,
                'error_message' => $errorMessage,
                'finished_at' => now(),
            ]);

            event(new WorkflowFailed($run, $e, $run->context ?? []));
        }

        return $run->fresh();
    }

    private function retryFromNode(WorkflowRun $originalRun, WorkflowNodeRun $failedNodeRun): WorkflowRun
    {
        $workflow = $originalRun->workflow;

        $newRun = WorkflowRun::create([
            'workflow_id' => $workflow->id,
            'status' => RunStatus::Running,
            'trigger_node_id' => $originalRun->trigger_node_id,
            'initial_payload' => $originalRun->initial_payload,
            'started_at' => now(),
        ]);

        event(new WorkflowStarted($newRun));

        $nodes = $workflow->nodes()->get()->keyBy('id');
        $edges = $workflow->edges()->get();

        $context = new ExecutionContext(
            workflowRunId: $newRun->id,
            workflowId: $workflow->id,
            initialPayload: $originalRun->initial_payload ?? [],
        );

        if ($originalRun->context) {
            $context->restoreOutputs($originalRun->context);
        }

        try {
            $edgeMap = $this->buildEdgeMap($edges);

            $originalInput = $failedNodeRun->input ?? [[]];
            $queue = [['node_id' => $failedNodeRun->node_id, 'from_port' => 'main', 'items' => $originalInput]];

            $this->processQueue($queue, $nodes, $edges, $edgeMap, $context, $newRun);

            if ($newRun->fresh()->status !== RunStatus::Waiting) {
                $newRun->update([
                    'status' => RunStatus::Completed,
                    'context' => $context->getAllOutputs(),
                    'finished_at' => now(),
                ]);

                event(new WorkflowCompleted($newRun, $context->getAllOutputs()));
            } else {
                $newRun->update(['context' => $context->getAllOutputs()]);
            }
        } catch (Throwable $e) {
            $newRun->update([
                'status' => RunStatus::Failed,
                'error_message' => $e->getMessage(),
                'context' => $context->getAllOutputs(),
                'finished_at' => now(),
            ]);

            event(new WorkflowFailed($newRun, $e, $context->getAllOutputs()));
        }

        return $newRun->fresh();
    }

    private function doExecute(Workflow $workflow, WorkflowRun $run, array $initialPayload, ?int $stopAfterNodeId = null): void
    {
        $run->update(['status' => RunStatus::Running, 'started_at' => now()]);
        event(new WorkflowStarted($run));

        $nodes = $workflow->nodes()->get()->keyBy('id');
        $edges = $workflow->edges()->get();

        $context = new ExecutionContext(
            workflowRunId: $run->id,
            workflowId: $workflow->id,
            initialPayload: $initialPayload,
        );

        if ($stopAfterNodeId !== null) {
            $context->enableTestMode();
        }

        // Find and execute trigger node
        $triggerNode = $nodes->firstWhere('type', NodeType::Trigger);

        if (! $triggerNode) {
            throw new WorkflowException("Workflow {$workflow->id} has no trigger node.");
        }

        // If the target node IS the trigger, execute it and stop
        if ($stopAfterNodeId === $triggerNode->id) {
            $triggerOutput = $this->executeNode(
                $triggerNode,
                new NodeInput(items: $initialPayload ?: [[]], context: $context),
                $run,
                $context,
                $nodes,
            );

            foreach ($triggerOutput->portItems as $port => $items) {
                $context->setNodeOutput($triggerNode->id, $port, $items);
            }

            $run->update([
                'status' => RunStatus::Completed,
                'context' => $context->getAllOutputs(),
                'finished_at' => now(),
            ]);

            event(new WorkflowCompleted($run, $context->getAllOutputs()));

            return;
        }

        $triggerOutput = $this->executeNode(
            $triggerNode,
            new NodeInput(items: $initialPayload ?: [[]], context: $context),
            $run,
            $context,
            $nodes,
        );

        foreach ($triggerOutput->portItems as $port => $items) {
            $context->setNodeOutput($triggerNode->id, $port, $items);
        }

        $edgeMap = $this->buildEdgeMap($edges);

        // Seed queue from trigger output
        $queue = [];
        foreach ($triggerOutput->portItems as $port => $items) {
            if (empty($items)) {
                continue;
            }

            $key = "{$triggerNode->id}_{$port}";
            foreach ($edgeMap[$key] ?? [] as $targetNodeId) {
                $queue[] = ['node_id' => $targetNodeId, 'from_port' => $port, 'items' => $items];
            }
        }

        $this->processQueue($queue, $nodes, $edges, $edgeMap, $context, $run, $stopAfterNodeId);

        // Check if a wait/delay node paused execution
        if ($run->fresh()->status === RunStatus::Waiting) {
            $run->update(['context' => $context->getAllOutputs()]);

            return;
        }

        $run->update([
            'status' => RunStatus::Completed,
            'context' => $context->getAllOutputs(),
            'finished_at' => now(),
        ]);

        event(new WorkflowCompleted($run, $context->getAllOutputs()));
    }

    /**
     * BFS processing loop.
     */
    private function processQueue(
        array $queue,
        Collection $nodes,
        Collection $edges,
        array $edgeMap,
        ExecutionContext $context,
        WorkflowRun $run,
        ?int $stopAfterNodeId = null,
    ): void {
        $pendingInputs = [];

        while (! empty($queue)) {
            $task = array_shift($queue);
            $nodeId = $task['node_id'];
            $node = $nodes->get($nodeId);

            if (! $node || ! $this->registry->has($node->node_key)) {
                continue;
            }

            // Skip annotation nodes (sticky notes) — they are visual-only
            if ($node->type === NodeType::Annotation) {
                continue;
            }

            $nodeInstance = $this->registry->resolve($node->node_key);
            $inputPorts = $nodeInstance->inputPorts();

            // Multi-input nodes: accumulate items from all incoming ports
            if (count($inputPorts) > 1) {
                $pendingInputs[$nodeId][$task['from_port']] = $task['items'];

                $incomingPorts = $this->getIncomingPorts($nodeId, $edges);
                foreach ($incomingPorts as $port) {
                    if (! isset($pendingInputs[$nodeId][$port])) {
                        continue 2; // Not all inputs received yet
                    }
                }

                $allItems = [];
                foreach ($pendingInputs[$nodeId] as $portItems) {
                    $allItems = array_merge($allItems, $portItems);
                }
                unset($pendingInputs[$nodeId]);
            } else {
                $allItems = $task['items'];
            }

            // Resolve expressions in config
            $nodeNameMap = $nodes->filter(fn ($n) => $n->name)->pluck('id', 'name')->toArray();
            $variables = $context->toVariables($nodeNameMap, $allItems[0] ?? []);
            $resolvedConfig = $this->expressionEvaluator->resolveConfig($node->config ?? [], $variables);

            // Execute node
            $nodeOutput = $this->executeNode(
                $node,
                new NodeInput(items: $allItems, context: $context),
                $run,
                $context,
                $nodes,
                $resolvedConfig,
            );

            // Check if node signaled a pause (wait/delay)
            if ($run->fresh()->status === RunStatus::Waiting) {
                return;
            }

            // Store output in context
            foreach ($nodeOutput->portItems as $port => $items) {
                $context->setNodeOutput($node->id, $port, $items);
            }

            // Stop processing if we reached the target node
            if ($stopAfterNodeId !== null && $node->id === $stopAfterNodeId) {
                return;
            }

            // Enqueue downstream nodes
            foreach ($nodeOutput->portItems as $port => $items) {
                if (empty($items)) {
                    continue;
                }

                $key = "{$node->id}_{$port}";
                foreach ($edgeMap[$key] ?? [] as $targetNodeId) {
                    $queue[] = ['node_id' => $targetNodeId, 'from_port' => $port, 'items' => $items];
                }
            }
        }
    }

    private function executeNode(
        WorkflowNode $node,
        NodeInput $input,
        WorkflowRun $run,
        ExecutionContext $context,
        Collection $nodes,
        ?array $resolvedConfig = null,
    ): NodeOutput {
        // ── Pinned test data (test mode only) ─────────────────────
        if ($context->isTestMode() && $node->hasPinnedOutput()) {
            $pinnedOutput = new NodeOutput($node->getPinnedOutput());

            $nodeRun = WorkflowNodeRun::create([
                'workflow_run_id' => $run->id,
                'node_id' => $node->id,
                'status' => NodeRunStatus::Completed,
                'input' => $input->items,
                'output' => $pinnedOutput->portItems,
                'executed_at' => now(),
                'duration_ms' => 0,
            ]);

            event(new NodeExecuted($nodeRun));

            return $pinnedOutput;
        }

        if ($context->isTestMode() && $node->hasPinnedInput()) {
            $input = new NodeInput(
                items: $node->getPinnedInput(),
                context: $input->context,
                port: $input->port,
            );
        }

        $config = $resolvedConfig ?? $node->config ?? [];

        $nodeRun = WorkflowNodeRun::create([
            'workflow_run_id' => $run->id,
            'node_id' => $node->id,
            'status' => NodeRunStatus::Running,
            'input' => $input->items,
            'executed_at' => now(),
        ]);

        $startTime = microtime(true);

        $retryCount = $config['retry_count']
            ?? $run->workflow->settings['retry_count']
            ?? config('workflow-automation.default_retry_count', 0);

        $retryDelay = $config['retry_delay_ms']
            ?? config('workflow-automation.default_retry_delay_ms', 1000);

        $backoff = config('workflow-automation.retry_backoff', 'exponential');

        try {
            $nodeInstance = $this->registry->resolve($node->node_key);

            $output = $this->nodeRunner->run(
                node: $nodeInstance,
                input: $input,
                config: $config,
                maxRetries: $retryCount,
                retryDelayMs: $retryDelay,
                backoffStrategy: $backoff,
            );

            $nodeRun->update([
                'status' => NodeRunStatus::Completed,
                'output' => $output->portItems,
                'duration_ms' => (int) ((microtime(true) - $startTime) * 1000),
                'attempts' => 1,
            ]);

            event(new NodeExecuted($nodeRun));

            return $output;
        } catch (Throwable $e) {
            $nodeRun->update([
                'status' => NodeRunStatus::Failed,
                'error_message' => $e->getMessage(),
                'duration_ms' => (int) ((microtime(true) - $startTime) * 1000),
            ]);

            event(new NodeFailed($nodeRun, $e));

            throw $e;
        }
    }

    /**
     * Build a map: "{sourceNodeId}_{sourcePort}" => [targetNodeId, ...]
     */
    private function buildEdgeMap(Collection $edges): array
    {
        $map = [];

        foreach ($edges as $edge) {
            $key = "{$edge->source_node_id}_{$edge->source_port}";
            $map[$key][] = $edge->target_node_id;
        }

        return $map;
    }

    /**
     * Get all incoming port names for a node.
     */
    private function getIncomingPorts(int $nodeId, Collection $edges): array
    {
        return $edges
            ->where('target_node_id', $nodeId)
            ->pluck('target_port')
            ->unique()
            ->values()
            ->toArray();
    }
}
