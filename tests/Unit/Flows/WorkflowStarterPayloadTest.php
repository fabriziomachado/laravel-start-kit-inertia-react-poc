<?php

declare(strict_types=1);

use Aftandilmmd\WorkflowAutomation\Models\WorkflowRun;
use App\Flows\WorkflowStarterPayload;
use App\Models\User;

it('starterUserId lê canonical e legacy', function (): void {
    $user = User::factory()->create();
    $payload = WorkflowStarterPayload::forUser($user);
    expect($payload[0])->toHaveKey(WorkflowStarterPayload::STARTER_USER_ID_KEY);

    $run1 = WorkflowRun::factory()->create([
        'initial_payload' => [[WorkflowStarterPayload::STARTER_USER_ID_KEY => '123']],
    ]);
    expect(WorkflowStarterPayload::starterUserId($run1))->toBe('123');

    $run2 = WorkflowRun::factory()->create([
        'initial_payload' => [[WorkflowStarterPayload::LEGACY_MATRICULA_USER_ID_KEY => '999']],
    ]);
    expect(WorkflowStarterPayload::starterUserId($run2))->toBe('999');

    $run3 = WorkflowRun::factory()->create(['initial_payload' => ['oops']]);
    expect(WorkflowStarterPayload::starterUserId($run3))->toBeNull();

    $run4 = WorkflowRun::factory()->create(['initial_payload' => [[]]]);
    expect(WorkflowStarterPayload::starterUserId($run4))->toBeNull();

    expect(WorkflowStarterPayload::starterUserIdFromInitialPayloadRow([
        WorkflowStarterPayload::STARTER_USER_ID_KEY => '1',
    ]))->toBe('1');
    expect(WorkflowStarterPayload::starterUserIdFromInitialPayloadRow([
        WorkflowStarterPayload::LEGACY_MATRICULA_USER_ID_KEY => '2',
    ]))->toBe('2');
    expect(WorkflowStarterPayload::starterUserIdFromInitialPayloadRow([]))->toBeNull();
});

