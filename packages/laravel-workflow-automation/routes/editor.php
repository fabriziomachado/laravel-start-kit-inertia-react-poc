<?php

declare(strict_types=1);

use Aftandilmmd\WorkflowAutomation\Http\Middleware\Authorize;
use Illuminate\Support\Facades\Route;

$spaHandler = function () {
    $published = public_path('workflow-editor/index.html');
    $packaged = __DIR__.'/../ui/dist/index.html';

    $path = file_exists($published) ? $published : $packaged;

    if (! file_exists($path)) {
        abort(404, 'Workflow UI not built. Run: cd vendor/aftandilmmd/laravel-workflow-automation/ui && npm install && npm run build');
    }

    $contents = file_get_contents($path);
    if ($contents === false) {
        abort(500, 'Failed to read workflow editor index.');
    }

    return response($contents, 200, ['Content-Type' => 'text/html']);
};

$assetHandler = function (string $file) {
    $published = public_path("workflow-editor/assets/{$file}");
    $packaged = __DIR__."/../ui/dist/assets/{$file}";

    $path = file_exists($published) ? $published : $packaged;

    if (! file_exists($path)) {
        abort(404);
    }

    $mime = match (pathinfo($file, PATHINFO_EXTENSION)) {
        'js' => 'application/javascript',
        'css' => 'text/css',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'ico' => 'image/x-icon',
        'woff2' => 'font/woff2',
        'woff' => 'font/woff',
        default => 'application/octet-stream',
    };

    $contents = file_get_contents($path);
    if ($contents === false) {
        abort(500, 'Failed to read workflow editor asset.');
    }

    return response($contents, 200, ['Content-Type' => $mime]);
};

// Static assets (JS, CSS, fonts) — must come before catch-all
Route::get('workflow-editor/assets/{file}', $assetHandler)->name('workflow.ui.assets');

// SPA catch-all — two routes to avoid optional parameter issues
Route::middleware(Authorize::class)->group(function () use ($spaHandler) {
    Route::get('workflow-editor', $spaHandler)->name('workflow.ui');
    Route::get('workflow-editor/{path}', $spaHandler)->where('path', '.*')->name('workflow.ui.spa');
});
