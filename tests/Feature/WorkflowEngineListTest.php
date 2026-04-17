<?php

declare(strict_types=1);

use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowTag;
use Illuminate\Support\Facades\Gate;

it('lista cada workflow uma vez na index quando o workflow tem várias tags', function (): void {
    app()->detectEnvironment(fn (): string => 'local');
    Gate::define('viewWorkflowAutomation', fn ($user = null): bool => false);

    $workflow = Workflow::factory()->create();
    $tags = WorkflowTag::factory()->count(3)->create();
    $workflow->tags()->attach($tags->pluck('id'));

    $this->getJson('/workflow-engine/workflows')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $workflow->id);
});
