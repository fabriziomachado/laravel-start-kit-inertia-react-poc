<?php

declare(strict_types=1);

use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowNode;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowNodeRun;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowRun;
use Illuminate\Support\Facades\Gate;

it('remove um workflow e apaga runs e nós associados', function (): void {
    app()->detectEnvironment(fn (): string => 'local');
    Gate::define('viewWorkflowAutomation', fn ($user = null): bool => false);

    $workflow = Workflow::factory()->create();
    $node = WorkflowNode::factory()->trigger()->create(['workflow_id' => $workflow->id]);
    $run = WorkflowRun::factory()->create([
        'workflow_id' => $workflow->id,
        'trigger_node_id' => $node->id,
    ]);
    WorkflowNodeRun::factory()->create([
        'workflow_run_id' => $run->id,
        'node_id' => $node->id,
    ]);

    $this->deleteJson("/workflow-engine/workflows/{$workflow->id}")
        ->assertOk();

    $this->assertDatabaseMissing('workflows', ['id' => $workflow->id]);
    $this->assertDatabaseMissing(config('workflow-automation.tables.runs', 'workflow_runs'), ['id' => $run->id]);
    $this->assertDatabaseMissing(config('workflow-automation.tables.nodes', 'workflow_nodes'), ['id' => $node->id]);
});
