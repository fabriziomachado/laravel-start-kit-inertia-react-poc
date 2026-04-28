<?php

declare(strict_types=1);

use Aftandilmmd\WorkflowAutomation\Engine\GraphExecutor;
use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowRun;
use App\Console\Commands\WorkflowEmailApprovalAmpExportCommand;
use App\Data\Sau\PrssLoginR01Dto;
use App\Flows\FlowRunStarter;
use App\Flows\WorkflowStarterPayload;
use App\Http\Middleware\RepairHtmlEntityAmpersandsInQueryString;
use App\Policies\WorkflowPolicy;
use App\Services\Workflow\AiCopilotService;
use App\Services\Workflow\AiFieldExtractor;
use App\Services\Workflow\NoopAiFieldExtractor;
use App\Services\Workflow\OpenAiFieldExtractor;
use Database\Seeders\WorkflowEmailApprovalDemoSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Symfony\Component\HttpFoundation\Response;

it('cobre caminhos básicos de serviços OpenAI (fakes)', function (): void {
    config()->set('services.openai.api_key', 'test-key');
    config()->set('services.openai.model', 'gpt-4o-mini');
    config()->set('services.openai.timeout', 10);

    Http::fake([
        'https://api.openai.com/v1/chat/completions' => Http::response([
            'choices' => [
                ['message' => ['content' => '{"name":"Maria"}']],
            ],
        ], 200),
    ]);

    $extractor = new OpenAiFieldExtractor;
    expect($extractor->isAvailable())->toBeTrue();

    $out = $extractor->extract([
        ['key' => 'name', 'label' => 'Nome', 'type' => 'string', 'required' => true],
        ['key' => 'age', 'label' => 'Idade', 'type' => 'number', 'required' => false],
    ], 'Nome: Maria');

    expect($out)->toHaveKey('name', 'Maria');
});

it('cobre NoopAiFieldExtractor', function (): void {
    $noop = new NoopAiFieldExtractor;

    expect($noop->isAvailable())->toBeFalse();
    expect($noop->extract([], 'x'))->toBe([]);
});

it('cobre AiCopilotService com sucesso via Http fake', function (): void {
    config()->set('services.openai.api_key', 'test-key');
    config()->set('services.openai.model', 'gpt-4o-mini');
    config()->set('services.openai.timeout', 10);

    Http::fake([
        'https://api.openai.com/v1/chat/completions' => Http::response([
            'choices' => [
                ['message' => ['content' => "Ok\n"]],
            ],
        ], 200),
    ]);

    $service = new AiCopilotService;

    $run = WorkflowRun::factory()->create(['context' => ['k' => 'v']]);
    $run->workflow()->associate(Workflow::factory()->create(['name' => 'Teste']))->save();

    $answer = $service->answer($run, 'Qual o estado?');

    expect($answer)->toBe('Ok');
});

it('cobre comando workflow:email-approval-amp-export', function (): void {
    $this->seed(WorkflowEmailApprovalDemoSeeder::class);

    // Executa o comando (injeção do GraphExecutor via container).
    $this->artisan(WorkflowEmailApprovalAmpExportCommand::class)
        ->assertExitCode(0);
});

it('cobre controllers intake (JSON) via HTTP', function (): void {
    $this->actingAs(\App\Models\User::factory()->create(['is_admin' => true]));

    $this->getJson('/flows/intake/students?q=ab')->assertOk();
    $this->getJson('/flows/intake/students?q=fin')->assertOk();
    $this->getJson('/flows/intake/students?q=doc')->assertOk();

    $this->postJson('/flows/intake/negotiations', [
        'student_id' => 1,
        'option_id' => 'opt-1',
    ])->assertOk();

    $this->postJson('/flows/intake/overrides', [
        'student_id' => 1,
        'reason' => 'x',
        'simulate_approve' => true,
    ])->assertOk();
});

it('cobre PrssLoginR01Dto::toArray', function (): void {
    $dto = new PrssLoginR01Dto(credentialId: '1', credentialType: 'cpf', password: 'secret');

    expect($dto->toArray())->toBe([
        'cd_pessoa_p' => '1',
        'tp_cd_pessoa' => 'cpf',
        'senha' => 'secret',
    ]);
});

it('cobre WorkflowStarterPayload e FlowRunStarter (fluxos de erro)', function (): void {
    $user = \App\Models\User::factory()->create();

    $payload = WorkflowStarterPayload::forUser($user);
    expect($payload)->toBeArray();

    $payload2 = WorkflowStarterPayload::forUserWithContext($user, [
        'student_id' => 1,
        'student_code' => '20240001',
        'student_name' => 'Maria',
    ]);
    expect($payload2[0])->toHaveKeys(['starter_user_id', 'student_id', 'student_code', 'student_name']);

    $workflow = Workflow::factory()->create(['is_active' => false]);

    /** @var \Aftandilmmd\WorkflowAutomation\Services\WorkflowService $svc */
    $svc = app(\Aftandilmmd\WorkflowAutomation\Services\WorkflowService::class);
    $starter = new FlowRunStarter($svc);

    $resp = $starter->startOrRedirectToForm($workflow, $user, 'flows.index');
    expect($resp->getStatusCode())->toBe(302);
});

it('cobre FlowIntakeController (Inertia) e WorkflowPolicy::start', function (): void {
    $user = \App\Models\User::factory()->create(['is_admin' => true]);

    Workflow::factory()->create([
        'name' => 'Matrícula de Calouro',
        'description' => 'Desc',
        'is_active' => true,
    ]);

    Workflow::factory()->create([
        'name' => 'Inativo',
        'description' => 'Desc',
        'is_active' => false,
    ]);

    $this->actingAs($user)
        ->get(route('flows.intake'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('flows/New')
            ->has('requirements')
            ->has('popularRequirementIds')
        );

    $policy = new WorkflowPolicy;
    expect($policy->start($user, Workflow::factory()->make(['is_active' => true])))->toBeTrue();
    expect($policy->start($user, Workflow::factory()->make(['is_active' => false])))->toBeFalse();
});

it('cobre RepairHtmlEntityAmpersandsInQueryString middleware', function (): void {
    $mw = new RepairHtmlEntityAmpersandsInQueryString;

    $request1 = Request::create('/any', 'GET');
    $r1 = $mw->handle($request1, fn (): Response => response('ok'));
    expect($r1->getContent())->toBe('ok');

    $request2 = Request::create('/workflow-approvals/1/2/3/approve?x=1&amp;y=2', 'GET');
    $request2->server->set('QUERY_STRING', 'x=1&amp;y=2');

    $r2 = $mw->handle($request2, function (Request $req): Response {
        return response($req->getQueryString() ?? '');
    });

    expect($r2->getContent())->toBe('x=1&y=2');
});

it('cobre AppServiceProvider binding de AiFieldExtractor (OpenAI vs Noop)', function (): void {
    config()->set('services.openai.api_key', null);
    $extractor1 = app(AiFieldExtractor::class);
    expect($extractor1)->toBeInstanceOf(NoopAiFieldExtractor::class);

    config()->set('services.openai.api_key', 'test-key');
    $extractor2 = app(AiFieldExtractor::class);
    expect($extractor2)->toBeInstanceOf(OpenAiFieldExtractor::class);
});

it('cobre SybasePingController via mock do DB rpc', function (): void {
    config()->set('sau.prss_login', [
        'credentialId' => '1',
        'credentialType' => 'cpf',
        'password' => 'secret',
    ]);

    $result = [
        new \App\Data\Sau\PrssLoginR01ResultDto(
            errorCode: 0,
            errorMessage: 'ok',
            idNumber: '1',
            name: 'Maria',
            cpf: '12345678900',
            lastPasswordChangeDate: null,
            lastUpdateDatetime: null,
        ),
    ];

    $rpc = \Mockery::mock();
    $rpc->shouldReceive('with')->once()->andReturnSelf();
    $rpc->shouldReceive('throwOnError')->once()->andReturnSelf();
    $rpc->shouldReceive('getAs')->once()->andReturn($result);

    $conn = \Mockery::mock();
    $conn->shouldReceive('rpc')->once()->with('prss_login_r01')->andReturn($rpc);

    $realDb = app('db');
    DB::shouldReceive('connection')
        ->andReturnUsing(static fn (?string $name = null) => $name === 'sau' ? $conn : $realDb->connection($name));

    $this->actingAs(\App\Models\User::factory()->create(['is_admin' => true]))
        ->get(route('debug.sybase'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('debug/sybase-ping')
            ->has('rows')
            ->where('sybaseError', null)
        );
});
