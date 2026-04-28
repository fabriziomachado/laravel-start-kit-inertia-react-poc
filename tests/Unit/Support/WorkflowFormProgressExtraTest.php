<?php

declare(strict_types=1);

use Aftandilmmd\WorkflowAutomation\Enums\NodeRunStatus;
use Aftandilmmd\WorkflowAutomation\Enums\NodeType;
use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowEdge;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowNode;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowNodeRun;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowRun;
use App\Models\User;
use App\Models\WorkflowFormConversation;
use App\Services\Workflow\ScriptedChatService;
use App\Support\WorkflowFormProgress;

it('timeline retorna vazio quando workflow não tem trigger', function (): void {
    $workflow = Workflow::factory()->create();
    $node = WorkflowNode::factory()->for($workflow)->action('send_mail')->create();

    $run = WorkflowRun::factory()->for($workflow)->create(['context' => []]);
    $active = WorkflowNodeRun::factory()->for($run)->create([
        'node_id' => $node->id,
        'status' => NodeRunStatus::Pending,
        'output' => ['main' => [['fields' => []]]],
    ]);

    expect(WorkflowFormProgress::timeline($run, $workflow, $active))->toBe([]);
    expect(WorkflowFormProgress::completedRunReadOnlySections($run, $workflow))->toBe([]);
});

it('timeline usa viewerDisplayName como actor_name no passo atual', function (): void {
    $workflow = Workflow::factory()->create();
    $trigger = WorkflowNode::factory()->for($workflow)->trigger('manual')->create();
    $step = WorkflowNode::factory()->for($workflow)->action('form_step')->withConfig(['title' => 'Dados'])->create();
    WorkflowEdge::factory()->for($workflow)->create([
        'source_node_id' => $trigger->id,
        'target_node_id' => $step->id,
    ]);

    $run = WorkflowRun::factory()->for($workflow)->create(['context' => []]);
    $active = WorkflowNodeRun::factory()->for($run)->create([
        'node_id' => $step->id,
        'status' => NodeRunStatus::Pending,
        'output' => ['main' => [['fields' => []]]],
    ]);

    $steps = WorkflowFormProgress::timeline($run, $workflow, $active, 'Visitante');

    expect($steps)->not->toBe([]);
    expect($steps[1]['actor_name'])->toBe('Visitante');
});

it('timeline cai para index 0 quando active node não está na ordem', function (): void {
    $workflow = Workflow::factory()->create();
    $trigger = WorkflowNode::factory()->for($workflow)->trigger('manual')->create();
    $step = WorkflowNode::factory()->for($workflow)->action('form_step')->withConfig(['title' => 'Dados'])->create();
    $orphan = WorkflowNode::factory()->for($workflow)->action('form_step')->withConfig(['title' => 'Órfão'])->create();
    WorkflowEdge::factory()->for($workflow)->create([
        'source_node_id' => $trigger->id,
        'target_node_id' => $step->id,
    ]);

    $run = WorkflowRun::factory()->for($workflow)->create(['context' => []]);
    $active = WorkflowNodeRun::factory()->for($run)->create([
        'node_id' => $orphan->id, // existe mas não está conectado ao trigger
        'status' => NodeRunStatus::Pending,
        'output' => ['main' => [['fields' => []]]],
    ]);

    $steps = WorkflowFormProgress::timeline($run, $workflow, $active, 'Visitante');
    expect($steps[0]['state'])->toBe('current'); // caiu para index 0
});

it('previousFormResumeToken retorna null quando active node não está na ordem', function (): void {
    $workflow = Workflow::factory()->create();
    $trigger = WorkflowNode::factory()->for($workflow)->trigger('manual')->create();
    $step = WorkflowNode::factory()->for($workflow)->action('form_step')->withConfig(['title' => 'Dados'])->create();
    $orphan = WorkflowNode::factory()->for($workflow)->action('form_step')->withConfig(['title' => 'Órfão'])->create();
    WorkflowEdge::factory()->for($workflow)->create([
        'source_node_id' => $trigger->id,
        'target_node_id' => $step->id,
    ]);

    $run = WorkflowRun::factory()->for($workflow)->create(['context' => []]);
    $active = WorkflowNodeRun::factory()->for($run)->create([
        'node_id' => $orphan->id,
        'status' => NodeRunStatus::Pending,
        'output' => ['main' => [['fields' => []]]],
    ]);

    expect(WorkflowFormProgress::previousFormResumeToken($run, $workflow, $active))->toBeNull();
});

it('prefillForFields ignora campos inválidos e mescla draft do chat', function (): void {
    $workflow = Workflow::factory()->create();
    $node = WorkflowNode::factory()->for($workflow)->action('form_step')->create();

    $run = WorkflowRun::factory()->for($workflow)->create([
        'context' => [
            (string) $node->id => [
                'main' => [[
                    'name' => 'Salvo',
                ]],
            ],
        ],
    ]);

    $nr = WorkflowNodeRun::factory()->for($run)->create([
        'node_id' => $node->id,
        'status' => NodeRunStatus::Pending,
        'output' => ['main' => [[
            'fields' => [
                ['key' => 'name', 'type' => 'string', 'required' => true],
                ['key' => 'email', 'type' => 'email', 'required' => true],
            ],
        ]]],
    ]);

    $svc = new ScriptedChatService;
    $conv = WorkflowFormConversation::query()->create([
        'workflow_run_id' => $run->id,
        'workflow_node_run_id' => $nr->id,
        'messages' => [],
    ]);
    $svc->ensureOpeningAssistant($conv, [
        ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
    ]);
    $conv->refresh();
    $res = $svc->appendUserMessage($conv, [
        ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
    ], 'ana@example.com');
    expect($res['ok'])->toBeTrue();

    $prefill = WorkflowFormProgress::prefillForFields(
        $run,
        $nr,
        [
            ['key' => 'name', 'type' => 'string'],
            ['key' => 'email', 'type' => 'email'],
            ['nope' => true], // inválido
        ],
        $res['messages'],
        $svc,
    );

    expect($prefill['name'])->toBe('Salvo');
    expect($prefill['email'])->toBe('ana@example.com');
});

it('completedRunReadOnlySections cobre trigger/set_fields e email_approval', function (): void {
    $workflow = Workflow::factory()->create();
    $trigger = WorkflowNode::factory()->for($workflow)->trigger('manual')->create(['name' => '']);
    $set = WorkflowNode::factory()->for($workflow)->action('set_fields')->create(['name' => '']);
    $approval = WorkflowNode::factory()->for($workflow)->action('email_approval')->create(['name' => '']);

    WorkflowEdge::factory()->for($workflow)->create(['source_node_id' => $trigger->id, 'target_node_id' => $set->id]);
    WorkflowEdge::factory()->for($workflow)->create(['source_node_id' => $set->id, 'target_node_id' => $approval->id]);

    $run = WorkflowRun::factory()->for($workflow)->create([
        'context' => [
            (string) $trigger->id => ['main' => [['starter' => 'x']]],
            (string) $set->id => ['main' => [['foo' => 'bar']]],
            (string) $approval->id => [
                'approved' => [[
                    'decision' => 'approve',
                    'comment' => 'ok',
                    'decided_at' => now()->toIso8601String(),
                ]],
            ],
        ],
    ]);

    $sections = WorkflowFormProgress::completedRunReadOnlySections($run, $workflow);
    expect($sections)->not->toBe([]);
    expect(collect($sections)->pluck('heading')->implode(','))->toContain('Início');
});

it('completedRunReadOnlySections cobre send_mail, trigger e set_fields com slice', function (): void {
    $workflow = Workflow::factory()->create();
    $trigger = WorkflowNode::factory()->for($workflow)->trigger('manual')->create();
    $sendMail = WorkflowNode::factory()->for($workflow)->action('send_mail')->create();
    $set = WorkflowNode::factory()->for($workflow)->action('set_fields')->create();

    WorkflowEdge::factory()->for($workflow)->create(['source_node_id' => $trigger->id, 'target_node_id' => $sendMail->id]);
    WorkflowEdge::factory()->for($workflow)->create(['source_node_id' => $sendMail->id, 'target_node_id' => $set->id]);

    $many = [];
    for ($i = 0; $i < 30; $i++) {
        $many["k{$i}"] = "v{$i}";
    }

    $run = WorkflowRun::factory()->for($workflow)->create([
        'context' => [
            (string) $trigger->id => ['main' => [[
                'starter_user_id' => '1',
            ]]],
            (string) $sendMail->id => ['main' => [[
                'mail_sent' => true,
                'subject' => 'Oi',
                'to' => 'ana@example.com',
            ]]],
            (string) $set->id => ['main' => [$many]],
        ],
    ]);

    $sections = WorkflowFormProgress::completedRunReadOnlySections($run, $workflow);
    $encoded = json_encode($sections, JSON_THROW_ON_ERROR);
    expect($encoded)->toContain('Estado: enviado');
});

it('submittedFieldLines ignora chaves reservadas e humaniza choice_cards', function (): void {
    $workflow = Workflow::factory()->create();
    $trigger = WorkflowNode::factory()->for($workflow)->trigger('manual')->create();
    $node = WorkflowNode::factory()->for($workflow)->action('form_step')->withConfig([
        'title' => 'Etapa',
        'fields' => [
            ['key' => 'ing', 'label' => 'Ingresso', 'type' => 'choice_cards', 'choices' => [
                ['value' => 'v1', 'label' => 'Um'],
            ]],
        ],
    ])->create();

    WorkflowEdge::factory()->for($workflow)->create(['source_node_id' => $trigger->id, 'target_node_id' => $node->id]);

    $run = WorkflowRun::factory()->for($workflow)->create([
        'context' => [
            (string) $node->id => ['main' => [[
                'resume_token' => 'x',
                '_submitted_by_id' => '1',
                'ing' => 'v1',
            ]]],
        ],
    ]);

    $sections = WorkflowFormProgress::completedRunReadOnlySections($run, $workflow);
    $encoded = json_encode($sections, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    expect($encoded)->toContain('Ingresso: Um');
});

it('formatPair usa — e trunca valores longos', function (): void {
    $workflow = Workflow::factory()->create();
    $trigger = WorkflowNode::factory()->for($workflow)->trigger('manual')->create();

    $run = WorkflowRun::factory()->for($workflow)->create([
        'context' => [
            (string) $trigger->id => ['main' => [[
                'a' => null,
                'b' => str_repeat('x', 200),
            ]]],
        ],
    ]);

    $sections = WorkflowFormProgress::completedRunReadOnlySections($run, $workflow);
    $encoded = json_encode($sections, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    expect($encoded)->toContain('a: —');
    expect($encoded)->toContain('…');
});

it('previousFormResumeToken retorna null quando orderedNodes está vazio', function (): void {
    $workflow = Workflow::factory()->create();
    $node = WorkflowNode::factory()->for($workflow)->action('form_step')->create();
    $run = WorkflowRun::factory()->for($workflow)->create();
    $active = WorkflowNodeRun::factory()->for($run)->create(['node_id' => $node->id]);

    expect(WorkflowFormProgress::previousFormResumeToken($run, $workflow, $active))->toBeNull();
});

it('cumulativeFormChatMessages cai no caminho ordered vazio', function (): void {
    $workflow = Workflow::factory()->create();
    $run = WorkflowRun::factory()->for($workflow)->create();
    $node = WorkflowNode::factory()->for($workflow)->action('form_step')->create();
    $nodeRun = WorkflowNodeRun::factory()->for($run)->create(['node_id' => $node->id]);

    $conv = WorkflowFormConversation::query()->create([
        'workflow_run_id' => $run->id,
        'workflow_node_run_id' => $nodeRun->id,
        'messages' => [['role' => 'user', 'content' => 'oi']],
    ]);

    $msgs = WorkflowFormProgress::cumulativeFormChatMessages($run, $workflow, $nodeRun, $conv);
    expect($msgs)->toHaveCount(1);
    expect($msgs[0]['meta']['workflow_node_run_id'] ?? null)->toBe($nodeRun->id);
});

it('cumulativeFormChatMessages ignora passos anteriores sem node run concluído', function (): void {
    $workflow = Workflow::factory()->create();
    $trigger = WorkflowNode::factory()->for($workflow)->trigger('manual')->create();
    $step1 = WorkflowNode::factory()->for($workflow)->action('form_step')->create();
    $step2 = WorkflowNode::factory()->for($workflow)->action('form_step')->create();
    WorkflowEdge::factory()->for($workflow)->create(['source_node_id' => $trigger->id, 'target_node_id' => $step1->id]);
    WorkflowEdge::factory()->for($workflow)->create(['source_node_id' => $step1->id, 'target_node_id' => $step2->id]);

    $run = WorkflowRun::factory()->for($workflow)->create();
    // step1 não tem nodeRun Completed (caminho 226..227)
    $step2Run = WorkflowNodeRun::factory()->for($run)->create(['node_id' => $step2->id]);

    $conv = WorkflowFormConversation::query()->create([
        'workflow_run_id' => $run->id,
        'workflow_node_run_id' => $step2Run->id,
        'messages' => [['role' => 'user', 'content' => 'a']],
    ]);

    $msgs = WorkflowFormProgress::cumulativeFormChatMessages($run, $workflow, $step2Run, $conv);
    expect($msgs)->toHaveCount(1);
});

it('cumulativeFormChatMessages cai no caminho currentIndex=false quando node_id não pertence ao workflow', function (): void {
    $workflow = Workflow::factory()->create();
    $trigger = WorkflowNode::factory()->for($workflow)->trigger('manual')->create();
    $step = WorkflowNode::factory()->for($workflow)->action('form_step')->create();
    WorkflowEdge::factory()->for($workflow)->create(['source_node_id' => $trigger->id, 'target_node_id' => $step->id]);

    $run = WorkflowRun::factory()->for($workflow)->create();
    $otherNode = WorkflowNode::factory()->create(); // não pertence ao $workflow
    $nodeRun = WorkflowNodeRun::factory()->for($run)->create(['node_id' => $otherNode->id]);

    $conv = WorkflowFormConversation::query()->create([
        'workflow_run_id' => $run->id,
        'workflow_node_run_id' => $nodeRun->id,
        'messages' => [['role' => 'user', 'content' => 'oi']],
    ]);

    $msgs = WorkflowFormProgress::cumulativeFormChatMessages($run, $workflow, $nodeRun, $conv);
    expect($msgs)->toHaveCount(1);
});

it('cumulativeFormChatMessages lida com messages não-array (normalizeChatMessageList retorna [])', function (): void {
    $workflow = Workflow::factory()->create();
    $run = WorkflowRun::factory()->for($workflow)->create();
    $node = WorkflowNode::factory()->for($workflow)->action('form_step')->create();
    $nodeRun = WorkflowNodeRun::factory()->for($run)->create(['node_id' => $node->id]);

    $conv = WorkflowFormConversation::query()->create([
        'workflow_run_id' => $run->id,
        'workflow_node_run_id' => $nodeRun->id,
        'messages' => 'nao-array',
    ]);

    $msgs = WorkflowFormProgress::cumulativeFormChatMessages($run, $workflow, $nodeRun, $conv);
    expect($msgs)->toBe([]);
});

it('cumulativeFormChatMessages enriquece meta de choice_cards quando faltando (normalizeChoicesArrayForMeta)', function (): void {
    $workflow = Workflow::factory()->create();
    $run = WorkflowRun::factory()->for($workflow)->create();
    $node = WorkflowNode::factory()->for($workflow)->action('form_step')->create();

    $nodeRun = WorkflowNodeRun::factory()->for($run)->create([
        'node_id' => $node->id,
        'output' => [
            'main' => [[
                'fields' => [[
                    'key' => 'ing',
                    'label' => 'Ingresso',
                    'type' => 'choice_cards',
                    'choices' => [
                        'x',
                        ['value' => '', 'label' => 'ignorar'],
                        ['value' => 'v1', 'label' => 'Um', 'description' => 'Desc', 'icon' => 'Ico'],
                    ],
                ]],
            ]],
        ],
    ]);

    $conv = WorkflowFormConversation::query()->create([
        'workflow_run_id' => $run->id,
        'workflow_node_run_id' => $nodeRun->id,
        'messages' => [[
            'role' => 'user',
            'content' => 'v1',
            'meta' => ['expecting_field' => 'ing'],
        ]],
    ]);

    $msgs = WorkflowFormProgress::cumulativeFormChatMessages($run, $workflow, $nodeRun, $conv);
    expect($msgs[0]['meta']['field_choices'][0]['icon'] ?? null)->toBe('Ico');
});

it('completedRunReadOnlySections ignora email_approval sem decision payload', function (): void {
    $workflow = Workflow::factory()->create();
    $trigger = WorkflowNode::factory()->for($workflow)->trigger('manual')->create();
    $email = WorkflowNode::factory()->for($workflow)->action('email_approval')->create();
    WorkflowEdge::factory()->for($workflow)->create(['source_node_id' => $trigger->id, 'target_node_id' => $email->id]);

    $run = WorkflowRun::factory()->for($workflow)->create(['context' => []]);

    expect(WorkflowFormProgress::completedRunReadOnlySections($run, $workflow))->toBe([]);
});

it('completedRunReadOnlySections ignora valores array e keys não-string em set_fields/trigger', function (): void {
    $workflow = Workflow::factory()->create();
    $trigger = WorkflowNode::factory()->for($workflow)->trigger('manual')->create();
    $set = WorkflowNode::factory()->for($workflow)->action('set_fields')->create();
    WorkflowEdge::factory()->for($workflow)->create(['source_node_id' => $trigger->id, 'target_node_id' => $set->id]);

    $run = WorkflowRun::factory()->for($workflow)->create([
        'context' => [
            (string) $trigger->id => ['main' => [[
                'ok' => 'x',
                'arr' => ['x'], // trigger: pula is_array($v)
            ]]],
            (string) $set->id => ['main' => [[
                'a' => '1',
                'b' => ['x'], // set_fields: pula is_array($v)
                10 => 'y', // set_fields: pula !is_string($k)
            ]]],
        ],
    ]);

    $sections = WorkflowFormProgress::completedRunReadOnlySections($run, $workflow);
    $encoded = json_encode($sections, JSON_THROW_ON_ERROR);
    expect($encoded)->toContain('a: 1');
});

it('previousFormResumeToken retorna null quando nodeRun anterior não tem resume_token', function (): void {
    $workflow = Workflow::factory()->create();
    $trigger = WorkflowNode::factory()->for($workflow)->trigger('manual')->create();
    $step1 = WorkflowNode::factory()->for($workflow)->action('form_step')->create();
    $step2 = WorkflowNode::factory()->for($workflow)->action('form_step')->create();
    WorkflowEdge::factory()->for($workflow)->create(['source_node_id' => $trigger->id, 'target_node_id' => $step1->id]);
    WorkflowEdge::factory()->for($workflow)->create(['source_node_id' => $step1->id, 'target_node_id' => $step2->id]);

    $run = WorkflowRun::factory()->for($workflow)->create();
    WorkflowNodeRun::factory()->for($run)->create([
        'node_id' => $step1->id,
        'status' => NodeRunStatus::Completed,
        'output' => ['main' => [[/* sem resume_token */]]],
    ]);
    $active = WorkflowNodeRun::factory()->for($run)->create(['node_id' => $step2->id]);

    expect(WorkflowFormProgress::previousFormResumeToken($run, $workflow, $active))->toBeNull();
});

it('timeline resolve actor_name via _submitted_by_id', function (): void {
    $workflow = Workflow::factory()->create();
    $trigger = WorkflowNode::factory()->for($workflow)->trigger('manual')->create();
    $step = WorkflowNode::factory()->for($workflow)->action('form_step')->create();
    WorkflowEdge::factory()->for($workflow)->create(['source_node_id' => $trigger->id, 'target_node_id' => $step->id]);

    $user = \App\Models\User::factory()->create(['name' => 'Maria']);

    $run = WorkflowRun::factory()->for($workflow)->create([
        'context' => [
            (string) $step->id => ['main' => [[
                '_submitted_by_id' => (string) $user->id,
            ]]],
        ],
    ]);

    $active = WorkflowNodeRun::factory()->for($run)->create(['node_id' => $step->id]);
    $steps = WorkflowFormProgress::timeline($run, $workflow, $active);

    expect($steps[1]['actor_name'] ?? null)->toBe('Maria');
});

it('orderedNodes ignora ids repetidos (seen) em edges duplicadas', function (): void {
    $workflow = Workflow::factory()->create();
    $trigger = WorkflowNode::factory()->for($workflow)->trigger('manual')->create();
    $step = WorkflowNode::factory()->for($workflow)->action('form_step')->create();

    WorkflowEdge::factory()->for($workflow)->create(['source_node_id' => $trigger->id, 'target_node_id' => $step->id]);
    WorkflowEdge::factory()->for($workflow)->create(['source_node_id' => $trigger->id, 'target_node_id' => $step->id]); // duplicada

    $run = WorkflowRun::factory()->for($workflow)->create();
    $active = WorkflowNodeRun::factory()->for($run)->create(['node_id' => $step->id]);

    $steps = WorkflowFormProgress::timeline($run, $workflow, $active);
    expect($steps)->not->toBeEmpty();
});

it('orderedNodes ignora edges para nodes fora do workflow (node não encontrado na coleção)', function (): void {
    $workflow = Workflow::factory()->create();
    $trigger = WorkflowNode::factory()->for($workflow)->trigger('manual')->create();
    $step = WorkflowNode::factory()->for($workflow)->action('form_step')->create();
    $foreignNode = WorkflowNode::factory()->create(); // pertence a outro workflow

    WorkflowEdge::factory()->for($workflow)->create(['source_node_id' => $trigger->id, 'target_node_id' => $step->id]);
    WorkflowEdge::factory()->for($workflow)->create(['source_node_id' => $trigger->id, 'target_node_id' => $foreignNode->id]); // fila terá id que não existe em $workflow->nodes

    $run = WorkflowRun::factory()->for($workflow)->create();
    $active = WorkflowNodeRun::factory()->for($run)->create(['node_id' => $step->id]);

    $steps = WorkflowFormProgress::timeline($run, $workflow, $active);
    expect($steps)->not->toBeEmpty();
});

it('timeline cobre summaryForNode para trigger/set_fields/send_mail/email_approval e form_step', function (): void {
    $workflow = Workflow::factory()->create();
    $trigger = WorkflowNode::factory()->for($workflow)->trigger('manual')->create();
    $set = WorkflowNode::factory()->for($workflow)->action('set_fields')->create();
    $mail = WorkflowNode::factory()->for($workflow)->action('send_mail')->create();
    $approval = WorkflowNode::factory()->for($workflow)->action('email_approval')->create();
    $form = WorkflowNode::factory()->for($workflow)->action('form_step')->withConfig([
        'title' => 'Dados',
        'fields' => [[
            'key' => 'ing',
            'label' => 'Ingresso',
            'type' => 'choice_cards',
            'choices' => [
                ['value' => 'v1', 'label' => 'Um'],
            ],
        ]],
    ])->create();

    WorkflowEdge::factory()->for($workflow)->create(['source_node_id' => $trigger->id, 'target_node_id' => $set->id]);
    WorkflowEdge::factory()->for($workflow)->create(['source_node_id' => $set->id, 'target_node_id' => $mail->id]);
    WorkflowEdge::factory()->for($workflow)->create(['source_node_id' => $mail->id, 'target_node_id' => $approval->id]);
    WorkflowEdge::factory()->for($workflow)->create(['source_node_id' => $approval->id, 'target_node_id' => $form->id]);

    $many = [];
    for ($i = 0; $i < 20; $i++) {
        $many["k{$i}"] = "v{$i}";
    }
    $many['arr'] = ['x']; // força continue (is_array) dentro de set_fields

    $run = WorkflowRun::factory()->for($workflow)->create([
        'context' => [
            (string) $trigger->id => ['main' => [[
                'arr' => ['x'], // trigger: força continue (line 659)
                'x' => 'y',
            ]]],
            (string) $set->id => ['main' => [$many]], // set_fields: array_slice(0,12) (668..676)
            (string) $mail->id => ['main' => [[
                'mail_sent' => true,
                'subject' => 'Oi',
                'to' => 'ana@example.com',
            ]]], // send_mail (680)
            (string) $approval->id => [
                'approved' => [[
                    'decision' => 'approve',
                    'comment' => 'ok',
                    'decided_at' => now()->toIso8601String(),
                    'decided_by_email' => 'boss@example.com',
                ]],
            ], // email_approval + decided_at parse (684..686, 742)
            (string) $form->id => ['main' => [[
                'ing' => 'v2', // não bate choices -> humanizeSubmittedFieldValue retorna value (824, 832..835)
            ]]],
        ],
    ]);

    // Cria nodeRuns para marcar tudo como "completed" antes do passo atual
    $nrTrigger = WorkflowNodeRun::factory()->for($run)->create(['node_id' => $trigger->id, 'status' => NodeRunStatus::Completed]);
    $nrSet = WorkflowNodeRun::factory()->for($run)->create(['node_id' => $set->id, 'status' => NodeRunStatus::Completed]);
    $nrMail = WorkflowNodeRun::factory()->for($run)->create(['node_id' => $mail->id, 'status' => NodeRunStatus::Completed]);
    $nrApproval = WorkflowNodeRun::factory()->for($run)->create(['node_id' => $approval->id, 'status' => NodeRunStatus::Completed]);
    unset($nrTrigger, $nrSet, $nrMail, $nrApproval);

    $active = WorkflowNodeRun::factory()->for($run)->create(['node_id' => $form->id]);

    $steps = WorkflowFormProgress::timeline($run, $workflow, $active);
    $encoded = json_encode($steps, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    expect($encoded)->toContain('Estado: enviado');
    expect($encoded)->toContain('Decisão: Aprovado');
});

it('emailApprovalReadOnlyLines cobre catch quando decided_at é inválido', function (): void {
    $workflow = Workflow::factory()->create();
    $trigger = WorkflowNode::factory()->for($workflow)->trigger('manual')->create();
    $approval = WorkflowNode::factory()->for($workflow)->action('email_approval')->create();
    $step = WorkflowNode::factory()->for($workflow)->action('form_step')->create();
    WorkflowEdge::factory()->for($workflow)->create(['source_node_id' => $trigger->id, 'target_node_id' => $approval->id]);
    WorkflowEdge::factory()->for($workflow)->create(['source_node_id' => $approval->id, 'target_node_id' => $step->id]);

    $run = WorkflowRun::factory()->for($workflow)->create([
        'context' => [
            (string) $approval->id => [
                'approved' => [[
                    'decision' => 'approve',
                    'decided_at' => 'not-a-date',
                ]],
            ],
        ],
    ]);

    $active = WorkflowNodeRun::factory()->for($run)->create(['node_id' => $step->id]);
    $steps = WorkflowFormProgress::timeline($run, $workflow, $active);
    $encoded = json_encode($steps, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    expect($encoded)->toContain('Decidida em');
});

it('submittedFieldLines ignora valores array e humanize retorna value em casos não-mapeados', function (): void {
    $workflow = Workflow::factory()->create();
    $trigger = WorkflowNode::factory()->for($workflow)->trigger('manual')->create();
    $step = WorkflowNode::factory()->for($workflow)->action('form_step')->withConfig([
        'title' => 'Dados',
        'fields' => [
            ['key' => 'ing', 'label' => 'Ingresso', 'type' => 'choice_cards', 'choices' => 'nao-array'],
            ['key' => 'x', 'label' => 'X', 'type' => 'string'],
        ],
    ])->create();
    $after = WorkflowNode::factory()->for($workflow)->action('set_fields')->create();
    WorkflowEdge::factory()->for($workflow)->create(['source_node_id' => $trigger->id, 'target_node_id' => $step->id]);
    WorkflowEdge::factory()->for($workflow)->create(['source_node_id' => $step->id, 'target_node_id' => $after->id]);

    $run = WorkflowRun::factory()->for($workflow)->create([
        'context' => [
            (string) $step->id => ['main' => [[
                'ing' => 'v1', // choices não-array -> line 824
                'x' => ['arr'], // value array -> line 800 (continue)
                'y' => 'z', // key não existe em fields -> line 835
            ]]],
        ],
    ]);

    $stepRun = WorkflowNodeRun::factory()->for($run)->create(['node_id' => $step->id, 'status' => NodeRunStatus::Completed]);
    $active = WorkflowNodeRun::factory()->for($run)->create(['node_id' => $after->id]);

    $steps = WorkflowFormProgress::timeline($run, $workflow, $active);
    $encoded = json_encode($steps, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    expect($encoded)->toContain('Ingresso: v1');
    unset($stepRun);
});

it('humanizeSubmittedFieldValue retorna value quando não encontra label nas choices', function (): void {
    $workflow = Workflow::factory()->create();
    $trigger = WorkflowNode::factory()->for($workflow)->trigger('manual')->create();
    $step = WorkflowNode::factory()->for($workflow)->action('form_step')->withConfig([
        'title' => 'Dados',
        'fields' => [
            ['key' => 'ing', 'label' => 'Ingresso', 'type' => 'choice_cards', 'choices' => [
                ['value' => 'v1', 'label' => 'Um'],
            ]],
        ],
    ])->create();
    $after = WorkflowNode::factory()->for($workflow)->action('set_fields')->create();
    WorkflowEdge::factory()->for($workflow)->create(['source_node_id' => $trigger->id, 'target_node_id' => $step->id]);
    WorkflowEdge::factory()->for($workflow)->create(['source_node_id' => $step->id, 'target_node_id' => $after->id]);

    $run = WorkflowRun::factory()->for($workflow)->create([
        'context' => [
            (string) $step->id => ['main' => [[
                'ing' => 'v2', // não bate -> line 832
            ]]],
        ],
    ]);
    $stepRun = WorkflowNodeRun::factory()->for($run)->create(['node_id' => $step->id, 'status' => NodeRunStatus::Completed]);
    $active = WorkflowNodeRun::factory()->for($run)->create(['node_id' => $after->id]);

    $steps = WorkflowFormProgress::timeline($run, $workflow, $active);
    $encoded = json_encode($steps, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    expect($encoded)->toContain('Ingresso: v2');
    unset($stepRun);
});

it('previousFormResumeToken cobre guard de candidato não-WorkflowNodeRun (line 502)', function (): void {
    $workflow = Workflow::factory()->create();
    $trigger = WorkflowNode::factory()->for($workflow)->trigger('manual')->create();
    $step1 = WorkflowNode::factory()->for($workflow)->action('form_step')->create();
    $step2 = WorkflowNode::factory()->for($workflow)->action('form_step')->create();
    WorkflowEdge::factory()->for($workflow)->create(['source_node_id' => $trigger->id, 'target_node_id' => $step1->id]);
    WorkflowEdge::factory()->for($workflow)->create(['source_node_id' => $step1->id, 'target_node_id' => $step2->id]);

    $run = WorkflowRun::factory()->for($workflow)->create();
    // Inject relation com Model que não é WorkflowNodeRun mas tem os campos usados pelo Collection::where.
    $fake = new \App\Models\User();
    $fake->setAttribute('node_id', $step1->id);
    $fake->setAttribute('status', NodeRunStatus::Completed);
    $fake->setAttribute('id', 123);
    $fake->setAttribute('output', ['main' => [[/* sem resume_token */]]]);
    $run->setRelation('nodeRuns', collect([$fake]));

    $active = WorkflowNodeRun::factory()->for($run)->create(['node_id' => $step2->id]);
    expect(WorkflowFormProgress::previousFormResumeToken($run, $workflow, $active))->toBeNull();
});

it('cumulativeFormChatMessages enriquece meta para select e choice_cards', function (): void {
    $workflow = Workflow::factory()->create();
    $trigger = WorkflowNode::factory()->for($workflow)->trigger('manual')->create();
    $step1 = WorkflowNode::factory()->for($workflow)->action('form_step')->withConfig(['title' => 'Etapa 1'])->create();
    $step2 = WorkflowNode::factory()->for($workflow)->action('form_step')->withConfig(['title' => 'Etapa 2'])->create();

    WorkflowEdge::factory()->for($workflow)->create(['source_node_id' => $trigger->id, 'target_node_id' => $step1->id]);
    WorkflowEdge::factory()->for($workflow)->create(['source_node_id' => $step1->id, 'target_node_id' => $step2->id]);

    $run = WorkflowRun::factory()->for($workflow)->create(['context' => []]);

    $nr1 = WorkflowNodeRun::factory()->for($run)->create([
        'node_id' => $step1->id,
        'status' => NodeRunStatus::Completed,
        'output' => ['main' => [[
            'fields' => [
                ['key' => 'forma', 'type' => 'select', 'label' => 'Forma', 'options' => 'a,b'],
                ['key' => 'ingresso', 'type' => 'choice_cards', 'label' => 'Ingresso', 'choices' => [
                    ['value' => 'v1', 'label' => 'Um', 'description' => 'd', 'icon' => 'i'],
                ]],
            ],
        ]]],
    ]);

    $nr2 = WorkflowNodeRun::factory()->for($run)->create([
        'node_id' => $step2->id,
        'status' => NodeRunStatus::Pending,
        'output' => ['main' => [[
            'fields' => [
                ['key' => 'x', 'type' => 'string', 'label' => 'X'],
            ],
        ]]],
    ]);

    WorkflowFormConversation::query()->create([
        'workflow_run_id' => $run->id,
        'workflow_node_run_id' => $nr1->id,
        'messages' => [
            [
                'role' => 'user',
                'content' => 'a',
                'meta' => ['expecting_field' => 'forma', 'at' => now()->toIso8601String()],
            ],
            [
                'role' => 'user',
                'content' => 'v1',
                'meta' => ['expecting_field' => 'ingresso', 'at' => now()->toIso8601String()],
            ],
        ],
    ]);

    $currentConversation = WorkflowFormConversation::query()->create([
        'workflow_run_id' => $run->id,
        'workflow_node_run_id' => $nr2->id,
        'messages' => [],
    ]);

    $out = WorkflowFormProgress::cumulativeFormChatMessages($run, $workflow, $nr2, $currentConversation);
    $encoded = json_encode($out, JSON_THROW_ON_ERROR);

    expect($encoded)->toContain('field_options');
    expect($encoded)->toContain('field_choices');
});

