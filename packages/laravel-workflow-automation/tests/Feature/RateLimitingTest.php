<?php

declare(strict_types=1);

use Aftandilmmd\WorkflowAutomation\Enums\RunStatus;
use Aftandilmmd\WorkflowAutomation\Exceptions\RateLimitExceededException;
use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowNode;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowRun;
use Aftandilmmd\WorkflowAutomation\Services\ConcurrencyGuard;
use Aftandilmmd\WorkflowAutomation\Services\WorkflowService;

// ── Per-Workflow Limits ──────────────────────────────────────────

it('allows execution when under per-workflow limit', function () {
    config()->set('workflow-automation.rate_limiting.max_concurrent_per_workflow', 3);

    $workflow = Workflow::factory()->active()->create();
    WorkflowNode::factory()->trigger()->create(['workflow_id' => $workflow->id]);

    // Create 2 running runs (under limit of 3)
    WorkflowRun::factory()->count(2)->create([
        'workflow_id' => $workflow->id,
        'status' => RunStatus::Running,
    ]);

    $service = app(WorkflowService::class);
    $run = $service->run($workflow, [['test' => true]]);

    expect($run->status)->toBe(RunStatus::Completed);
});

it('blocks execution when per-workflow limit is reached', function () {
    config()->set('workflow-automation.rate_limiting.max_concurrent_per_workflow', 2);

    $workflow = Workflow::factory()->active()->create();
    WorkflowNode::factory()->trigger()->create(['workflow_id' => $workflow->id]);

    // Create 2 running runs (at limit)
    WorkflowRun::factory()->count(2)->create([
        'workflow_id' => $workflow->id,
        'status' => RunStatus::Running,
    ]);

    $service = app(WorkflowService::class);

    expect(fn () => $service->run($workflow, [['test' => true]]))
        ->toThrow(RateLimitExceededException::class);
});

it('respects per-workflow settings over config default', function () {
    config()->set('workflow-automation.rate_limiting.max_concurrent_per_workflow', 10);

    $workflow = Workflow::factory()->active()->create([
        'settings' => ['max_concurrent_runs' => 1],
    ]);
    WorkflowNode::factory()->trigger()->create(['workflow_id' => $workflow->id]);

    WorkflowRun::factory()->create([
        'workflow_id' => $workflow->id,
        'status' => RunStatus::Running,
    ]);

    $service = app(WorkflowService::class);

    expect(fn () => $service->run($workflow, [['test' => true]]))
        ->toThrow(RateLimitExceededException::class);
});

it('does not count completed runs toward the limit', function () {
    config()->set('workflow-automation.rate_limiting.max_concurrent_per_workflow', 2);

    $workflow = Workflow::factory()->active()->create();
    WorkflowNode::factory()->trigger()->create(['workflow_id' => $workflow->id]);

    // Completed + Failed runs should not count
    WorkflowRun::factory()->create([
        'workflow_id' => $workflow->id,
        'status' => RunStatus::Completed,
    ]);
    WorkflowRun::factory()->create([
        'workflow_id' => $workflow->id,
        'status' => RunStatus::Failed,
    ]);
    WorkflowRun::factory()->create([
        'workflow_id' => $workflow->id,
        'status' => RunStatus::Running,
    ]);

    $service = app(WorkflowService::class);
    $run = $service->run($workflow, [['test' => true]]);

    expect($run->status)->toBe(RunStatus::Completed);
});

// ── Global Limits ────────────────────────────────────────────────

it('blocks execution when global limit is reached', function () {
    config()->set('workflow-automation.rate_limiting.global_max_concurrent', 2);

    $workflow1 = Workflow::factory()->active()->create();
    WorkflowNode::factory()->trigger()->create(['workflow_id' => $workflow1->id]);

    $workflow2 = Workflow::factory()->active()->create();
    WorkflowNode::factory()->trigger()->create(['workflow_id' => $workflow2->id]);

    // 2 runs across different workflows
    WorkflowRun::factory()->create([
        'workflow_id' => $workflow1->id,
        'status' => RunStatus::Running,
    ]);
    WorkflowRun::factory()->create([
        'workflow_id' => $workflow2->id,
        'status' => RunStatus::Pending,
    ]);

    $service = app(WorkflowService::class);

    expect(fn () => $service->run($workflow1, [['test' => true]]))
        ->toThrow(RateLimitExceededException::class);
});

it('allows execution when global limit is not reached', function () {
    config()->set('workflow-automation.rate_limiting.global_max_concurrent', 5);

    $workflow = Workflow::factory()->active()->create();
    WorkflowNode::factory()->trigger()->create(['workflow_id' => $workflow->id]);

    WorkflowRun::factory()->count(3)->create([
        'workflow_id' => $workflow->id,
        'status' => RunStatus::Running,
    ]);

    $service = app(WorkflowService::class);
    $run = $service->run($workflow, [['test' => true]]);

    expect($run->status)->toBe(RunStatus::Completed);
});

// ── No Limits (default) ─────────────────────────────────────────

it('allows unlimited runs when limits are zero', function () {
    config()->set('workflow-automation.rate_limiting.global_max_concurrent', 0);
    config()->set('workflow-automation.rate_limiting.max_concurrent_per_workflow', 0);

    $workflow = Workflow::factory()->active()->create();
    WorkflowNode::factory()->trigger()->create(['workflow_id' => $workflow->id]);

    WorkflowRun::factory()->count(50)->create([
        'workflow_id' => $workflow->id,
        'status' => RunStatus::Running,
    ]);

    $service = app(WorkflowService::class);
    $run = $service->run($workflow, [['test' => true]]);

    expect($run->status)->toBe(RunStatus::Completed);
});

// ── ConcurrencyGuard canRun / status ────────────────────────────

it('returns correct canRun status', function () {
    config()->set('workflow-automation.rate_limiting.max_concurrent_per_workflow', 1);

    $workflow = Workflow::factory()->active()->create();

    $guard = app(ConcurrencyGuard::class);

    expect($guard->canRun($workflow))->toBeTrue();

    WorkflowRun::factory()->create([
        'workflow_id' => $workflow->id,
        'status' => RunStatus::Running,
    ]);

    expect($guard->canRun($workflow))->toBeFalse();
});

it('returns detailed status information', function () {
    config()->set('workflow-automation.rate_limiting.global_max_concurrent', 10);
    config()->set('workflow-automation.rate_limiting.max_concurrent_per_workflow', 5);

    $workflow = Workflow::factory()->active()->create();

    WorkflowRun::factory()->count(3)->create([
        'workflow_id' => $workflow->id,
        'status' => RunStatus::Running,
    ]);

    $guard = app(ConcurrencyGuard::class);
    $status = $guard->status($workflow);

    expect($status)->toHaveKeys(['global', 'workflow', 'can_run'])
        ->and($status['global']['max_concurrent'])->toBe(10)
        ->and($status['global']['active_runs'])->toBe(3)
        ->and($status['global']['available'])->toBe(7)
        ->and($status['workflow']['max_concurrent'])->toBe(5)
        ->and($status['workflow']['active_runs'])->toBe(3)
        ->and($status['workflow']['available'])->toBe(2)
        ->and($status['can_run'])->toBeTrue();
});

// ── WorkflowService convenience methods ─────────────────────────

it('exposes canRun via WorkflowService', function () {
    config()->set('workflow-automation.rate_limiting.max_concurrent_per_workflow', 1);

    $workflow = Workflow::factory()->active()->create();

    $service = app(WorkflowService::class);
    expect($service->canRun($workflow))->toBeTrue();

    WorkflowRun::factory()->create([
        'workflow_id' => $workflow->id,
        'status' => RunStatus::Running,
    ]);

    expect($service->canRun($workflow))->toBeFalse();
});

it('exposes rateLimitStatus via WorkflowService', function () {
    config()->set('workflow-automation.rate_limiting.max_concurrent_per_workflow', 3);

    $workflow = Workflow::factory()->active()->create();
    $service = app(WorkflowService::class);

    $status = $service->rateLimitStatus($workflow);

    expect($status['workflow']['max_concurrent'])->toBe(3)
        ->and($status['workflow']['active_runs'])->toBe(0)
        ->and($status['can_run'])->toBeTrue();
});

// ── Exception properties ────────────────────────────────────────

it('includes context in RateLimitExceededException', function () {
    $exception = new RateLimitExceededException(
        workflowId: 42,
        currentRuns: 5,
        maxConcurrent: 5,
        scope: 'workflow',
    );

    expect($exception->workflowId)->toBe(42)
        ->and($exception->currentRuns)->toBe(5)
        ->and($exception->maxConcurrent)->toBe(5)
        ->and($exception->scope)->toBe('workflow')
        ->and($exception->getMessage())->toContain('Workflow 42');
});

it('has correct message for global scope', function () {
    $exception = new RateLimitExceededException(
        workflowId: 1,
        currentRuns: 10,
        maxConcurrent: 10,
        scope: 'global',
    );

    expect($exception->getMessage())->toContain('Global concurrency limit');
});
