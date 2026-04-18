<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Lab404\Impersonate\Services\ImpersonateManager;

final class HandleInertiaRequests extends Middleware
{
    /**
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        /** @var ImpersonateManager $impersonate */
        $impersonate = app('impersonate');
        $isImpersonating = $request->user() !== null && $impersonate->isImpersonating();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'impersonating' => $isImpersonating,
            'impersonator' => $isImpersonating ? $impersonate->getImpersonator() : null,
            'flows_error' => fn (): ?string => $request->session()->pull('flows_error'),
            'flows_success' => fn (): ?string => $request->session()->pull('flows_success'),
        ];
    }
}
