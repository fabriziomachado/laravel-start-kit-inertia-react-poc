<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

it('regista as rotas nomeadas do editor e da API de workflows', function (): void {
    expect(Route::has('workflow.ui'))->toBeTrue()
        ->and(Route::has('workflow.ui.spa'))->toBeTrue();
});

it('permite aceder ao editor de workflows em ambiente local', function (): void {
    app()->detectEnvironment(fn (): string => 'local');

    Gate::define('viewWorkflowAutomation', fn ($user = null): bool => false);

    $this->get('/workflow-editor')
        ->assertSuccessful()
        ->assertSee('Workflow Editor');
});

it('permite listar workflows via API em ambiente local', function (): void {
    app()->detectEnvironment(fn (): string => 'local');

    Gate::define('viewWorkflowAutomation', fn ($user = null): bool => false);

    $this->getJson('/workflow-engine/workflows')->assertOk();
});

it('carrega os pacotes Laravel AI e MCP', function (): void {
    expect(class_exists(Laravel\Ai\AiManager::class))->toBeTrue()
        ->and(class_exists(Laravel\Mcp\Facades\Mcp::class))->toBeTrue();
});
