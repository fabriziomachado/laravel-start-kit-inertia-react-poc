<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Services;

use Aftandilmmd\WorkflowAutomation\Engine\GraphExecutor;
use Aftandilmmd\WorkflowAutomation\Engine\GraphValidator;
use Aftandilmmd\WorkflowAutomation\Enums\CreatedVia;
use Aftandilmmd\WorkflowAutomation\Enums\NodeType;
use Aftandilmmd\WorkflowAutomation\Enums\RunStatus;
use Aftandilmmd\WorkflowAutomation\Exceptions\WorkflowException;
use Aftandilmmd\WorkflowAutomation\Jobs\ExecuteWorkflowJob;
use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowEdge;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowNode;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowRun;

final class WorkflowService
{
    public function __construct(
        private readonly GraphExecutor $executor,
        private readonly GraphValidator $validator,
        private readonly ConcurrencyGuard $concurrencyGuard,
    ) {}

    // ── Execution ──────────────────────────────────────────────────

    /**
     * Run a workflow synchronously.
     */
    public function run(int|Workflow $workflow, array $payload = []): WorkflowRun
    {
        $workflow = $this->resolveWorkflow($workflow);

        return $this->executor->execute($workflow, $payload);
    }

    /**
     * Dispatch a workflow to the queue for async execution.
     */
    public function runAsync(int|Workflow $workflow, array $payload = []): void
    {
        $workflow = $this->resolveWorkflow($workflow);

        ExecuteWorkflowJob::dispatch($workflow->id, $payload)
            ->onQueue(config('workflow-automation.queue', 'default'));
    }

    /**
     * Resume a waiting workflow run.
     */
    public function resume(int|WorkflowRun $run, string $resumeToken, array $payload = []): WorkflowRun
    {
        $run = $this->resolveRun($run);

        // Find the waiting node run that holds this resume token
        $nodeRun = $run->nodeRuns()
            ->where('status', 'completed')
            ->whereJsonContains('output->resume->0->resume_token', $resumeToken)
            ->first();

        $resumeNodeId = $nodeRun?->node_id ?? 0;

        return $this->executor->resume($run, $resumeNodeId, $payload);
    }

    /**
     * Cancel a running or waiting workflow.
     */
    public function cancel(int|WorkflowRun $run): WorkflowRun
    {
        $run = $this->resolveRun($run);

        if ($run->isFinished()) {
            return $run;
        }

        $run->update([
            'status' => RunStatus::Cancelled,
            'finished_at' => now(),
        ]);

        return $run->fresh();
    }

    /**
     * Replay a completed/failed run with its original payload.
     */
    public function replay(int|WorkflowRun $run): WorkflowRun
    {
        $run = $this->resolveRun($run);

        return $this->run($run->workflow_id, $run->initial_payload ?? []);
    }

    /**
     * Retry a failed run from the point of failure.
     */
    public function retryFromFailure(int|WorkflowRun $run): WorkflowRun
    {
        $run = $this->resolveRun($run);

        if ($run->status !== RunStatus::Failed) {
            throw new WorkflowException("Can only retry failed runs. Current status: {$run->status->value}");
        }

        return $this->executor->retryFromFailure($run);
    }

    /**
     * Execute a workflow up to (and including) a specific node, then stop.
     */
    public function testNode(int|Workflow $workflow, int $nodeId, array $payload = []): WorkflowRun
    {
        $workflow = $this->resolveWorkflow($workflow);

        return $this->executor->executeUpTo($workflow, $nodeId, $payload);
    }

    /**
     * Retry a single failed node within a run.
     */
    public function retryNode(int|WorkflowRun $run, int $nodeId): WorkflowRun
    {
        $run = $this->resolveRun($run);

        if ($run->status !== RunStatus::Failed) {
            throw new WorkflowException("Can only retry nodes in failed runs. Current status: {$run->status->value}");
        }

        return $this->executor->retryNode($run, $nodeId);
    }

    // ── CRUD ───────────────────────────────────────────────────────

    public function create(array $data): Workflow
    {
        $tagIds = $data['tag_ids'] ?? null;
        unset($data['tag_ids']);

        $workflow = Workflow::create($data);

        if ($tagIds !== null) {
            $workflow->tags()->sync($tagIds);
            $workflow->load('tags');
        }

        return $workflow;
    }

    public function update(int|Workflow $workflow, array $data): Workflow
    {
        $workflow = $this->resolveWorkflow($workflow);

        $tagIds = $data['tag_ids'] ?? null;
        unset($data['tag_ids']);

        $workflow->update($data);

        if ($tagIds !== null) {
            $workflow->tags()->sync($tagIds);
        }

        return $workflow->fresh();
    }

    public function delete(int|Workflow $workflow): void
    {
        $workflow = $this->resolveWorkflow($workflow);
        $workflow->delete();
    }

    public function duplicate(int|Workflow $workflow): Workflow
    {
        $workflow = $this->resolveWorkflow($workflow);

        $new = $workflow->replicate();
        $new->name = $workflow->name.' (Copy)';
        $new->is_active = false;
        $new->created_via = CreatedVia::Duplicate;
        $new->save();

        $nodeIdMap = [];
        foreach ($workflow->nodes as $node) {
            $newNode = $node->replicate();
            $newNode->workflow_id = $new->id;
            $newNode->save();
            $nodeIdMap[$node->id] = $newNode->id;
        }

        foreach ($workflow->edges as $edge) {
            $new->edges()->create([
                'source_node_id' => $nodeIdMap[$edge->source_node_id],
                'source_port' => $edge->source_port,
                'target_node_id' => $nodeIdMap[$edge->target_node_id],
                'target_port' => $edge->target_port,
            ]);
        }

        $new->tags()->sync($workflow->tags->pluck('id'));

        return $new->load(['nodes', 'edges', 'tags']);
    }

    // ── State ──────────────────────────────────────────────────────

    public function activate(int|Workflow $workflow): Workflow
    {
        $workflow = $this->resolveWorkflow($workflow);
        $workflow->update(['is_active' => true]);

        return $workflow->fresh();
    }

    public function deactivate(int|Workflow $workflow): Workflow
    {
        $workflow = $this->resolveWorkflow($workflow);
        $workflow->update(['is_active' => false]);

        return $workflow->fresh();
    }

    /**
     * Validate a workflow and return error messages.
     *
     * @return string[]
     */
    public function validate(int|Workflow $workflow): array
    {
        $workflow = $this->resolveWorkflow($workflow);

        return $this->validator->errors($workflow);
    }

    // ── Rate Limiting ──────────────────────────────────────────────

    /**
     * Check if a workflow can run within concurrency limits.
     */
    public function canRun(int|Workflow $workflow): bool
    {
        $workflow = $this->resolveWorkflow($workflow);

        return $this->concurrencyGuard->canRun($workflow);
    }

    /**
     * Get concurrency status for a workflow (global + per-workflow limits).
     */
    public function rateLimitStatus(int|Workflow $workflow): array
    {
        $workflow = $this->resolveWorkflow($workflow);

        return $this->concurrencyGuard->status($workflow);
    }

    // ── Builder helpers ────────────────────────────────────────────

    public function addNode(
        int|Workflow $workflow,
        string $nodeKey,
        array $config = [],
        ?string $name = null,
    ): WorkflowNode {
        $workflow = $this->resolveWorkflow($workflow);

        return $workflow->nodes()->create([
            'type' => app(\Aftandilmmd\WorkflowAutomation\Registry\NodeRegistry::class)->getMeta($nodeKey)['type'] ?? NodeType::Action,
            'node_key' => $nodeKey,
            'name' => $name,
            'config' => $config,
        ]);
    }

    public function connect(
        int|WorkflowNode $source,
        int|WorkflowNode $target,
        string $sourcePort = 'main',
        string $targetPort = 'main',
    ): WorkflowEdge {
        $sourceNodeId = $source instanceof WorkflowNode ? $source->id : $source;
        $targetNodeId = $target instanceof WorkflowNode ? $target->id : $target;
        $sourceNode = $source instanceof WorkflowNode ? $source : WorkflowNode::findOrFail($sourceNodeId);

        return WorkflowEdge::create([
            'workflow_id' => $sourceNode->workflow_id,
            'source_node_id' => $sourceNodeId,
            'source_port' => $sourcePort,
            'target_node_id' => $targetNodeId,
            'target_port' => $targetPort,
        ]);
    }

    public function removeNode(int $nodeId): void
    {
        WorkflowEdge::where('source_node_id', $nodeId)
            ->orWhere('target_node_id', $nodeId)
            ->delete();

        WorkflowNode::findOrFail($nodeId)->delete();
    }

    public function removeEdge(int $edgeId): void
    {
        WorkflowEdge::findOrFail($edgeId)->delete();
    }

    // ── Helpers ────────────────────────────────────────────────────

    private function resolveWorkflow(int|Workflow $workflow): Workflow
    {
        return $workflow instanceof Workflow ? $workflow : Workflow::findOrFail($workflow);
    }

    private function resolveRun(int|WorkflowRun $run): WorkflowRun
    {
        return $run instanceof WorkflowRun ? $run : WorkflowRun::findOrFail($run);
    }
}
