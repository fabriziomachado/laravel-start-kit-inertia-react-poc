<?php

declare(strict_types=1);

use Aftandilmmd\WorkflowAutomation\Models\WorkflowRun;
use Illuminate\Support\Facades\URL;

it('renderiza a view AMP válida para AMP4EMAIL sem campo reservado __amp_source_origin', function (): void {
    $action = 'https://app.example.test/workflow-approvals/1/2/submit?signature=abc';

    $html = view('emails.workflow.approval-amp', [
        'title' => 'Confirmação',
        'body' => "Linha 1\nLinha 2",
        'approveLabel' => 'Aprovar',
        'rejectLabel' => 'Rejeitar',
        'askComment' => true,
        'actionUrl' => $action,
        'ampSourceOrigin' => 'https://app.example.test',
    ])->render();

    expect($html)
        ->toContain('⚡4email')
        ->toContain('amp-form')
        ->toContain('action-xhr="'.$action.'"')
        ->toContain('name="comment"')
        ->toContain('value="approve"')
        ->toContain('value="reject"')
        // Nomes __amp* são reservados pelo runtime e rejeitados pelo validador
        // AMP4EMAIL. O runtime acrescenta __amp_source_origin automaticamente ao
        // query string do action-xhr (ver AMP CORS spec), não deve existir no form.
        ->not->toContain('name="__amp_source_origin"');
});

it('renderiza texto e HTML de fallback com links GET', function (): void {
    $approve = 'https://app.example.test/workflow-approvals/1/2/approve?signature=a';
    $reject = 'https://app.example.test/workflow-approvals/1/2/reject?signature=b';

    $plain = view('emails.workflow.approval-text', [
        'title' => 'Assunto interno',
        'body' => 'Corpo',
        'approveLabel' => 'Sim',
        'rejectLabel' => 'Não',
        'approveGetUrl' => $approve,
        'rejectGetUrl' => $reject,
    ])->render();

    expect($plain)->toContain($approve)->toContain($reject)->not->toContain('AMP for Email');

    $html = view('emails.workflow.approval-html', [
        'title' => 'Assunto interno',
        'body' => 'Corpo',
        'approveLabel' => 'Sim',
        'rejectLabel' => 'Não',
        'approveGetUrl' => $approve,
        'rejectGetUrl' => $reject,
    ])->render();

    expect($html)->toContain('href="'.$approve.'"')->toContain('href="'.$reject.'"');
});

it('aceita GET assinado na rota de fallback approve ou reject', function (): void {
    $run = WorkflowRun::factory()->waiting()->create();
    $token = '00000000-0000-4000-8000-000000000010';
    Illuminate\Support\Facades\Cache::put(
        App\Support\WorkflowApprovalToken::cacheKey($run->id, 3),
        $token,
        now()->addHour(),
    );

    $url = URL::temporarySignedRoute(
        'workflow-approvals.fallback',
        now()->addHour(),
        ['run' => $run->id, 'node' => 3, 'token' => $token, 'decision' => 'approve'],
    );

    $this->get($url)
        ->assertOk()
        ->assertSee('Obrigado', false);
});

it('rejeita GET fallback com decision inválida na rota', function (): void {
    $run = WorkflowRun::factory()->waiting()->create();

    $this->get("/workflow-approvals/{$run->id}/1/bad-token/invalid-decision")
        ->assertNotFound();
});
