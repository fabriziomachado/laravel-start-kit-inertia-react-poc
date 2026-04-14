<?php

declare(strict_types=1);

arch()->preset()->php();
arch()->preset()->strict();
arch()->preset()->laravel()->ignoring([
    'App\Http\Controllers\Auth\OtpzController',
    'App\Http\Controllers\Concerns\HasInertiaFallback',
    'App\Http\Controllers\SybasePingController',
]);
arch()->preset()->security()->ignoring([
    'assert',
]);

// arch('controllers')
//     ->expect('App\Http\Controllers')
//     ->not->toBeUsed();

//

