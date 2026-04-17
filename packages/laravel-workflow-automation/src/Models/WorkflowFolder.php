<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Models;

use Aftandilmmd\WorkflowAutomation\Database\Factories\WorkflowFolderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class WorkflowFolder extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function getTable(): string
    {
        return config('workflow-automation.tables.folders', 'workflow_folders');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(
            config('workflow-automation.models.folder', self::class),
            'parent_id',
        );
    }

    public function children(): HasMany
    {
        return $this->hasMany(
            config('workflow-automation.models.folder', self::class),
            'parent_id',
        );
    }

    public function workflows(): HasMany
    {
        return $this->hasMany(
            config('workflow-automation.models.workflow', Workflow::class),
            'folder_id',
        );
    }

    public function ancestors(): array
    {
        $ancestors = [];
        $current = $this->parent;

        while ($current) {
            $ancestors[] = $current;
            $current = $current->parent;
        }

        return array_reverse($ancestors);
    }

    protected static function newFactory(): WorkflowFolderFactory
    {
        return WorkflowFolderFactory::new();
    }
}
