<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;

it('allows access in local environment', function () {
    app()->detectEnvironment(fn () => 'local');

    Gate::define('viewWorkflowAutomation', fn ($user = null) => false);

    $this->getJson('/workflow-engine/workflows')
        ->assertOk();
});

it('denies access in production without gate', function () {
    app()->detectEnvironment(fn () => 'production');

    Gate::define('viewWorkflowAutomation', fn ($user = null) => false);

    $this->getJson('/workflow-engine/workflows')
        ->assertForbidden();
});

it('allows access in production when gate returns true', function () {
    app()->detectEnvironment(fn () => 'production');

    Gate::define('viewWorkflowAutomation', fn ($user = null) => true);

    $this->getJson('/workflow-engine/workflows')
        ->assertOk();
});

it('denies access to editor in production without gate', function () {
    app()->detectEnvironment(fn () => 'production');

    Gate::define('viewWorkflowAutomation', fn ($user = null) => false);

    $this->get('/workflow-editor')
        ->assertForbidden();
});

it('allows access to editor in local environment', function () {
    app()->detectEnvironment(fn () => 'local');

    Gate::define('viewWorkflowAutomation', fn ($user = null) => false);

    $this->get('/workflow-editor')
        ->assertSuccessful();
});
