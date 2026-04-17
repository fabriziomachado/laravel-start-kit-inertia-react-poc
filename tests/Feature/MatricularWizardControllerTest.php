<?php

declare(strict_types=1);

use Aftandilmmd\WorkflowAutomation\Engine\GraphExecutor;
use Aftandilmmd\WorkflowAutomation\Enums\RunStatus;
use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use App\Models\User;
use Database\Seeders\WorkflowFormWizardExampleSeeder;

it('mostra a página de matrícula sem iniciar o fluxo', function (): void {
    $this->seed(WorkflowFormWizardExampleSeeder::class);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('matricular'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('matricular/Index')
        ->where('workflow_name', WorkflowFormWizardExampleSeeder::WORKFLOW_NAME)
        ->has('runs'));
});

it('inicia o fluxo ao submeter POST e redireciona para o primeiro passo', function (): void {
    $this->seed(WorkflowFormWizardExampleSeeder::class);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('matricular.store'));

    $response->assertRedirect();

    $target = $response->headers->get('Location');
    expect($target)->toBeString()->toContain('/workflow-forms/');

    $this->actingAs($user)->get($target)->assertOk();
});

it('lista todas as instâncias do fluxo após iniciar uma', function (): void {
    $this->seed(WorkflowFormWizardExampleSeeder::class);

    $user = User::factory()->create();

    $this->actingAs($user)->post(route('matricular.store'))->assertRedirect();

    $this->actingAs($user)
        ->get(route('matricular'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('matricular/Index')
            ->has('runs', 1));
});

it('outro utilizador vê a mesma instância na listagem global', function (): void {
    $this->seed(WorkflowFormWizardExampleSeeder::class);

    $alice = User::factory()->create(['name' => 'Alice Matrícula']);
    $bob = User::factory()->create(['name' => 'Bob Lista']);

    $this->actingAs($alice)->post(route('matricular.store'))->assertRedirect();

    $this->actingAs($bob)
        ->get(route('matricular'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('matricular/Index')
            ->has('runs', 1)
            ->where('runs.0.iniciada_por_label', 'Alice Matrícula'));
});

it('redireciona ao dashboard com mensagem quando o workflow não existe', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('matricular'))
        ->assertRedirect(route('dashboard'));

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('matricula_error'));
});

it('permite ao titular ver na lista e abrir o resumo só de leitura de uma matrícula concluída', function (): void {
    $this->seed(WorkflowFormWizardExampleSeeder::class);

    $user = User::factory()->create();
    $workflow = Workflow::query()->where('name', WorkflowFormWizardExampleSeeder::WORKFLOW_NAME)->firstOrFail();
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
        ->assertOk();

    $run->refresh();
    expect($run->status)->toBe(RunStatus::Completed);

    $viewUrl = route('matricular.runs.show', $run);

    $this->actingAs($user)
        ->get(route('matricular'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('runs', 1)
            ->where('runs.0.view_url', $viewUrl)
            ->where('runs.0.resume_url', null));

    $this->actingAs($user)
        ->get($viewUrl)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('matricular/Show')
            ->where('run_id', $run->id)
            ->where('workflow_name', WorkflowFormWizardExampleSeeder::WORKFLOW_NAME)
            ->where('iniciada_por_label', $user->name)
            ->has('sections')
            ->where('sections', fn ($sections): bool => collect($sections)
                ->pluck('heading')
                ->contains('Dados pessoais')));
});

it('não permite a outro utilizador ver o resumo de matrícula concluída', function (): void {
    $this->seed(WorkflowFormWizardExampleSeeder::class);

    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $workflow = Workflow::query()->where('name', WorkflowFormWizardExampleSeeder::WORKFLOW_NAME)->firstOrFail();
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
        ->assertOk();

    $run->refresh();
    expect($run->status)->toBe(RunStatus::Completed);

    $this->actingAs($bob)->get(route('matricular.runs.show', $run->id))->assertForbidden();
});

it('responde 404 ao pedir resumo de instância ainda em curso', function (): void {
    $this->seed(WorkflowFormWizardExampleSeeder::class);

    $user = User::factory()->create();
    $workflow = Workflow::query()->where('name', WorkflowFormWizardExampleSeeder::WORKFLOW_NAME)->firstOrFail();
    $run = app(GraphExecutor::class)->execute($workflow, [['matricula_user_id' => $user->id]]);

    expect($run->fresh()->status)->toBe(RunStatus::Waiting);

    $this->actingAs($user)->get(route('matricular.runs.show', $run->id))->assertNotFound();
});

it('permite a um administrador ver o resumo de matrícula concluída de outro utilizador', function (): void {
    $this->seed(WorkflowFormWizardExampleSeeder::class);

    $alice = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $workflow = Workflow::query()->where('name', WorkflowFormWizardExampleSeeder::WORKFLOW_NAME)->firstOrFail();
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
        ->assertOk();

    $run->refresh();
    expect($run->status)->toBe(RunStatus::Completed);

    $this->actingAs($admin)
        ->get(route('matricular.runs.show', $run->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('matricular/Show')
            ->where('iniciada_por_label', $alice->name));
});
