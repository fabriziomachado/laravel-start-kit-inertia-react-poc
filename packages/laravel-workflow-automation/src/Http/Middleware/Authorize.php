<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class Authorize
{
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        return $this->allowedToAccess($request)
            ? $next($request)
            : abort(Response::HTTP_FORBIDDEN);
    }

    private function allowedToAccess(Request $request): bool
    {
        if (app()->environment('local')) {
            return true;
        }

        return Gate::check('viewWorkflowAutomation');
    }
}
