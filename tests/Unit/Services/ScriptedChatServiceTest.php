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

it('pergunta usa ui_hints.ask e converte ponto/colon em interrogação', function (): void {
    $run = WorkflowRun::factory()->create();
    $nodeRun = WorkflowNodeRun::factory()->create(['workflow_run_id' => $run->id]);

    $conversation = WorkflowFormConversation::query()->create([
        'workflow_run_id' => $run->id,
        'workflow_node_run_id' => $nodeRun->id,
        'messages' => [],
    ]);

    $svc = new ScriptedChatService;
    $svc->ensureOpeningAssistant($conversation, [
        ['key' => 'name', 'label' => 'Nome', 'type' => 'string', 'required' => true, 'ui_hints' => ['ask' => 'Qual o nome.']],
    ]);

    $conversation->refresh();
    expect((string) ($conversation->messages[0]['content'] ?? ''))->toEndWith('?');
});

it('normaliza boolean (on) e number vazio', function (): void {
    $run = WorkflowRun::factory()->create();
    $nodeRun = WorkflowNodeRun::factory()->create(['workflow_run_id' => $run->id]);

    $conversation = WorkflowFormConversation::query()->create([
        'workflow_run_id' => $run->id,
        'workflow_node_run_id' => $nodeRun->id,
        'messages' => [],
    ]);

    $fields = [
        ['key' => 'flag', 'label' => 'Flag', 'type' => 'boolean', 'required' => false],
        ['key' => 'qty', 'label' => 'Qtd', 'type' => 'number', 'required' => false],
    ];

    $svc = new ScriptedChatService;
    $svc->ensureOpeningAssistant($conversation, $fields);
    $conversation->refresh();

    // Responde primeiro campo boolean
    $r1 = $svc->appendUserMessage($conversation, $fields, 'on');
    expect($r1['ok'])->toBeTrue();

    // Agora expecting_field é qty, passa string vazia (deve manter '')
    $conversation->refresh();
    $r2 = $svc->appendUserMessage($conversation, $fields, '');
    expect($r2['ok'])->toBeTrue();
});

it('inclui meta field_options/field_choices em respostas do utilizador', function (): void {
    $run = WorkflowRun::factory()->create();
    $nodeRun = WorkflowNodeRun::factory()->create(['workflow_run_id' => $run->id]);

    $conversation = WorkflowFormConversation::query()->create([
        'workflow_run_id' => $run->id,
        'workflow_node_run_id' => $nodeRun->id,
        'messages' => [],
    ]);

    $fields = [
        ['key' => 'forma', 'label' => 'Forma', 'type' => 'select', 'required' => true, 'options' => 'a,b'],
        ['key' => 'ing', 'label' => 'Ing', 'type' => 'choice_cards', 'required' => true, 'choices' => [['value' => 'v1', 'label' => 'Um']]],
    ];

    $svc = new ScriptedChatService;
    $svc->ensureOpeningAssistant($conversation, $fields);
    $conversation->refresh();

    $r1 = $svc->appendUserMessage($conversation, $fields, 'a');
    expect($r1['ok'])->toBeTrue();
    $lastUser1 = collect($r1['messages'])->last(fn ($m) => is_array($m) && ($m['role'] ?? null) === 'user');
    expect($lastUser1['meta'])->toHaveKey('field_options');

    $conversation->refresh();
    $r2 = $svc->appendUserMessage($conversation, $fields, 'v1');
    expect($r2['ok'])->toBeTrue();
    $lastUser2 = collect($r2['messages'])->last(fn ($m) => is_array($m) && ($m['role'] ?? null) === 'user');
    expect($lastUser2['meta'])->toHaveKey('field_choices');
});

it('appendUserMessage devolve erro quando expecting_field não existe nos fields', function (): void {
    $run = WorkflowRun::factory()->create();
    $nodeRun = WorkflowNodeRun::factory()->create(['workflow_run_id' => $run->id]);

    $conversation = WorkflowFormConversation::query()->create([
        'workflow_run_id' => $run->id,
        'workflow_node_run_id' => $nodeRun->id,
        'messages' => [
            ['role' => 'assistant', 'content' => 'Pergunta?', 'meta' => ['expecting_field' => 'missing', 'at' => now()->toIso8601String()]],
        ],
    ]);

    $svc = new ScriptedChatService;
    $result = $svc->appendUserMessage($conversation, [
        ['key' => 'name', 'label' => 'Nome', 'type' => 'string', 'required' => true],
    ], 'Ana');

    expect($result['ok'])->toBeFalse();
    expect($result['errors']['chat'][0] ?? null)->toContain('Campo inválido');
});

it('replaceUserMessage devolve erro quando fieldKey é inválido', function (): void {
    $run = WorkflowRun::factory()->create();
    $nodeRun = WorkflowNodeRun::factory()->create(['workflow_run_id' => $run->id]);

    $conversation = WorkflowFormConversation::query()->create([
        'workflow_run_id' => $run->id,
        'workflow_node_run_id' => $nodeRun->id,
        'messages' => [],
    ]);

    $svc = new ScriptedChatService;
    $result = $svc->replaceUserMessage($conversation, [
        ['key' => 'name', 'label' => 'Nome', 'type' => 'string', 'required' => true],
    ], 'nao-existe', 'x');

    expect($result['ok'])->toBeFalse();
    expect($result['errors']['chat'][0] ?? null)->toContain('Campo inválido');
});

it('replaceUserMessage devolve erros quando validação falha', function (): void {
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
    $svc->appendUserMessage($conversation, $fields, 'a@a.com');

    $conversation->refresh();
    $result = $svc->replaceUserMessage($conversation, $fields, 'email', 'email-invalido');

    expect($result['ok'])->toBeFalse();
    expect($result['errors'])->toHaveKey('email');
});

it('isReadyForSubmit ignora itens inválidos até achar ready_for_submit', function (): void {
    $svc = new ScriptedChatService;

    $messages = [
        'x', // não-array
        ['role' => 'user', 'content' => 'oi'],
        ['role' => 'assistant', 'content' => '...', 'meta' => ['at' => now()->toIso8601String()]], // sem phase
        ['role' => 'assistant', 'content' => '', 'meta' => ['phase' => 'ready_for_submit']],
    ];

    expect($svc->isReadyForSubmit($messages))->toBeTrue();
});

it('draftValuesFromMessages ignora mensagens sem meta expecting e parseia boolean/number', function (): void {
    $svc = new ScriptedChatService;

    $fields = [
        ['key' => 'flag', 'label' => 'Flag', 'type' => 'boolean', 'required' => false],
        ['key' => 'qty', 'label' => 'Qtd', 'type' => 'number', 'required' => false],
    ];

    $messages = [
        ['role' => 'assistant', 'content' => 'x'],
        ['role' => 'user', 'content' => 'Sim', 'meta' => ['expecting_field' => 'flag']],
        ['role' => 'user', 'content' => '12.5', 'meta' => ['expecting_field' => 'qty']],
        ['role' => 'user', 'content' => 'zzz', 'meta' => ['expecting_field' => 'invalido']], // field inexistente
        ['role' => 'user', 'content' => 'x', 'meta' => 'nao-array'],
    ];

    $out = $svc->draftValuesFromMessages($fields, $messages);
    expect($out)->toHaveKey('flag', true);
    expect($out['qty'])->toBeFloat();
});

it('inclui choice_cards meta com description/icon e ignora entradas inválidas', function (): void {
    $run = WorkflowRun::factory()->create();
    $nodeRun = WorkflowNodeRun::factory()->create(['workflow_run_id' => $run->id]);

    $conversation = WorkflowFormConversation::query()->create([
        'workflow_run_id' => $run->id,
        'workflow_node_run_id' => $nodeRun->id,
        'messages' => [],
    ]);

    $fields = [
        [
            'key' => 'ing',
            'label' => 'Ing',
            'type' => 'choice_cards',
            'required' => true,
            'choices' => [
                'x', // inválido
                ['value' => '', 'label' => 'Ignorar'],
                ['value' => 'v1', 'label' => 'Um', 'description' => 'Desc', 'icon' => 'Ico'],
            ],
        ],
    ];

    $svc = new ScriptedChatService;
    $svc->ensureOpeningAssistant($conversation, $fields);
    $conversation->refresh();

    $r = $svc->appendUserMessage($conversation, $fields, 'v1');
    expect($r['ok'])->toBeTrue();

    $lastUser = collect($r['messages'])->last(fn ($m) => is_array($m) && ($m['role'] ?? null) === 'user');
    expect($lastUser['meta']['field_choices'][0]['description'] ?? null)->toBe('Desc');
    expect($lastUser['meta']['field_choices'][0]['icon'] ?? null)->toBe('Ico');
});

it('isReadyForSubmit percorre mensagens sem encontrar ready_for_submit (cobre continues)', function (): void {
    $svc = new ScriptedChatService;

    // array_reverse vai começar pelo último item (não-array) -> line 166
    $messages = [
        ['role' => 'assistant', 'content' => 'x', 'meta' => ['phase' => 'nao']],
        ['role' => 'user', 'content' => 'oi'],
        'nao-array',
    ];

    expect($svc->isReadyForSubmit($messages))->toBeFalse();
});

it('appendUserMessage navega fields com entradas inválidas (cobre nextFieldAfter continue)', function (): void {
    $run = WorkflowRun::factory()->create();
    $nodeRun = WorkflowNodeRun::factory()->create(['workflow_run_id' => $run->id]);

    $conversation = WorkflowFormConversation::query()->create([
        'workflow_run_id' => $run->id,
        'workflow_node_run_id' => $nodeRun->id,
        'messages' => [],
    ]);

    $fields = [
        'x',
        ['label' => 'sem key'],
        ['key' => 'a', 'label' => 'A', 'type' => 'string', 'required' => true],
        ['key' => 'b', 'label' => 'B', 'type' => 'string', 'required' => true],
    ];

    $svc = new ScriptedChatService;
    $svc->ensureOpeningAssistant($conversation, $fields);
    $conversation->refresh();

    $r = $svc->appendUserMessage($conversation, $fields, 'va');
    expect($r['ok'])->toBeTrue();
});

it('appendUserMessage ignora mensagens que não são assistant/meta não-array até achar expecting_field', function (): void {
    $run = WorkflowRun::factory()->create();
    $nodeRun = WorkflowNodeRun::factory()->create(['workflow_run_id' => $run->id]);

    $conversation = WorkflowFormConversation::query()->create([
        'workflow_run_id' => $run->id,
        'workflow_node_run_id' => $nodeRun->id,
        'messages' => [
            ['role' => 'assistant', 'content' => 'Pergunta?', 'meta' => ['expecting_field' => 'name', 'at' => now()->toIso8601String()]],
            ['role' => 'assistant', 'content' => 'x', 'meta' => 'nao-array'],
            ['role' => 'user', 'content' => 'ruído'],
        ],
    ]);

    $fields = [
        ['key' => 'name', 'label' => 'Nome', 'type' => 'string', 'required' => true, 'ask' => 'Diga o nome'],
    ];

    $svc = new ScriptedChatService;
    $r = $svc->appendUserMessage($conversation, $fields, 'Ana');
    expect($r['ok'])->toBeTrue();
});

it('ensureOpeningAssistant usa field.ask e aceita texto vazio (asQuestion retorna vazio)', function (): void {
    $run = WorkflowRun::factory()->create();
    $nodeRun = WorkflowNodeRun::factory()->create(['workflow_run_id' => $run->id]);

    $conversation = WorkflowFormConversation::query()->create([
        'workflow_run_id' => $run->id,
        'workflow_node_run_id' => $nodeRun->id,
        'messages' => [],
    ]);

    $svc = new ScriptedChatService;
    $svc->ensureOpeningAssistant($conversation, [
        ['key' => 'k', 'label' => '', 'type' => 'string', 'required' => true, 'ask' => 'Pergunta!'],
    ]);

    $conversation->refresh();
    expect((string) ($conversation->messages[0]['content'] ?? ''))->toBe('Pergunta!');
});

it('ensureOpeningAssistant pode gerar pergunta vazia quando label é vazio (asQuestion early return)', function (): void {
    $run = WorkflowRun::factory()->create();
    $nodeRun = WorkflowNodeRun::factory()->create(['workflow_run_id' => $run->id]);

    $conversation = WorkflowFormConversation::query()->create([
        'workflow_run_id' => $run->id,
        'workflow_node_run_id' => $nodeRun->id,
        'messages' => [],
    ]);

    $svc = new ScriptedChatService;
    $svc->ensureOpeningAssistant($conversation, [
        ['key' => 'k', 'label' => '', 'type' => 'string', 'required' => true],
    ]);

    $conversation->refresh();
    expect((string) ($conversation->messages[0]['content'] ?? ''))->toBe('');
});

it('normalizeContent cobre boolean rawContent bool e scalar (bool cast)', function (): void {
    $run = WorkflowRun::factory()->create();
    $nodeRun = WorkflowNodeRun::factory()->create(['workflow_run_id' => $run->id]);

    $conversation = WorkflowFormConversation::query()->create([
        'workflow_run_id' => $run->id,
        'workflow_node_run_id' => $nodeRun->id,
        'messages' => [],
    ]);

    $fields = [
        ['key' => 'a', 'label' => 'A', 'type' => 'boolean', 'required' => true],
        ['key' => 'b', 'label' => 'B', 'type' => 'boolean', 'required' => true],
    ];

    $svc = new ScriptedChatService;
    $svc->ensureOpeningAssistant($conversation, $fields);
    $conversation->refresh();

    $r1 = $svc->appendUserMessage($conversation, $fields, true);
    expect($r1['ok'])->toBeTrue();

    $conversation->refresh();
    $r2 = $svc->appendUserMessage($conversation, $fields, 1);
    expect($r2['ok'])->toBeTrue();
});

it('draftValuesFromMessages retorna string vazia para number quando content é vazio', function (): void {
    $svc = new ScriptedChatService;

    $fields = [
        ['key' => 'qty', 'label' => 'Qtd', 'type' => 'number', 'required' => false],
    ];

    $out = $svc->draftValuesFromMessages($fields, [
        ['role' => 'user', 'content' => '', 'meta' => ['expecting_field' => 'qty']],
    ]);

    expect($out['qty'])->toBe('');
});

