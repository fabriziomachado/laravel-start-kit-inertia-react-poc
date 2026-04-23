<?php

declare(strict_types=1);

use App\Data\Sau\PrssLoginR01ResultDto;
use App\Http\Controllers\Concerns\HasInertiaFallback;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Uepg\LaravelSybase\Database\ProcedureExecutionException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'workflow-approvals/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        /**
         * ProcedureExecutionException (Sybase RPC com throwOnError): global.
         *
         * - expectsJson() || sem controller Inertia: JSON 422 com o mesmo formato camelCase
         *   que PrssLoginR01ResultDto (errorCode, errorMessage, …).
         *   Nota: curl e clientes API tipicamente não enviam Accept: application/json,
         *   por isso a condição inclui o fallback "sem HasInertiaFallback".
         *
         * - GET Inertia (navegação SPA): flash + Inertia::location(mesmo URL) → 409 com
         *   X-Inertia-Location. O cliente Inertia faz window.location = url (hard reload),
         *   que é um pedido fresh sem X-Inertia. Sem loop; o flash fica na sessão e é
         *   consumido quando o componente renderiza no reload.
         *
         * - GET HTML (primeiro carregamento) com HasInertiaFallback: renderiza o componente
         *   directamente com props de estado vazio + flash inline — permanece na mesma rota
         *   sem redirect nem loop.
         *
         * - GET sem HasInertiaFallback (closures, endpoints JSON sem Accept header): JSON 422.
         *
         * - POST / PUT / PATCH / DELETE: PRG com redirect()->back() — mesmo URL que o referer
         *   (ex.: formulário em /login) passa a funcionar; sem forçar login/home.
         */
        $exceptions->renderable(function (ProcedureExecutionException $e, Request $request): SymfonyResponse {
            $result = PrssLoginR01ResultDto::fromArray([
                'cd_retorno' => $e->cdRetorno,
                'msg_retorno' => $e->msgRetorno ?? '',
            ]);

            if ($request->expectsJson() && ! $request->inertia()) {
                return response()->json($result, 422, [], JSON_UNESCAPED_UNICODE);
            }

            $toastPayload = [
                'type' => 'error',
                'title' => filled($e->msgRetorno) ? $e->msgRetorno : $e->getMessage(),
                'message' => $e->getMessage(),
                'description' => 'cd_retorno: '.$e->cdRetorno,
                'details' => [
                    'errorCode' => $result->errorCode,
                    'errorMessage' => $result->errorMessage,
                    'idNumber' => $result->idNumber,
                    'name' => $result->name,
                    'cpf' => $result->cpf,
                    'lastPasswordChangeDate' => $result->lastPasswordChangeDate,
                    'lastUpdateDatetime' => $result->lastUpdateDatetime,
                ],
            ];

            Inertia::flash('toast', $toastPayload);

            if ($request->isMethod('GET')) {
                if ($request->inertia()) {
                    return Inertia::location($request->fullUrl());
                }

                $controller = $request->route()?->getController();

                if ($controller instanceof HasInertiaFallback) {
                    [$component, $props] = $controller->inertiaFallback();

                    return Inertia::render($component, $props)->toResponse($request);
                }

                // Closure ou controller sem HasInertiaFallback: não é uma rota Inertia,
                // pelo que JSON 422 é a resposta correcta (independentemente do Accept header).
                return response()->json($result, 422, [], JSON_UNESCAPED_UNICODE);
            }

            // $previous = url()->previous();
            // $safeUrl = $previous !== $request->fullUrl() ? $previous : route('home');

            // return redirect()->to($safeUrl);
            return back(fallback: route('home'));
        });

        // $exceptions->renderable(function (\Uepg\LaravelSybase\Database\ProcedureExecutionException $e, \Illuminate\Http\Request $request) {
        //     if ($request->is('__sybase-ping') || $request->expectsJson()) {
        //         return response()->json([
        //             'ok->' => false,
        //             'cd_retorno' => $e->cdRetorno,
        //             'msg' => $e->msgRetorno,
        //         ], 422);
        //     }
        //     return null; // deixa o comportamento normal noutros casos
        // });

        // $exceptions->renderable(function (ProcedureExecutionException $e, Request $request) {
        //     if (! $request->routeIs('debug.sybase', 'debug.sybase-ping')) {
        //         return null;
        //     }

        //     if ($request->expectsJson() && ! $request->inertia()) {
        //         return response()->json([
        //             'ok --' => false,
        //             'cd_retorno' => $e->cdRetorno,
        //             'message' => $e->getMessage(),
        //             'msg_retorno' => $e->msgRetorno,
        //         ], 422);
        //     }

        //     $response = Inertia::render('debug/sybase-ping', [
        //         'rows' => null,
        //         'sybaseError' => [
        //             'cd_retorno' => $e->cdRetorno,
        //             'message' => $e->getMessage(),
        //             'msg_retorno ->' => $e->msgRetorno,
        //         ],
        //     ])->toResponse($request);
        //     $response->setStatusCode(422);

        //     return $response;
        // });

        // $exceptions->renderable(function (ProcedureExecutionException $e, Request $request): ?SymfonyResponse {
        //     // if (! $request->routeIs('debug.sybase', 'debug.sybase-ping')) {
        //     //     return null;
        //     // }

        //     if ($request->expectsJson() && ! $request->inertia()) {
        //         return response()->json([
        //             'ok' => false,
        //             'cd_retorno' => $e->cdRetorno,
        //             'message' => $e->getMessage(),
        //             'msg_retorno' => $e->msgRetorno,
        //         ], 422);
        //     }

        //     if ($request->inertia()) {
        //         $response = Inertia::render('debug/sybase-ping', [
        //             'rows' => null,
        //             'sybaseError' => null,
        //         ])->flash('toast', [
        //             'type' => 'error',
        //             'title' => 'ProcedureExecutionException',
        //             'message' => $e->getMessage(),
        //             'description' => $e->msgRetorno,
        //             'details' => [
        //                 'cd_retorno' => $e->cdRetorno,
        //                 'msg_retorno' => $e->msgRetorno,
        //             ],
        //         ])->toResponse($request);
        //         $response->setStatusCode(422);

        //         return $response;
        //     }

        //     // if ($request->routeIs('debug.sybase-ping')) {
        //     //     return response()->json([
        //     //         'ok' => false,
        //     //         'cd_retorno' => $e->cdRetorno,
        //     //         'message' => $e->getMessage(),
        //     //         'msg_retorno' => $e->msgRetorno,
        //     //     ], 422);
        //     // }

        //     // return null;

        //     // Inertia::flash('toast', [
        //     //     'type' => 'error',
        //     //     'title' => 'Erro na procedure',
        //     //     'message' => $e->getMessage(),
        //     //     'details' => [
        //     //         'cd_retorno' => $e->cdRetorno,
        //     //         'msg_retorno' => $e->msgRetorno,
        //     //     ],
        //     // ]);

        //     return null;
        // });
    })->create();
