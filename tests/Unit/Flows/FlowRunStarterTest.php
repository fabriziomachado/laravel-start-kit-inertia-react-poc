<?php

declare(strict_types=1);

use Aftandilmmd\WorkflowAutomation\Enums\RunStatus;
use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowNodeRun;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowRun;
use Aftandilmmd\WorkflowAutomation\Services\WorkflowService;
use App\Flows\FlowRunStarter;
use App\Models\User;
use Database\Seeders\WorkflowEmailApprovalDemoSeeder;
use Database\Seeders\WorkflowFormWizardExampleSeeder;

it('startOrRedirectToForm devolve erro quando run não fica em Waiting', function (): void {
    $workflow = Workflow::factory()->create(['is_active' => true]);
    $user = User::factory()->create();

    // Um workflow sem nós tende a concluir imediatamente.
    $svc = app(WorkflowService::class);

    $starter = new FlowRunStarter($svc);
    $resp = $starter->startOrRedirectToForm($workflow, $user, 'flows.index');

    expect($resp->getStatusCode())->toBe(302);
});

it('startOrRedirectToForm devolve erro quando não há resume_token', function (): void {
    $user = User::factory()->create();

    $this->seed(WorkflowEmailApprovalDemoSeeder::class);
    $workflow = Workflow::query()->where('name', WorkflowEmailApprovalDemoSeeder::WORKFLOW_NAME)->firstOrFail();

    $svc = app(WorkflowService::class);

    $starter = new FlowRunStarter($svc);
    $resp = $starter->startOrRedirectToForm($workflow, $user, 'flows.index', context: ['student_id' => 1]);

    expect($resp->getStatusCode())->toBe(302);
});

it('startOrRedirectToForm redireciona para workflow-forms.show quando há token', function (): void {
    $user = User::factory()->create();

    $this->seed(WorkflowFormWizardExampleSeeder::class);
    $workflow = Workflow::query()->where('name', WorkflowFormWizardExampleSeeder::WORKFLOW_NAME)->firstOrFail();

    $svc = app(WorkflowService::class);

    $starter = new FlowRunStarter($svc);
    $resp = $starter->startOrRedirectToForm($workflow, $user, 'flows.index');

    expect($resp->getStatusCode())->toBe(302);
    expect((string) $resp->headers->get('Location'))->toContain('workflow-forms/');
});

