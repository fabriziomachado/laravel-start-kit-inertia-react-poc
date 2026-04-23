<?php

declare(strict_types=1);

use Aftandilmmd\WorkflowAutomation\Models\WorkflowRun;
use App\Support\WorkflowApprovalToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;

it('rejeita POST sem assinatura válida na rota de aprovação', function (): void {
    $run = WorkflowRun::factory()->waiting()->create();

    $this->post("/workflow-approvals/{$run->id}/1/00000000-0000-4000-8000-000000000000/submit", [
        'decision' => 'approve',
        '__amp_source_origin' => mb_rtrim((string) config('app.url'), '/'),
    ])->assertForbidden();
});

it('aceita POST com URL temporária assinada, token em cache e devolve JSON com headers CORS mínimos AMP', function (): void {
    $run = WorkflowRun::factory()->waiting()->create();
    $token = '00000000-0000-4000-8000-000000000001';
    Cache::put(WorkflowApprovalToken::cacheKey($run->id, 7), $token, now()->addHour());

    $url = URL::temporarySignedRoute(
        'workflow-approvals.submit',
        now()->addHour(),
        ['run' => $run->id, 'node' => 7, 'token' => $token],
    );

    $appUrl = mb_rtrim((string) config('app.url'), '/');

    $this->post($url, [
        'decision' => 'approve',
        '__amp_source_origin' => $appUrl,
    ])
        ->assertOk()
        ->assertJson([
            'ok' => true,
            'message' => 'Resposta registrada',
        ])
        ->assertHeader('Access-Control-Allow-Credentials', 'true')
        ->assertHeader('Access-Control-Expose-Headers', 'AMP-Access-Control-Allow-Source-Origin')
        ->assertHeader('AMP-Access-Control-Allow-Source-Origin', $appUrl);
});

it('ecoa Origin de domínio Google em Access-Control-Allow-Origin', function (): void {
    $run = WorkflowRun::factory()->waiting()->create();
    $token = '00000000-0000-4000-8000-000000000002';
    Cache::put(WorkflowApprovalToken::cacheKey($run->id, 1), $token, now()->addHour());

    $url = URL::temporarySignedRoute(
        'workflow-approvals.submit',
        now()->addHour(),
        ['run' => $run->id, 'node' => 1, 'token' => $token],
    );

    $this->withHeaders(['Origin' => 'https://mail.google.com'])
        ->post($url, [
            'decision' => 'approve',
            '__amp_source_origin' => mb_rtrim((string) config('app.url'), '/'),
        ])
        ->assertOk()
        ->assertHeader('Access-Control-Allow-Origin', 'https://mail.google.com');
});

it('rejeita __amp_source_origin inválido', function (): void {
    $run = WorkflowRun::factory()->waiting()->create();
    $token = '00000000-0000-4000-8000-000000000003';
    Cache::put(WorkflowApprovalToken::cacheKey($run->id, 2), $token, now()->addHour());

    $url = URL::temporarySignedRoute(
        'workflow-approvals.submit',
        now()->addHour(),
        ['run' => $run->id, 'node' => 2, 'token' => $token],
    );

    $this->post($url, [
        'decision' => 'approve',
        '__amp_source_origin' => 'https://evil.example',
    ])->assertForbidden()
        ->assertJsonPath('error', 'Origem inválida.');
});

it('rejeita quando o token em cache não coincide', function (): void {
    $run = WorkflowRun::factory()->waiting()->create();
    Cache::put(WorkflowApprovalToken::cacheKey($run->id, 3), 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', now()->addHour());

    $url = URL::temporarySignedRoute(
        'workflow-approvals.submit',
        now()->addHour(),
        [
            'run' => $run->id,
            'node' => 3,
            'token' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
        ],
    );

    $this->post($url, [
        'decision' => 'approve',
        '__amp_source_origin' => mb_rtrim((string) config('app.url'), '/'),
    ])->assertStatus(409);
});

it('rejeita quando o run não está em Waiting', function (): void {
    $run = WorkflowRun::factory()->completed()->create();
    $token = '00000000-0000-4000-8000-000000000004';
    Cache::put(WorkflowApprovalToken::cacheKey($run->id, 4), $token, now()->addHour());

    $url = URL::temporarySignedRoute(
        'workflow-approvals.submit',
        now()->addHour(),
        ['run' => $run->id, 'node' => 4, 'token' => $token],
    );

    $this->post($url, [
        'decision' => 'approve',
        '__amp_source_origin' => mb_rtrim((string) config('app.url'), '/'),
    ])->assertStatus(409);
});

it('rejeita URL assinada expirada', function (): void {
    $run = WorkflowRun::factory()->waiting()->create();
    $token = '00000000-0000-4000-8000-000000000005';
    Cache::put(WorkflowApprovalToken::cacheKey($run->id, 5), $token, now()->addHour());

    $url = URL::temporarySignedRoute(
        'workflow-approvals.submit',
        now()->subMinute(),
        ['run' => $run->id, 'node' => 5, 'token' => $token],
    );

    $this->post($url, [
        'decision' => 'approve',
        '__amp_source_origin' => mb_rtrim((string) config('app.url'), '/'),
    ])->assertForbidden();
});

it('segundo POST com o mesmo link devolve 409 após consumir o token', function (): void {
    $run = WorkflowRun::factory()->waiting()->create();
    $token = '00000000-0000-4000-8000-000000000006';
    Cache::put(WorkflowApprovalToken::cacheKey($run->id, 6), $token, now()->addHour());

    $url = URL::temporarySignedRoute(
        'workflow-approvals.submit',
        now()->addHour(),
        ['run' => $run->id, 'node' => 6, 'token' => $token],
    );

    $body = [
        'decision' => 'approve',
        '__amp_source_origin' => mb_rtrim((string) config('app.url'), '/'),
    ];

    $this->post($url, $body)->assertOk();
    $this->post($url, $body)->assertStatus(409);
});

it('aceita GET de fallback quando a query string traz &amp; literal (cópia a partir de texto plano)', function (): void {
    $run = WorkflowRun::factory()->waiting()->create();
    $token = '00000000-0000-4000-8000-000000000011';
    Cache::put(WorkflowApprovalToken::cacheKey($run->id, 8), $token, now()->addHour());

    $url = URL::temporarySignedRoute(
        'workflow-approvals.fallback',
        now()->addHour(),
        ['run' => $run->id, 'node' => 8, 'token' => $token, 'decision' => 'approve'],
    );

    $parts = parse_url($url);
    expect($parts)->toHaveKey('query');
    $brokenQuery = str_replace('&', '&amp;', (string) $parts['query']);
    $brokenUrl = ($parts['scheme'] ?? 'http').'://'.($parts['host'] ?? '')
        .($parts['path'] ?? '').'?'.$brokenQuery;

    $this->get($brokenUrl)->assertOk()->assertSee('Obrigado', false);
});
