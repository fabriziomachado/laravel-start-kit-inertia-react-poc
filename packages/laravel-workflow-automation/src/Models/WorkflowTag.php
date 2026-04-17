<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Models;

use Aftandilmmd\WorkflowAutomation\Database\Factories\WorkflowTagFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class WorkflowTag extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function getTable(): string
    {
        return config('workflow-automation.tables.tags', 'workflow_tags');
    }

    public function workflows(): BelongsToMany
    {
        return $this->belongsToMany(
            config('workflow-automation.models.workflow', Workflow::class),
            config('workflow-automation.tables.tag_pivot', 'workflow_tag_pivot'),
            'tag_id',
            'workflow_id',
        );
    }

    protected static function newFactory(): WorkflowTagFactory
    {
        return WorkflowTagFactory::new();
    }
}
