<?php

declare(strict_types=1);

use Aftandilmmd\WorkflowAutomation\Engine\GraphExecutor;
use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use App\Models\User;
use App\Services\Workflow\AiCopilotService;
use App\Services\Workflow\AiFieldExtractor;
use Database\Seeders\WorkflowFormWizardExampleSeeder;
use Illuminate\Support\Facades\Http;

/**
 * @return array{token: string, run_id: int, node_run_id: int}
 */
function wizardTokenFor(User $user): array
{
    test()->seed(WorkflowFormWizardExampleSeeder::class);

    $workflow = Workflow::query()->where('name', WorkflowFormWizardExampleSeeder::WORKFLOW_NAME)->firstOrFail();
    $run = app(GraphExecutor::class)->execute($workflow, \App\Flows\WorkflowStarterPayload::forUser($user));

    $nodeRun = $run->nodeRuns()->orderByDesc('id')->first();
    $token = $nodeRun?->output['main'][0]['resume_token'] ?? null;
    expect($token)->toBeString();
    expect($nodeRun)->not->toBeNull();

    return [
        'token' => (string) $token,
        'run_id' => (int) $run->id,
        'node_run_id' => (int) $nodeRun->id,
    ];
}

it('aiExtract devolve 503 quando extractor indisponível', function (): void {
    $user = User::factory()->create();
    $ctx = wizardTokenFor($user);
    $token = $ctx['token'];

    $extractor = \Mockery::mock(AiFieldExtractor::class);
    $extractor->shouldReceive('isAvailable')->once()->andReturnFalse();
    app()->instance(AiFieldExtractor::class, $extractor);

    $this->actingAs($user)
        ->postJson(route('workflow-forms.ai-extract', ['token' => $token]), ['free_text' => 'x'])
        ->assertStatus(503);
});

it('aiExtract devolve 502 quando extractor lança exceção', function (): void {
    $user = User::factory()->create();
    $ctx = wizardTokenFor($user);
    $token = $ctx['token'];

    $extractor = \Mockery::mock(AiFieldExtractor::class);
    $extractor->shouldReceive('isAvailable')->once()->andReturnTrue();
    $extractor->shouldReceive('extract')->once()->andThrow(new RuntimeException('boom'));
    app()->instance(AiFieldExtractor::class, $extractor);

    $this->actingAs($user)
        ->postJson(route('workflow-forms.ai-extract', ['token' => $token]), ['free_text' => 'x'])
        ->assertStatus(502);
});

it('aiExtract devolve values quando ok', function (): void {
    $user = User::factory()->create();
    $ctx = wizardTokenFor($user);
    $token = $ctx['token'];

    $extractor = \Mockery::mock(AiFieldExtractor::class);
    $extractor->shouldReceive('isAvailable')->once()->andReturnTrue();
    $extractor->shouldReceive('extract')->once()->andReturn(['name' => 'Ana']);
    app()->instance(AiFieldExtractor::class, $extractor);

    $this->actingAs($user)
        ->postJson(route('workflow-forms.ai-extract', ['token' => $token]), ['free_text' => 'Nome: Ana'])
        ->assertOk()
        ->assertJsonPath('values.name', 'Ana');
});

it('aiCopilot devolve 503 quando copilot indisponível', function (): void {
    $user = User::factory()->create();
    $ctx = wizardTokenFor($user);
    $token = $ctx['token'];

    config()->set('services.openai.api_key', null);

    $this->actingAs($user)
        ->postJson(route('workflow-forms.ai-copilot', ['token' => $token]), ['message' => 'oi'])
        ->assertStatus(503);
});

it('aiCopilot devolve 502 quando answer lança exceção', function (): void {
    $user = User::factory()->create();
    $ctx = wizardTokenFor($user);
    $token = $ctx['token'];

    config()->set('services.openai.api_key', 'test-key');
    config()->set('services.openai.timeout', 10);
    Http::fake([
        'https://api.openai.com/v1/chat/completions' => Http::response(['err' => true], 500),
    ]);

    $this->actingAs($user)
        ->postJson(route('workflow-forms.ai-copilot', ['token' => $token]), ['message' => 'oi'])
        ->assertStatus(502);
});

it('aiCopilot devolve reply quando ok', function (): void {
    $user = User::factory()->create();
    $ctx = wizardTokenFor($user);
    $token = $ctx['token'];

    config()->set('services.openai.api_key', 'test-key');
    config()->set('services.openai.timeout', 10);
    Http::fake([
        'https://api.openai.com/v1/chat/completions' => Http::response([
            'choices' => [
                ['message' => ['content' => 'ok']],
            ],
        ], 200),
    ]);

    $this->actingAs($user)
        ->postJson(route('workflow-forms.ai-copilot', ['token' => $token]), ['message' => 'oi'])
        ->assertOk()
        ->assertJsonPath('reply', 'ok');
});

it('chat devolve 404 para token inválido', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('workflow-forms.chat', ['token' => 'token-invalido']), ['content' => 'x'])
        ->assertNotFound();
});

it('chat devolve 422 quando ScriptedChatService retorna erros', function (): void {
    $user = User::factory()->create();
    $ctx = wizardTokenFor($user);
    $token = $ctx['token'];

    // Pré-cria uma conversa com mensagens mas sem meta expecting_field,
    // para que ensureOpeningAssistant não altere e appendUserMessage falhe (sem pergunta pendente).
    \App\Models\WorkflowFormConversation::query()->create([
        'workflow_run_id' => $ctx['run_id'],
        'workflow_node_run_id' => $ctx['node_run_id'],
        'messages' => [
            ['role' => 'assistant', 'content' => 'Olá', 'meta' => ['at' => now()->toIso8601String()]],
        ],
    ]);

    $this->actingAs($user)
        ->postJson(route('workflow-forms.chat', ['token' => $token]), ['content' => 'x'])
        ->assertStatus(422);
});

it('edit devolve 422 quando workflow_node_run_id não existe', function (): void {
    $user = User::factory()->create();
    $ctx = wizardTokenFor($user);
    $token = $ctx['token'];

    $this->actingAs($user)
        ->postJson(route('workflow-forms.chat.edit', ['token' => $token]), [
            'field_key' => 'name',
            'content' => 'Ana',
            'workflow_node_run_id' => 999999,
        ])
        ->assertStatus(422);
});

it('preferences aceita json e devolve preferências', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patchJson(route('workflow-forms.preferences'), ['workflow_form_renderer' => 'chatbot'])
        ->assertOk()
        ->assertJsonPath('preferences.workflow_form_renderer', 'chatbot');
});

