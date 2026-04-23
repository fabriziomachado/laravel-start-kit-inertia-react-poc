<?php

declare(strict_types=1);

use Aftandilmmd\WorkflowAutomation\Engine\GraphExecutor;
use Aftandilmmd\WorkflowAutomation\Enums\RunStatus;
use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use App\Mail\WorkflowApprovalAmpMail;
use App\Support\WorkflowApprovalToken;
use Database\Seeders\WorkflowEmailApprovalDemoSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

it('executa o fluxo demo até Waiting e envia WorkflowApprovalAmpMail', function (): void {
    Mail::fake();
    $this->seed(WorkflowEmailApprovalDemoSeeder::class);

    $workflow = Workflow::query()->where('name', WorkflowEmailApprovalDemoSeeder::WORKFLOW_NAME)->firstOrFail();
    $run = app(GraphExecutor::class)->execute($workflow, [[]]);

    expect($run->fresh()->status)->toBe(RunStatus::Waiting);

    Mail::assertSent(WorkflowApprovalAmpMail::class, function (WorkflowApprovalAmpMail $mail): bool {
        return str_contains($mail->subjectLine, 'Aprovação');
    });
});

it('retoma o fluxo por POST approve e conclui o run', function (): void {
    Mail::fake();
    $this->seed(WorkflowEmailApprovalDemoSeeder::class);

    $workflow = Workflow::query()->where('name', WorkflowEmailApprovalDemoSeeder::WORKFLOW_NAME)->firstOrFail();
    $emailNode = $workflow->nodes()->where('node_key', 'email_approval')->firstOrFail();

    $run = app(GraphExecutor::class)->execute($workflow, [[]]);
    expect($run->fresh()->status)->toBe(RunStatus::Waiting);

    $token = Cache::get(WorkflowApprovalToken::cacheKey($run->id, $emailNode->id));
    expect($token)->toBeString();

    $url = URL::temporarySignedRoute(
        'workflow-approvals.submit',
        now()->addHours(168),
        ['run' => $run->id, 'node' => $emailNode->id, 'token' => $token],
    );

    $appUrl = mb_rtrim((string) config('app.url'), '/');

    $this->post($url, [
        'decision' => 'approve',
        'comment' => 'OK para continuar',
        '__amp_source_origin' => $appUrl,
    ])->assertOk()->assertJson(['ok' => true]);

    expect($run->fresh()->status)->toBe(RunStatus::Completed);
});

it('ordena as partes do multipart/alternative como plain → html → amp (preferência crescente)', function (): void {
    config()->set('mail.default', 'array');

    $mail = new WorkflowApprovalAmpMail(
        subjectLine: '[PoC] Aprovação',
        emailApprovalPayload: [
            'title' => 'Confirma?',
            'body' => 'Conteúdo',
            'approveLabel' => 'Aprovar',
            'rejectLabel' => 'Rejeitar',
            'askComment' => false,
            'actionUrl' => 'https://example.test/submit',
            'ampSourceOrigin' => 'https://example.test',
            'approveGetUrl' => 'https://example.test/approve',
            'rejectGetUrl' => 'https://example.test/reject',
        ],
    );

    Mail::to('dest@example.test')->send($mail);

    /** @var Symfony\Component\Mailer\Transport\NullTransport|Symfony\Component\Mailer\Transport\TransportInterface $transport */
    $transport = app('mail.manager')->mailer('array')->getSymfonyTransport();
    $messages = $transport->messages();

    expect($messages)->toHaveCount(1);

    $symfonyEmail = $messages[0]->getOriginalMessage();
    $body = $symfonyEmail->getBody();

    expect($body)->toBeInstanceOf(Symfony\Component\Mime\Part\Multipart\AlternativePart::class);

    $parts = $body->getParts();
    $mediaTypes = array_map(
        static fn (Symfony\Component\Mime\Part\TextPart $p): string => $p->getMediaType().'/'.$p->getMediaSubtype(),
        $parts,
    );

    expect($mediaTypes)->toBe(['text/plain', 'text/html', 'text/x-amp-html']);
});

it('retoma o fluxo por POST reject', function (): void {
    Mail::fake();
    $this->seed(WorkflowEmailApprovalDemoSeeder::class);

    $workflow = Workflow::query()->where('name', WorkflowEmailApprovalDemoSeeder::WORKFLOW_NAME)->firstOrFail();
    $emailNode = $workflow->nodes()->where('node_key', 'email_approval')->firstOrFail();

    $run = app(GraphExecutor::class)->execute($workflow, [[]]);

    $token = Cache::get(WorkflowApprovalToken::cacheKey($run->id, $emailNode->id));
    expect($token)->toBeString();

    $url = URL::temporarySignedRoute(
        'workflow-approvals.submit',
        now()->addHours(168),
        ['run' => $run->id, 'node' => $emailNode->id, 'token' => $token],
    );

    $appUrl = mb_rtrim((string) config('app.url'), '/');

    $this->post($url, [
        'decision' => 'reject',
        '__amp_source_origin' => $appUrl,
    ])->assertOk();

    expect($run->fresh()->status)->toBe(RunStatus::Completed);
});
