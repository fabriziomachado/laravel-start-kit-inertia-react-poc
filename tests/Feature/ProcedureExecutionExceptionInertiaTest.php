<?php

declare(strict_types=1);

use App\Http\Controllers\Concerns\HasInertiaFallback;
use App\Http\Controllers\SybasePingController;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Uepg\LaravelSybase\Database\ProcedureExecutionException;

/**
 * @return array<string, string>
 */
function inertiaPartialHeaders(): array
{
    $middleware = resolve(HandleInertiaRequests::class);
    $version = $middleware->version(Request::create('/')) ?? '';

    return [
        'HTTP_X_INERTIA' => 'true',
        'HTTP_X_INERTIA_VERSION' => $version,
        'HTTP_ACCEPT' => 'application/json',
    ];
}

it('returns 409 with X-Inertia-Location when inertia GET throws procedure exception', function (): void {
    $this->app->bind(SybasePingController::class, fn (): object => new class
    {
        public function __invoke(): never
        {
            throw new ProcedureExecutionException(9, 'msg_retorno_test', 'message_test');
        }
    });

    $response = $this->get('/sybase', inertiaPartialHeaders());

    $response->assertStatus(409);
    $response->assertHeader('X-Inertia-Location', url('/sybase'));
    $response->assertInertiaFlash('toast.type', 'error');
    $response->assertInertiaFlash('toast.title', 'msg_retorno_test');
    $response->assertInertiaFlash('toast.message', 'message_test');
    $response->assertInertiaFlash('toast.description', 'cd_retorno: 9');
    $response->assertInertiaFlash('toast.details.errorCode', 9);
    $response->assertInertiaFlash('toast.details.errorMessage', 'msg_retorno_test');
});

it('returns json 422 when fresh GET without inertia fallback throws procedure exception', function (): void {
    $this->app->bind(SybasePingController::class, fn (): object => new class
    {
        public function __invoke(): never
        {
            throw new ProcedureExecutionException(9, 'msg_retorno_test', 'message_test');
        }
    });

    $response = $this->get('/sybase');

    $response->assertStatus(422);
    $response->assertJson([
        'errorCode' => 9,
        'errorMessage' => 'msg_retorno_test',
        'idNumber' => null,
        'name' => null,
        'cpf' => null,
        'lastPasswordChangeDate' => null,
        'lastUpdateDatetime' => null,
    ]);
});

it('renders same inertia component with flash when fresh html GET throws procedure exception', function (): void {
    Http::fake(['*' => Http::response(null, 503)]);

    $this->app->bind(SybasePingController::class, fn (): object => new class implements HasInertiaFallback
    {
        public function __invoke(): never
        {
            throw new ProcedureExecutionException(9, 'msg_retorno_test', 'message_test');
        }

        public function inertiaFallback(): array
        {
            return ['debug/sybase-ping', ['rows' => null, 'sybaseError' => null]];
        }
    });

    $response = $this->get('/sybase');

    $response->assertOk();
    $response->assertInertia(function (AssertableInertia $page): void {
        $page->component('debug/sybase-ping')
            ->hasFlash('toast.type', 'error')
            ->hasFlash('toast.title', 'msg_retorno_test')
            ->hasFlash('toast.message', 'message_test')
            ->hasFlash('toast.description', 'cd_retorno: 9')
            ->hasFlash('toast.details.errorCode', 9)
            ->hasFlash('toast.details.errorMessage', 'msg_retorno_test');
    });
});
