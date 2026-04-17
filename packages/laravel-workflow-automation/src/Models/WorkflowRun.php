<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Models;

use Aftandilmmd\WorkflowAutomation\Database\Factories\WorkflowRunFactory;
use Aftandilmmd\WorkflowAutomation\Enums\RunStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class WorkflowRun extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function getTable(): string
    {
        return config('workflow-automation.tables.runs', 'workflow_runs');
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(
            config('workflow-automation.models.workflow', Workflow::class),
        );
    }

    public function triggerNode(): BelongsTo
    {
        return $this->belongsTo(
            config('workflow-automation.models.node', WorkflowNode::class),
            'trigger_node_id',
        );
    }

    public function nodeRuns(): HasMany
    {
        return $this->hasMany(
            config('workflow-automation.models.node_run', WorkflowNodeRun::class),
            'workflow_run_id',
        );
    }

    public function parentRun(): BelongsTo
    {
        return $this->belongsTo(
            config('workflow-automation.models.run', self::class),
            'parent_run_id',
        );
    }

    public function childRuns(): HasMany
    {
        return $this->hasMany(
            config('workflow-automation.models.run', self::class),
            'parent_run_id',
        );
    }

    public function isRunning(): bool
    {
        return $this->status === RunStatus::Running;
    }

    public function isWaiting(): bool
    {
        return $this->status === RunStatus::Waiting;
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [
            RunStatus::Completed,
            RunStatus::Failed,
            RunStatus::Cancelled,
        ]);
    }

    protected static function newFactory(): WorkflowRunFactory
    {
        return WorkflowRunFactory::new();
    }

    protected function casts(): array
    {
        return [
            'status' => RunStatus::class,
            'initial_payload' => 'array',
            'context' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
