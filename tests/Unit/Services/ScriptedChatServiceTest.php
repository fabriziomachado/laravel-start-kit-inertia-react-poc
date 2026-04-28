<?php

declare(strict_types=1);

use Aftandilmmd\WorkflowAutomation\Models\WorkflowNodeRun;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowRun;
use App\Models\WorkflowFormConversation;
use App\Services\Workflow\ScriptedChatService;

it('não cria mensagem inicial se já houver mensagens', function (): void {
    $run = WorkflowRun::factory()->create();
    $nodeRun = WorkflowNodeRun::factory()->create(['workflow_run_id' => $run->id]);

    $conversation = WorkflowFormConversation::query()->create([
        'workflow_run_id' => $run->id,
        'workflow_node_run_id' => $nodeRun->id,
        'messages' => [
            ['role' => 'assistant', 'content' => 'Olá', 'meta' => ['at' => now()->toIso8601String()]],
        ],
    ]);

    $svc = new ScriptedChatService;
    $svc->ensureOpeningAssistant($conversation, [
        ['key' => 'name', 'label' => 'Nome', 'type' => 'string', 'required' => true],
    ]);

    $conversation->refresh();
    expect($conversation->messages)->toHaveCount(1);
});

it('não cria mensagem inicial quando não há campos', function (): void {
    $run = WorkflowRun::factory()->create();
    $nodeRun = WorkflowNodeRun::factory()->create(['workflow_run_id' => $run->id]);

    $conversation = WorkflowFormConversation::query()->create([
        'workflow_run_id' => $run->id,
        'workflow_node_run_id' => $nodeRun->id,
        'messages' => [],
    ]);

    $svc = new ScriptedChatService;
    $svc->ensureOpeningAssistant($conversation, []);

    $conversation->refresh();
    expect($conversation->messages)->toBe([]);
});

it('appendUserMessage devolve erro quando não há pergunta pendente', function (): void {
    $run = WorkflowRun::factory()->create();
    $nodeRun = WorkflowNodeRun::factory()->create(['workflow_run_id' => $run->id]);

    $conversation = WorkflowFormConversation::query()->create([
        'workflow_run_id' => $run->id,
        'workflow_node_run_id' => $nodeRun->id,
        'messages' => [],
    ]);

    $svc = new ScriptedChatService;
    $result = $svc->appendUserMessage($conversation, [
        ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
    ], 'a@a.com');

    expect($result['ok'])->toBeFalse();
    expect($result['errors'])->toHaveKey('chat');
});

it('appendUserMessage valida e avança para ready_for_submit no último campo', function (): void {
    $run = WorkflowRun::factory()->create();
    $nodeRun = WorkflowNodeRun::factory()->create(['workflow_run_id' => $run->id]);

    $conversation = WorkflowFormConversation::query()->create([
        'workflow_run_id' => $run->id,
        'workflow_node_run_id' => $nodeRun->id,
        'messages' => [],
    ]);

    $fields = [
        ['key' => 'accept_terms', 'label' => 'Aceita?', 'type' => 'boolean', 'required' => true],
    ];

    $svc = new ScriptedChatService;
    $svc->ensureOpeningAssistant($conversation, $fields);
    $conversation->refresh();

    $result = $svc->appendUserMessage($conversation, $fields, 'sim');
    expect($result['ok'])->toBeTrue();
    expect($svc->isReadyForSubmit($result['messages']))->toBeTrue();

    $draft = $svc->draftValuesFromMessages($fields, $result['messages']);
    expect($draft)->toHaveKey('accept_terms', true);
});

it('appendUserMessage devolve erros quando validação falha', function (): void {
    $run = WorkflowRun::factory()->create();
    $nodeRun = WorkflowNodeRun::factory()->create(['workflow_run_id' => $run->id]);

    $conversation = WorkflowFormConversation::query()->create([
        'workflow_run_id' => $run->id,
        'workflow_node_run_id' => $nodeRun->id,
        'messages' => [],
    ]);

    $fields = [
        ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
    ];

    $svc = new ScriptedChatService;
    $svc->ensureOpeningAssistant($conversation, $fields);
    $conversation->refresh();

    $result = $svc->appendUserMessage($conversation, $fields, 'email-invalido');
    expect($result['ok'])->toBeFalse();
    expect($result['errors'])->toHaveKey('email');
});

it('replaceUserMessage permite editar resposta e marca meta edited', function (): void {
    $run = WorkflowRun::factory()->create();
    $nodeRun = WorkflowNodeRun::factory()->create(['workflow_run_id' => $run->id]);

    $conversation = WorkflowFormConversation::query()->create([
        'workflow_run_id' => $run->id,
        'workflow_node_run_id' => $nodeRun->id,
        'messages' => [],
    ]);

    $fields = [
        ['key' => 'age', 'label' => 'Idade', 'type' => 'number', 'required' => false],
    ];

    $svc = new ScriptedChatService;
    $svc->ensureOpeningAssistant($conversation, $fields);
    $conversation->refresh();

    $result1 = $svc->appendUserMessage($conversation, $fields, '10');
    expect($result1['ok'])->toBeTrue();

    $conversation->refresh();
    $result2 = $svc->replaceUserMessage($conversation, $fields, 'age', '12');
    expect($result2['ok'])->toBeTrue();

    $messages = $result2['messages'];
    $lastUser = collect($messages)->last(fn ($m) => is_array($m) && ($m['role'] ?? null) === 'user');
    expect($lastUser)->toBeArray();
    expect($lastUser['content'])->toBe('12');
    expect($lastUser['meta']['edited'])->toBeTrue();
});

it('replaceUserMessage devolve erro quando não encontra resposta para editar', function (): void {
    $run = WorkflowRun::factory()->create();
    $nodeRun = WorkflowNodeRun::factory()->create(['workflow_run_id' => $run->id]);

    $conversation = WorkflowFormConversation::query()->create([
        'workflow_run_id' => $run->id,
        'workflow_node_run_id' => $nodeRun->id,
        'messages' => [
            ['role' => 'assistant', 'content' => 'Pergunta?', 'meta' => ['expecting_field' => 'x', 'at' => now()->toIso8601String()]],
        ],
    ]);

    $svc = new ScriptedChatService;
    $result = $svc->replaceUserMessage($conversation, [
        ['key' => 'name', 'label' => 'Nome', 'type' => 'string', 'required' => true],
    ], 'name', 'Ana');

    expect($result['ok'])->toBeFalse();
    expect($result['errors'])->toHaveKey('chat');
});

