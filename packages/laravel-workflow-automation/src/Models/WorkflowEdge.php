<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Models;

use Aftandilmmd\WorkflowAutomation\Database\Factories\WorkflowEdgeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class WorkflowEdge extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function getTable(): string
    {
        return config('workflow-automation.tables.edges', 'workflow_edges');
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(
            config('workflow-automation.models.workflow', Workflow::class),
        );
    }

    public function sourceNode(): BelongsTo
    {
        return $this->belongsTo(
            config('workflow-automation.models.node', WorkflowNode::class),
            'source_node_id',
        );
    }

    public function targetNode(): BelongsTo
    {
        return $this->belongsTo(
            config('workflow-automation.models.node', WorkflowNode::class),
            'target_node_id',
        );
    }

    protected static function newFactory(): WorkflowEdgeFactory
    {
        return WorkflowEdgeFactory::new();
    }
}
