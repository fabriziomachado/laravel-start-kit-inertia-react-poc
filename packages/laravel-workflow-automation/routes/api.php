<?php

declare(strict_types=1);

use Aftandilmmd\WorkflowAutomation\Http\Controllers\AiBuilderController;
use Aftandilmmd\WorkflowAutomation\Http\Controllers\CredentialController;
use Aftandilmmd\WorkflowAutomation\Http\Controllers\FolderController;
use Aftandilmmd\WorkflowAutomation\Http\Controllers\MetadataController;
use Aftandilmmd\WorkflowAutomation\Http\Controllers\NodeRegistryController;
use Aftandilmmd\WorkflowAutomation\Http\Controllers\TagController;
use Aftandilmmd\WorkflowAutomation\Http\Controllers\TranscriptionController;
use Aftandilmmd\WorkflowAutomation\Http\Controllers\WebhookController;
use Aftandilmmd\WorkflowAutomation\Http\Controllers\WorkflowController;
use Aftandilmmd\WorkflowAutomation\Http\Controllers\WorkflowEdgeController;
use Aftandilmmd\WorkflowAutomation\Http\Controllers\WorkflowNodeController;
use Aftandilmmd\WorkflowAutomation\Http\Controllers\WorkflowRunController;
use Aftandilmmd\WorkflowAutomation\Http\Middleware\Authorize;
use Illuminate\Support\Facades\Route;

$prefix = config('workflow-automation.prefix', 'workflow-engine');
$middleware = config('workflow-automation.middleware', ['api']);

// ── API Routes (CRUD + execution) ──────────────────────────────
if (config('workflow-automation.api_routes', true)) {
    Route::prefix($prefix)
        ->middleware([...$middleware, Authorize::class])
        ->group(function () {
            // ── Workflows ────────────────────────────────────────────
            Route::apiResource('workflows', WorkflowController::class);
            Route::post('workflows/{workflow}/activate', [WorkflowController::class, 'activate']);
            Route::post('workflows/{workflow}/deactivate', [WorkflowController::class, 'deactivate']);
            Route::post('workflows/{workflow}/run', [WorkflowController::class, 'run']);
            Route::post('workflows/{workflow}/duplicate', [WorkflowController::class, 'duplicate']);
            Route::post('workflows/{workflow}/validate', [WorkflowController::class, 'validateWorkflow']);
            Route::post('workflows/{workflow}/test-node', [WorkflowRunController::class, 'testNode']);
            Route::post('workflows/{workflow}/ai-build', [AiBuilderController::class, 'build']);
            Route::get('ai-builder/status', [AiBuilderController::class, 'status']);

            // ── Nodes ────────────────────────────────────────────────
            Route::post('workflows/{workflow}/nodes', [WorkflowNodeController::class, 'store']);
            Route::put('workflows/{workflow}/nodes/{node}', [WorkflowNodeController::class, 'update']);
            Route::delete('workflows/{workflow}/nodes/{node}', [WorkflowNodeController::class, 'destroy']);
            Route::patch('workflows/{workflow}/nodes/{node}/position', [WorkflowNodeController::class, 'position']);
            Route::get('workflows/{workflow}/nodes/{node}/variables', [WorkflowNodeController::class, 'availableVariables']);
            Route::post('workflows/{workflow}/nodes/{node}/pin', [WorkflowNodeController::class, 'pin']);
            Route::delete('workflows/{workflow}/nodes/{node}/pin', [WorkflowNodeController::class, 'unpin']);

            // ── Edges ────────────────────────────────────────────────
            Route::post('workflows/{workflow}/edges', [WorkflowEdgeController::class, 'store']);
            Route::delete('workflows/{workflow}/edges/{edge}', [WorkflowEdgeController::class, 'destroy']);

            // ── Runs ─────────────────────────────────────────────────
            Route::get('workflows/{workflow}/runs', [WorkflowRunController::class, 'index']);
            Route::get('runs/{run}', [WorkflowRunController::class, 'show']);
            Route::post('runs/{run}/cancel', [WorkflowRunController::class, 'cancel']);
            Route::post('runs/{run}/resume', [WorkflowRunController::class, 'resume']);
            Route::post('runs/{run}/replay', [WorkflowRunController::class, 'replay']);
            Route::post('runs/{run}/retry', [WorkflowRunController::class, 'retryFromFailure']);
            Route::post('runs/{run}/retry-node', [WorkflowRunController::class, 'retryNode']);

            // ── Node Registry ────────────────────────────────────────
            Route::get('registry/nodes', [NodeRegistryController::class, 'index']);
            Route::get('registry/nodes/{key}', [NodeRegistryController::class, 'show']);
            Route::get('registry/editor-scripts', [NodeRegistryController::class, 'editorScripts']);

            // ── Metadata (models, events) ──────────────────────────
            Route::get('metadata/models', [MetadataController::class, 'models']);
            Route::get('metadata/model-events', [MetadataController::class, 'modelEvents']);

            // ── Tags ──────────────────────────────────────────────────
            Route::apiResource('tags', TagController::class)->except(['show']);

            // ── Folders ───────────────────────────────────────────────
            Route::apiResource('folders', FolderController::class)->except(['show']);

            // ── Credentials ───────────────────────────────────────────
            Route::apiResource('credentials', CredentialController::class);
            Route::get('credentials-types', [CredentialController::class, 'types']);

            // ── Transcription (STT) ─────────────────────────────────────
            Route::post('transcribe', [TranscriptionController::class, 'transcribe']);
        });
}

// ── Webhook (no auth middleware) ────────────────────────────────
if (config('workflow-automation.webhook_routes', true)) {
    $webhookPrefix = config('workflow-automation.webhook_prefix', 'workflow-webhook');

    Route::post("{$webhookPrefix}/{uuid}", [WebhookController::class, 'handle'])
        ->middleware('api')
        ->name('workflow.webhook');
}
