<?php

declare(strict_types=1);

use Aftandilmmd\WorkflowAutomation\Engine\GraphExecutor;
use Aftandilmmd\WorkflowAutomation\Enums\RunStatus;
use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use App\Models\User;
use Database\Seeders\WorkflowFormWizardExampleSeeder;

it('mostra o passo do formulário para um token válido', function (): void {
    $this->seed(WorkflowFormWizardExampleSeeder::class);

    $workflow = Workflow::query()->where('name', WorkflowFormWizardExampleSeeder::WORKFLOW_NAME)->firstOrFail();
    $run = app(GraphExecutor::class)->execute($workflow, [[]]);

    $token = $run->nodeRuns()->orderByDesc('id')->first()?->output['main'][0]['resume_token'] ?? null;
    expect($token)->toBeString();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('workflow-forms.show', ['token' => $token]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('workflow-forms/Show')
            ->has('token')
            ->has('step')
            ->where('step.title', 'Dados pessoais')
            ->has('progress')
            ->where('progress.workflow_name', WorkflowFormWizardExampleSeeder::WORKFLOW_NAME)
            ->where(
                'progress.workflow_description',
                mb_trim((string) $workflow->description),
            )
            ->has('progress.steps', 5)
            ->where('progress.steps.1.state', 'current')
            ->where('progress.steps.1.label', 'Dados pessoais')
            ->where('progress.steps.1.description', 'Indique o nome e o e-mail.')
            ->where('progress.steps.1.actor_name', $user->name)
            ->where('previous_token', null)
            ->where('prefill', []));
});

it('no segundo passo expõe o token da etapa anterior e o prefill repõe dados ao voltar', function (): void {
    $this->seed(WorkflowFormWizardExampleSeeder::class);

    $run = app(GraphExecutor::class)->execute(
        Workflow::query()->where('name', WorkflowFormWizardExampleSeeder::WORKFLOW_NAME)->firstOrFail(),
        [[]],
    );
    $firstToken = $run->nodeRuns()->orderByDesc('id')->first()?->output['main'][0]['resume_token'];
    expect($firstToken)->toBeString();

    $user = User::factory()->create();

    $first = $this->actingAs($user)
        ->post(route('workflow-forms.submit', ['token' => $firstToken]), [
            'name' => 'Ana Teste',
            'email' => 'ana@example.com',
        ]);

    $first->assertRedirect();
    preg_match('~workflow-forms/([^/?#]+)~', (string) $first->headers->get('Location'), $matches);
    $secondToken = $matches[1] ?? null;
    expect($secondToken)->toBeString()->not->toBe($firstToken);

    $this->actingAs($user)
        ->get(route('workflow-forms.show', ['token' => $secondToken]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('previous_token', $firstToken)
            ->where('step.title', 'Forma de ingresso.')
            ->where('progress.steps.1.actor_name', $user->name)
            ->where('progress.steps.2.actor_name', $user->name));

    $this->actingAs($user)
        ->get(route('workflow-forms.show', ['token' => $firstToken]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('prefill.name', 'Ana Teste')
            ->where('prefill.email', 'ana@example.com'));
});

it('submete os três passos do wizard e conclui o fluxo', function (): void {
    $this->seed(WorkflowFormWizardExampleSeeder::class);

    $workflow = Workflow::query()->where('name', WorkflowFormWizardExampleSeeder::WORKFLOW_NAME)->firstOrFail();
    $run = app(GraphExecutor::class)->execute($workflow, [[]]);
    $firstToken = $run->nodeRuns()->orderByDesc('id')->first()?->output['main'][0]['resume_token'];
    expect($firstToken)->toBeString();

    $user = User::factory()->create();

    $first = $this->actingAs($user)
        ->post(route('workflow-forms.submit', ['token' => $firstToken]), [
            'name' => 'Ana Teste',
            'email' => 'ana@example.com',
        ]);

    $first->assertRedirect();

    $run->refresh();
    expect($run->status)->toBe(RunStatus::Waiting);

    preg_match('~workflow-forms/([^/?#]+)~', (string) $first->headers->get('Location'), $m2);
    $ingressoToken = $m2[1] ?? null;
    expect($ingressoToken)->toBeString()->not->toBe($firstToken);

    $second = $this->actingAs($user)
        ->post(route('workflow-forms.submit', ['token' => $ingressoToken]), [
            'forma_ingresso' => 'vestibular_online',
        ]);

    $second->assertRedirect();

    preg_match('~workflow-forms/([^/?#]+)~', (string) $second->headers->get('Location'), $m3);
    $detailsToken = $m3[1] ?? null;
    expect($detailsToken)->toBeString()->not->toBe($ingressoToken);

    $third = $this->actingAs($user)
        ->post(route('workflow-forms.submit', ['token' => $detailsToken]), [
            'reason' => 'Motivo de teste',
            'accept_terms' => true,
        ]);

    $third->assertRedirect(route('flows.index'));

    $run->refresh();
    expect($run->status)->toBe(RunStatus::Completed);
});

it('valida campos obrigatórios no submit', function (): void {
    $this->seed(WorkflowFormWizardExampleSeeder::class);

    $workflow = Workflow::query()->where('name', WorkflowFormWizardExampleSeeder::WORKFLOW_NAME)->firstOrFail();
    $run = app(GraphExecutor::class)->execute($workflow, [[]]);
    $token = $run->nodeRuns()->orderByDesc('id')->first()?->output['main'][0]['resume_token'];

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('workflow-forms.submit', ['token' => $token]), [])
        ->assertSessionHasErrors(['name', 'email']);
});

it('valida choice_cards com valor fora das opções', function (): void {
    $this->seed(WorkflowFormWizardExampleSeeder::class);

    $workflow = Workflow::query()->where('name', WorkflowFormWizardExampleSeeder::WORKFLOW_NAME)->firstOrFail();
    $run = app(GraphExecutor::class)->execute($workflow, [[]]);
    $firstToken = $run->nodeRuns()->orderByDesc('id')->first()?->output['main'][0]['resume_token'];
    expect($firstToken)->toBeString();

    $user = User::factory()->create();

    $afterFirst = $this->actingAs($user)
        ->post(route('workflow-forms.submit', ['token' => $firstToken]), [
            'name' => 'Ana Teste',
            'email' => 'ana@example.com',
        ]);

    $afterFirst->assertRedirect();
    preg_match('~workflow-forms/([^/?#]+)~', (string) $afterFirst->headers->get('Location'), $matches);
    $ingressoToken = $matches[1] ?? null;
    expect($ingressoToken)->toBeString();

    $this->actingAs($user)
        ->post(route('workflow-forms.submit', ['token' => $ingressoToken]), [
            'forma_ingresso' => 'valor_invalido',
        ])
        ->assertSessionHasErrors(['forma_ingresso']);
});
