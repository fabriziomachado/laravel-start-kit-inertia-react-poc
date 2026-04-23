<?php

declare(strict_types=1);

use Aftandilmmd\WorkflowAutomation\Enums\RunStatus;
use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowRun;
use App\Support\WorkflowFormProgress;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('inclui o passo email_approval no resumo de execução quando foi aprovado', function (): void {
    $workflow = Workflow::query()->create([
        'name' => 'Teste resumo email_approval (aprovado)',
        'description' => '',
        'is_active' => true,
    ]);

    $trigger = $workflow->addNode('Início', 'manual', []);
    $approval = $workflow->addNode('Aprovação por e-mail', 'email_approval', []);
    $approved = $workflow->addNode('Caminho aprovado', 'set_fields', []);
    $rejected = $workflow->addNode('Caminho rejeitado', 'set_fields', []);

    $trigger->connect($approval);
    $approval->connect($approved, sourcePort: 'approved');
    $approval->connect($rejected, sourcePort: 'rejected');

    $run = WorkflowRun::query()->create([
        'workflow_id' => $workflow->id,
        'status' => RunStatus::Completed,
        'trigger_node_id' => $trigger->id,
        'context' => [
            (string) $approval->id => [
                'approved' => [
                    [
                        'decision' => 'approve',
                        'comment' => 'OK, pode avançar',
                        'decided_at' => '2026-04-22T18:25:09+00:00',
                        'decided_by_email' => 'fcm@unesc.net',
                    ],
                ],
            ],
            (string) $approved->id => [
                'main' => [
                    ['result' => 'approved'],
                ],
            ],
        ],
    ]);

    $workflow->load(['nodes', 'edges']);
    $sections = WorkflowFormProgress::completedRunReadOnlySections($run, $workflow);

    $approvalSection = collect($sections)->firstWhere('heading', 'Aprovação por e-mail');
    expect($approvalSection)->not->toBeNull()
        ->and($approvalSection['lines'])->toContain('Decisão: Aprovado')
        ->and($approvalSection['lines'])->toContain('Comentário: OK, pode avançar')
        ->and($approvalSection['lines'])->toContain('Decidida por: fcm@unesc.net');

    // Ramo aprovado está listado; ramo rejeitado não aparece porque não foi executado
    $approvedSection = collect($sections)->firstWhere('heading', 'Caminho aprovado');
    expect($approvedSection)->not->toBeNull();

    $rejectedSection = collect($sections)->firstWhere('heading', 'Caminho rejeitado');
    expect($rejectedSection)->toBeNull();
});

it('inclui o passo email_approval no resumo de execução quando foi rejeitado', function (): void {
    $workflow = Workflow::query()->create([
        'name' => 'Teste resumo email_approval (rejeitado)',
        'description' => '',
        'is_active' => true,
    ]);

    $trigger = $workflow->addNode('Início', 'manual', []);
    $approval = $workflow->addNode('Aprovação por e-mail', 'email_approval', []);
    $approved = $workflow->addNode('Caminho aprovado', 'set_fields', []);
    $rejected = $workflow->addNode('Caminho rejeitado', 'set_fields', []);

    $trigger->connect($approval);
    $approval->connect($approved, sourcePort: 'approved');
    $approval->connect($rejected, sourcePort: 'rejected');

    $run = WorkflowRun::query()->create([
        'workflow_id' => $workflow->id,
        'status' => RunStatus::Completed,
        'trigger_node_id' => $trigger->id,
        'context' => [
            (string) $approval->id => [
                'rejected' => [
                    [
                        'decision' => 'reject',
                        'comment' => 'Falta informação',
                        'decided_at' => '2026-04-22T19:00:00+00:00',
                    ],
                ],
            ],
            (string) $rejected->id => [
                'main' => [
                    ['result' => 'rejected'],
                ],
            ],
        ],
    ]);

    $workflow->load(['nodes', 'edges']);
    $sections = WorkflowFormProgress::completedRunReadOnlySections($run, $workflow);

    $approvalSection = collect($sections)->firstWhere('heading', 'Aprovação por e-mail');
    expect($approvalSection)->not->toBeNull()
        ->and($approvalSection['lines'])->toContain('Decisão: Rejeitado')
        ->and($approvalSection['lines'])->toContain('Comentário: Falta informação');

    $rejectedSection = collect($sections)->firstWhere('heading', 'Caminho rejeitado');
    expect($rejectedSection)->not->toBeNull();

    $approvedSection = collect($sections)->firstWhere('heading', 'Caminho aprovado');
    expect($approvedSection)->toBeNull();
});
