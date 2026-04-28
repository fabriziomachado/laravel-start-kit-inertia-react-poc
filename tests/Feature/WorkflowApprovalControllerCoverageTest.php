<?php

declare(strict_types=1);

use Aftandilmmd\WorkflowAutomation\Enums\RunStatus;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowRun;
use App\Support\WorkflowApprovalToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;

it('fallback devolve 409 quando token não bate', function (): void {
    $run = WorkflowRun::factory()->create(['status' => RunStatus::Waiting]);
    $nodeId = 123;
    $cacheKey = WorkflowApprovalToken::cacheKey($run->id, $nodeId);
    Cache::put($cacheKey, 'abc', 60);

    $url = URL::temporarySignedRoute('workflow-approvals.fallback', now()->addMinutes(5), [
        'run' => $run->id,
        'node' => $nodeId,
        'token' => 'wrong',
        'decision' => 'approve',
    ]);

    $this->get($url)->assertStatus(409);
});

it('submit devolve 422 quando decision inválida', function (): void {
    $run = WorkflowRun::factory()->create(['status' => RunStatus::Waiting]);
    $nodeId = 123;
    $cacheKey = WorkflowApprovalToken::cacheKey($run->id, $nodeId);
    Cache::put($cacheKey, 'tok', 60);

    $url = URL::temporarySignedRoute('workflow-approvals.submit', now()->addMinutes(5), [
        'run' => $run->id,
        'node' => $nodeId,
        'token' => 'tok',
    ]);

    $this->postJson($url, [
        'decision' => 'maybe',
        '__amp_source_origin' => config('app.url'),
    ])->assertStatus(422);
});

it('submit usa Origin google.com como Access-Control-Allow-Origin', function (): void {
    $run = WorkflowRun::factory()->create(['status' => RunStatus::Waiting]);
    $nodeId = 123;
    $cacheKey = WorkflowApprovalToken::cacheKey($run->id, $nodeId);
    Cache::put($cacheKey, 'tok', 60);

    // payload validado passa, mas o resume pode falhar por não existir nó.
    $url = URL::temporarySignedRoute('workflow-approvals.submit', now()->addMinutes(5), [
        'run' => $run->id,
        'node' => $nodeId,
        'token' => 'tok',
    ]);

    $resp = $this->withHeaders(['Origin' => 'https://mail.google.com'])
        ->postJson($url, [
            'decision' => 'approve',
            '__amp_source_origin' => config('app.url'),
        ]);

    $resp->assertHeader('Access-Control-Allow-Origin', 'https://mail.google.com');
});

