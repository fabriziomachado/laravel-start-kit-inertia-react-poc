<?php

declare(strict_types=1);

use Aftandilmmd\WorkflowAutomation\Engine\GraphExecutor;
use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use App\Flows\WorkflowStarterPayload;
use App\Models\User;
use Database\Seeders\WorkflowFormWizardExampleSeeder;
use Inertia\Testing\AssertableInertia;
use Illuminate\Support\Facades\DB;

function wizardToken(User $user): string
{
    test()->seed(WorkflowFormWizardExampleSeeder::class);
    $workflow = Workflow::query()->where('name', WorkflowFormWizardExampleSeeder::WORKFLOW_NAME)->firstOrFail();
    $run = app(GraphExecutor::class)->execute($workflow, WorkflowStarterPayload::forUser($user));
    $token = $run->nodeRuns()->orderByDesc('id')->first()?->output['main'][0]['resume_token'] ?? null;
    expect($token)->toBeString();

    return (string) $token;
}

it('submit retorna 404 para token inválido', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('workflow-forms.submit', ['token' => 'invalido']), [])
        ->assertNotFound();
});

it('submit-chat retorna 404 para token inválido', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('workflow-forms.submit-chat', ['token' => 'invalido']), [])
        ->assertNotFound();
});

it('preferences redireciona guest para login', function (): void {
    $this->patch(route('workflow-forms.preferences'), ['workflow_form_renderer' => 'wizard'])
        ->assertRedirect(route('login'));
});

it('preferences (html) redireciona back', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from('/settings/appearance')
        ->patch(route('workflow-forms.preferences'), ['workflow_form_renderer' => 'wizard'])
        ->assertRedirect();
});

it('show cai para wizard quando preferences não é JSON', function (): void {
    $user = User::factory()->create();
    $token = wizardToken($user);

    DB::table((new User)->getTable())
        ->where('id', $user->id)
        ->update(['preferences' => 'not-json']);

    $this->actingAs($user)
        ->get(route('workflow-forms.show', ['token' => $token]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('preferences.workflow_form_renderer', 'wizard')
        );
});

it('show usa chatbot quando preferências pedem chatbot', function (): void {
    $user = User::factory()->create();
    $token = wizardToken($user);

    \Illuminate\Support\Facades\DB::table((new User)->getTable())
        ->where('id', $user->id)
        ->update(['preferences' => json_encode(['workflow_form_renderer' => 'chatbot'], JSON_THROW_ON_ERROR)]);

    $this->actingAs($user)
        ->get(route('workflow-forms.show', ['token' => $token]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('preferences.workflow_form_renderer', 'chatbot')
        );
});

it('show cai para wizard quando preferences é null/empty', function (): void {
    $user = User::factory()->create();
    $token = wizardToken($user);

    \Illuminate\Support\Facades\DB::table((new User)->getTable())
        ->where('id', $user->id)
        ->update(['preferences' => null]);

    $this->actingAs($user)
        ->get(route('workflow-forms.show', ['token' => $token]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('preferences.workflow_form_renderer', 'wizard')
        );
});

it('show retorna 404 quando token pertence a run que não está Waiting', function (): void {
    $user = User::factory()->create();

    test()->seed(WorkflowFormWizardExampleSeeder::class);
    $workflow = Workflow::query()->where('name', WorkflowFormWizardExampleSeeder::WORKFLOW_NAME)->firstOrFail();
    $run = app(GraphExecutor::class)->execute($workflow, WorkflowStarterPayload::forUser($user));

    $nodeRun = $run->nodeRuns()->orderByDesc('id')->firstOrFail();
    $token = (string) ($nodeRun->output['main'][0]['resume_token'] ?? '');
    expect($token)->not->toBe('');

    // Marca o run como concluído (não Waiting) para findWaitingFormNodeRunByToken falhar.
    \Aftandilmmd\WorkflowAutomation\Models\WorkflowRun::query()
        ->where('id', $run->id)
        ->update(['status' => \Aftandilmmd\WorkflowAutomation\Enums\RunStatus::Completed]);

    $this->actingAs($user)
        ->get(route('workflow-forms.show', ['token' => $token]))
        ->assertNotFound();
});

