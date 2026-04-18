<?php

declare(strict_types=1);

namespace App\Models;

use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class WorkflowDefinitionAudit extends Model
{
    protected $fillable = [
        'workflow_id',
        'user_id',
        'action',
        'snapshot',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
        ];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class, 'workflow_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
