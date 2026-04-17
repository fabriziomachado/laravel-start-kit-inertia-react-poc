<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Models;

use Aftandilmmd\WorkflowAutomation\Database\Factories\WorkflowFactory;
use Aftandilmmd\WorkflowAutomation\Enums\CreatedVia;
use Aftandilmmd\WorkflowAutomation\Enums\NodeType;
use Aftandilmmd\WorkflowAutomation\Services\WorkflowService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Workflow extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    public function getTable(): string
    {
        return config('workflow-automation.tables.workflows', 'workflows');
    }

    public function nodes(): HasMany
    {
        return $this->hasMany(
            config('workflow-automation.models.node', WorkflowNode::class),
        );
    }

    public function edges(): HasMany
    {
        return $this->hasMany(
            config('workflow-automation.models.edge', WorkflowEdge::class),
        );
    }

    public function runs(): HasMany
    {
        return $this->hasMany(
            config('workflow-automation.models.run', WorkflowRun::class),
        );
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(
            config('workflow-automation.models.tag', WorkflowTag::class),
            config('workflow-automation.tables.tag_pivot', 'workflow_tag_pivot'),
            'workflow_id',
            'tag_id',
        );
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(
            config('workflow-automation.models.folder', WorkflowFolder::class),
            'folder_id',
        );
    }

    /**
     * Get the single trigger node for this workflow.
     */
    public function triggerNode(): ?WorkflowNode
    {
        return $this->nodes()->where('type', NodeType::Trigger)->first();
    }

    // ── Fluent API ──────────────────────────────────────────────

    public function addNode(string $name, string $nodeKey, array $config = []): WorkflowNode
    {
        return $this->service()->addNode($this, $nodeKey, $config, $name);
    }

    public function connect(
        int|WorkflowNode $source,
        int|WorkflowNode $target,
        string $sourcePort = 'main',
        string $targetPort = 'main',
    ): WorkflowEdge {
        return $this->service()->connect($source, $target, $sourcePort, $targetPort);
    }

    public function activate(): static
    {
        $this->service()->activate($this);
        $this->refresh();

        return $this;
    }

    public function deactivate(): static
    {
        $this->service()->deactivate($this);
        $this->refresh();

        return $this;
    }

    public function validateGraph(): array
    {
        return $this->service()->validate($this);
    }

    public function start(array $payload = []): WorkflowRun
    {
        return $this->service()->run($this, $payload);
    }

    public function startAsync(array $payload = []): void
    {
        $this->service()->runAsync($this, $payload);
    }

    public function duplicate(): self
    {
        return $this->service()->duplicate($this);
    }

    public function removeNode(int $nodeId): void
    {
        $this->service()->removeNode($nodeId);
    }

    public function removeEdge(int $edgeId): void
    {
        $this->service()->removeEdge($edgeId);
    }

    public function attachTags(array $tagIds): static
    {
        $this->tags()->sync($tagIds);
        $this->load('tags');

        return $this;
    }

    public function detachTags(array $tagIds = []): static
    {
        if (empty($tagIds)) {
            $this->tags()->detach();
        } else {
            $this->tags()->detach($tagIds);
        }

        $this->load('tags');

        return $this;
    }

    public function moveToFolder(int|WorkflowFolder|null $folder): static
    {
        $folderId = $folder instanceof WorkflowFolder ? $folder->id : $folder;
        $this->update(['folder_id' => $folderId]);
        $this->load('folder');

        return $this;
    }

    protected static function newFactory(): WorkflowFactory
    {
        return WorkflowFactory::new();
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'run_async' => 'boolean',
            'settings' => 'array',
            'created_via' => CreatedVia::class,
        ];
    }

    private function service(): WorkflowService
    {
        return app(WorkflowService::class);
    }
}
