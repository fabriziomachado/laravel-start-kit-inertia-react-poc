<?php

declare(strict_types=1);

namespace App\Models;

use Aftandilmmd\WorkflowAutomation\Models\WorkflowNodeRun;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowRun;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read int $id
 * @property-read int $workflow_run_id
 * @property-read int $workflow_node_run_id
 * @property list<array<string, mixed>> $messages
 */
final class WorkflowFormConversation extends Model
{
    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'messages' => 'array',
        ];
    }

    public function workflowRun(): BelongsTo
    {
        return $this->belongsTo(WorkflowRun::class, 'workflow_run_id');
    }

    public function workflowNodeRun(): BelongsTo
    {
        return $this->belongsTo(WorkflowNodeRun::class, 'workflow_node_run_id');
    }
}
