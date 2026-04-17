<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Models;

use Aftandilmmd\WorkflowAutomation\Database\Factories\WorkflowNodeRunFactory;
use Aftandilmmd\WorkflowAutomation\Enums\NodeRunStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class WorkflowNodeRun extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function getTable(): string
    {
        return config('workflow-automation.tables.node_runs', 'workflow_node_runs');
    }

    public function workflowRun(): BelongsTo
    {
        return $this->belongsTo(
            config('workflow-automation.models.run', WorkflowRun::class),
            'workflow_run_id',
        );
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(
            config('workflow-automation.models.node', WorkflowNode::class),
            'node_id',
        );
    }

    protected static function newFactory(): WorkflowNodeRunFactory
    {
        return WorkflowNodeRunFactory::new();
    }

    protected function casts(): array
    {
        return [
            'status' => NodeRunStatus::class,
            'input' => 'array',
            'output' => 'array',
            'executed_at' => 'datetime',
        ];
    }
}
