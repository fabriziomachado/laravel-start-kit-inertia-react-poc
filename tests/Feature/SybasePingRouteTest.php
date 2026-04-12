<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

it('registers the sybase debug routes', function (): void {
    $uris = collect(Route::getRoutes())->map->uri()->all();

    expect($uris)->toContain('sybase')->and($uris)->toContain('__sybase-ping');
});

it('names the sybase debug routes', function (): void {
    expect(Route::has('debug.sybase'))->toBeTrue()
        ->and(Route::has('debug.sybase-ping'))->toBeTrue();
});
