<?php

declare(strict_types=1);

arch()->preset()->php()->ignoring([
    'Aftandilmmd\\WorkflowAutomation\\',
]);

arch()->preset()->strict()->ignoring([
    'Aftandilmmd\\WorkflowAutomation\\',
]);

arch()->preset()->laravel()->ignoring([
    'Aftandilmmd\\WorkflowAutomation\\',
    'App\Http\Controllers\Auth\OtpzController',
    'App\Http\Controllers\Concerns\HasInertiaFallback',
    'App\Http\Controllers\SybasePingController',
    'App\Http\Controllers\WorkflowApprovalController',
    'App\Http\Controllers\WorkflowFormController',
]);

arch()->preset()->security()->ignoring([
    'assert',
]);

// arch('controllers')
//     ->expect('App\Http\Controllers')
//     ->not->toBeUsed();

//
