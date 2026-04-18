<?php

declare(strict_types=1);

namespace App\Observers;

use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use App\Models\WorkflowDefinitionAudit;

final class WorkflowDefinitionObserver
{
    public function created(Workflow $workflow): void
    {
        $this->record($workflow, 'created');
    }

    public function updated(Workflow $workflow): void
    {
        if (! $workflow->wasChanged()) {
            return;
        }

        $this->record($workflow, 'updated');
    }

    private function record(Workflow $workflow, string $action): void
    {
        WorkflowDefinitionAudit::query()->create([
            'workflow_id' => $workflow->id,
            'user_id' => auth()->id(),
            'action' => $action,
            'snapshot' => $workflow->only(['name', 'description', 'is_active', 'slug', 'settings', 'run_async']),
        ]);
    }
}
