<?php

declare(strict_types=1);

use Aftandilmmd\WorkflowAutomation\Engine\GraphExecutor;
use Aftandilmmd\WorkflowAutomation\Enums\RunStatus;
use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use App\Models\User;
use Database\Seeders\WorkflowFormWizardExampleSeeder;

function configureExampleWorkflow(): Workflow
{
    test()->seed(WorkflowFormWizardExampleSeeder::class);

    return Workflow::query()->where('name', WorkflowFormWizardExampleSeeder::WORKFLOW_NAME)->firstOrFail();
}

it('mostra o catálogo de fluxos activos', function (): void {
    configureExampleWorkflow();

    $user = User::factory()->create();

    $this->actingAs($user)->get(route('flows.index'))->assertOk()->assertInertia(fn ($page) => $page
        ->component('flows/Index')
        ->has('workflows', 1)
        ->where('workflows.0.name', WorkflowFormWizardExampleSeeder::WORKFLOW_NAME)
        ->has('runs'));
});

it('inicia o fluxo ao submeter POST e redireciona para o primeiro passo', function (): void {
    $workflow = configureExampleWorkflow();

    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('flows.runs.store', $workflow));

    $response->assertRedirect();

    $target = $response->headers->get('Location');
    expect($target)->toBeString()->toContain('/workflow-forms/');

    $this->actingAs($user)->get($target)->assertOk();
});

it('lista instâncias do fluxo após iniciar uma', function (): void {
    $workflow = configureExampleWorkflow();

    $user = User::factory()->create();

    $this->actingAs($user)->post(route('flows.runs.store', $workflow))->assertRedirect();

    $this->actingAs($user)
        ->get(route('flows.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('flows/Index')
            ->has('runs', 1));
});

it('utilizador não administrador só vê as próprias execuções na listagem', function (): void {
    $workflow = configureExampleWorkflow();

    $alice = User::factory()->create(['name' => 'Alice Fluxo']);
    $bob = User::factory()->create(['name' => 'Bob Lista']);

    $this->actingAs($alice)->post(route('flows.runs.store', $workflow))->assertRedirect();

    $this->actingAs($bob)
        ->get(route('flows.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('flows/Index')
            ->has('runs', 0));
});

it('administrador vê execuções de outros utilizadores na listagem', function (): void {
    $workflow = configureExampleWorkflow();

    $alice = User::factory()->create(['name' => 'Alice Admin']);
    $admin = User::factory()->admin()->create();

    $this->actingAs($alice)->post(route('flows.runs.store', $workflow))->assertRedirect();

    $this->actingAs($admin)
        ->get(route('flows.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('flows/Index')
            ->has('runs', 1)
            ->where('runs.0.iniciada_por_label', 'Alice Admin'));
});

it('redireciona ao dashboard com mensagem quando não há fluxos activos', function (): void {
    Workflow::query()->update(['is_active' => false]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('flows.index'))
        ->assertRedirect(route('dashboard'));

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('flows_error'));
});

it('permite ao titular ver na lista e abrir o resumo só de leitura de uma execução concluída', function (): void {
    $workflow = configureExampleWorkflow();

    $user = User::factory()->create();
    $run = app(GraphExecutor::class)->execute($workflow, [['matricula_user_id' => $user->id]]);

    $firstToken = $run->nodeRuns()->orderByDesc('id')->first()?->output['main'][0]['resume_token'];
    expect($firstToken)->toBeString();

    $first = $this->actingAs($user)->post(route('workflow-forms.submit', ['token' => $firstToken]), [
        'name' => 'Ana Resumo',
        'email' => 'ana-resumo@example.test',
    ]);
    $first->assertRedirect();
    preg_match('~workflow-forms/([^/?#]+)~', (string) $first->headers->get('Location'), $m2);
    $ingressoToken = $m2[1] ?? null;
    expect($ingressoToken)->toBeString();

    $second = $this->actingAs($user)->post(route('workflow-forms.submit', ['token' => $ingressoToken]), [
        'forma_ingresso' => 'enem',
    ]);
    $second->assertRedirect();
    preg_match('~workflow-forms/([^/?#]+)~', (string) $second->headers->get('Location'), $m3);
    $detailsToken = $m3[1] ?? null;
    expect($detailsToken)->toBeString();

    $this->actingAs($user)
        ->post(route('workflow-forms.submit', ['token' => $detailsToken]), [
            'reason' => 'Teste resumo',
            'accept_terms' => true,
        ])
        ->assertRedirect(route('flows.index'));

    $run->refresh();
    expect($run->status)->toBe(RunStatus::Completed);

    $viewUrl = route('flows.runs.show', $run);

    $this->actingAs($user)
        ->get(route('flows.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('runs', 1)
            ->where('runs.0.view_url', $viewUrl)
            ->where('runs.0.resume_url', null));

    $this->actingAs($user)
        ->get($viewUrl)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('flows/Show')
            ->where('run_id', $run->id)
            ->where('workflow_name', WorkflowFormWizardExampleSeeder::WORKFLOW_NAME)
            ->where('iniciada_por_label', $user->name)
            ->has('sections')
            ->where('sections', fn ($sections): bool => collect($sections)
                ->pluck('heading')
                ->contains('Dados pessoais')));
});

it('não permite a outro utilizador ver o resumo de execução concluída', function (): void {
    $workflow = configureExampleWorkflow();

    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $run = app(GraphExecutor::class)->execute($workflow, [['matricula_user_id' => $alice->id]]);

    $firstToken = $run->nodeRuns()->orderByDesc('id')->first()?->output['main'][0]['resume_token'];
    expect($firstToken)->toBeString();

    $first = $this->actingAs($alice)->post(route('workflow-forms.submit', ['token' => $firstToken]), [
        'name' => 'Alice',
        'email' => 'alice@example.test',
    ]);
    preg_match('~workflow-forms/([^/?#]+)~', (string) $first->headers->get('Location'), $m2);
    $ingressoToken = $m2[1] ?? null;
    expect($ingressoToken)->toBeString();

    $second = $this->actingAs($alice)->post(route('workflow-forms.submit', ['token' => $ingressoToken]), [
        'forma_ingresso' => 'enem',
    ]);
    preg_match('~workflow-forms/([^/?#]+)~', (string) $second->headers->get('Location'), $m3);
    $detailsToken = $m3[1] ?? null;
    expect($detailsToken)->toBeString();

    $this->actingAs($alice)
        ->post(route('workflow-forms.submit', ['token' => $detailsToken]), [
            'reason' => 'Motivo',
            'accept_terms' => true,
        ])
        ->assertRedirect(route('flows.index'));

    $run->refresh();
    expect($run->status)->toBe(RunStatus::Completed);

    $this->actingAs($bob)->get(route('flows.runs.show', $run->id))->assertForbidden();
});

it('responde 404 ao pedir resumo de instância ainda em curso', function (): void {
    $workflow = configureExampleWorkflow();

    $user = User::factory()->create();
    $run = app(GraphExecutor::class)->execute($workflow, [['matricula_user_id' => $user->id]]);

    expect($run->fresh()->status)->toBe(RunStatus::Waiting);

    $this->actingAs($user)->get(route('flows.runs.show', $run->id))->assertNotFound();
});

it('permite a um administrador ver o resumo de execução concluída de outro utilizador', function (): void {
    $workflow = configureExampleWorkflow();

    $alice = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $run = app(GraphExecutor::class)->execute($workflow, [['matricula_user_id' => $alice->id]]);

    $firstToken = $run->nodeRuns()->orderByDesc('id')->first()?->output['main'][0]['resume_token'];
    expect($firstToken)->toBeString();

    $first = $this->actingAs($alice)->post(route('workflow-forms.submit', ['token' => $firstToken]), [
        'name' => 'Alice',
        'email' => 'alice-admin@example.test',
    ]);
    preg_match('~workflow-forms/([^/?#]+)~', (string) $first->headers->get('Location'), $m2);
    $ingressoToken = $m2[1] ?? null;
    expect($ingressoToken)->toBeString();

    $second = $this->actingAs($alice)->post(route('workflow-forms.submit', ['token' => $ingressoToken]), [
        'forma_ingresso' => 'enem',
    ]);
    preg_match('~workflow-forms/([^/?#]+)~', (string) $second->headers->get('Location'), $m3);
    $detailsToken = $m3[1] ?? null;
    expect($detailsToken)->toBeString();

    $this->actingAs($alice)
        ->post(route('workflow-forms.submit', ['token' => $detailsToken]), [
            'reason' => 'Motivo',
            'accept_terms' => true,
        ])
        ->assertRedirect(route('flows.index'));

    $run->refresh();
    expect($run->status)->toBe(RunStatus::Completed);

    $this->actingAs($admin)
        ->get(route('flows.runs.show', $run->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('flows/Show')
            ->where('iniciada_por_label', $alice->name));
});
