<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Models;

use Aftandilmmd\WorkflowAutomation\Database\Factories\WorkflowNodeFactory;
use Aftandilmmd\WorkflowAutomation\Enums\NodeType;
use Aftandilmmd\WorkflowAutomation\Services\WorkflowService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class WorkflowNode extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function getTable(): string
    {
        return config('workflow-automation.tables.nodes', 'workflow_nodes');
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(
            config('workflow-automation.models.workflow', Workflow::class),
        );
    }

    public function outgoingEdges(): HasMany
    {
        return $this->hasMany(
            config('workflow-automation.models.edge', WorkflowEdge::class),
            'source_node_id',
        );
    }

    public function incomingEdges(): HasMany
    {
        return $this->hasMany(
            config('workflow-automation.models.edge', WorkflowEdge::class),
            'target_node_id',
        );
    }

    public function nodeRuns(): HasMany
    {
        return $this->hasMany(
            config('workflow-automation.models.node_run', WorkflowNodeRun::class),
            'node_id',
        );
    }

    // ── Pinned Test Data ───────────────────────────────────────

    public function hasPinnedInput(): bool
    {
        return ! empty($this->pinned_data['input']);
    }

    public function hasPinnedOutput(): bool
    {
        return ! empty($this->pinned_data['output']);
    }

    public function getPinnedInput(): ?array
    {
        return $this->pinned_data['input'] ?? null;
    }

    public function getPinnedOutput(): ?array
    {
        return $this->pinned_data['output'] ?? null;
    }

    // ── Fluent API ──────────────────────────────────────────────

    public function addNode(string $name, string $nodeKey, array $config = []): self
    {
        return $this->service()->addNode($this->workflow_id, $nodeKey, $config, $name);
    }

    /**
     * Connect this node to a target node. Returns the TARGET node for chaining.
     */
    public function connect(
        int|self $target,
        string $sourcePort = 'main',
        string $targetPort = 'main',
    ): self {
        $targetNode = $target instanceof self ? $target : self::findOrFail($target);

        $this->service()->connect($this, $targetNode, $sourcePort, $targetPort);

        return $targetNode;
    }

    public function activate(): Workflow
    {
        return $this->workflow->activate();
    }

    public function deactivate(): Workflow
    {
        return $this->workflow->deactivate();
    }

    public function run(array $payload = []): WorkflowRun
    {
        return $this->workflow->start($payload);
    }

    public function validateGraph(): array
    {
        return $this->workflow->validateGraph();
    }

    protected static function newFactory(): WorkflowNodeFactory
    {
        return WorkflowNodeFactory::new();
    }

    protected function casts(): array
    {
        return [
            'type' => NodeType::class,
            'config' => 'array',
            'pinned_data' => 'array',
        ];
    }

    private function service(): WorkflowService
    {
        return app(WorkflowService::class);
    }
}
