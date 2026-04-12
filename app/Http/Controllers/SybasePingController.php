<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\Sau\PrssLoginR01Dto;
use App\Data\Sau\PrssLoginR01ResultDto;
use App\Http\Controllers\Concerns\HasInertiaFallback;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final readonly class SybasePingController implements HasInertiaFallback
{
    public function __invoke(): Response
    {
        // Get credentials from config
        ['credentialId' => $credentialId, 'credentialType' => $credentialType, 'password' => $password] = config('sau.prss_login', []);

        // Create credentials object
        $credentials = new PrssLoginR01Dto(
            credentialId: (string) $credentialId,
            credentialType: (string) $credentialType,
            password: (string) $password,
        );

        $resultset = DB::connection('sau')
            ->rpc('prss_login_r01')
            ->with($credentials)
            ->throwOnError()
            ->getAs(PrssLoginR01ResultDto::class);

        // return redirect()->route('debug.sybase-ping');

        return Inertia::render('debug/sybase-ping', [
            'rows' => $resultset,
            'sybaseError' => null,
        ]);
    }

    public function inertiaFallback(): array
    {
        return ['debug/sybase-ping', ['rows' => null, 'sybaseError' => null]];
    }
}
