<?php

declare(strict_types=1);

use Aftandilmmd\WorkflowAutomation\Enums\NodeType;
use Aftandilmmd\WorkflowAutomation\Jobs\ExecuteWorkflowJob;
use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowEdge;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowNode;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

it('accepts a valid webhook and dispatches a job', function () {
    Queue::fake();

    $uuid = Str::uuid()->toString();

    $workflow = Workflow::factory()->active()->create();
    $trigger = WorkflowNode::factory()->trigger('webhook')->create([
        'workflow_id' => $workflow->id,
        'name' => 'Webhook Trigger',
        'config' => [
            'path' => $uuid,
            'method' => 'POST',
            'auth_type' => 'none',
        ],
    ]);
    $action = WorkflowNode::factory()->create([
        'workflow_id' => $workflow->id,
        'type' => NodeType::Transformer,
        'node_key' => 'set_fields',
        'name' => 'Action',
        'config' => ['fields' => ['processed' => true]],
    ]);
    WorkflowEdge::factory()->create([
        'workflow_id' => $workflow->id,
        'source_node_id' => $trigger->id,
        'target_node_id' => $action->id,
    ]);

    $this->postJson("/workflow-webhook/{$uuid}", ['order_id' => 123])
        ->assertStatus(202)
        ->assertJsonPath('message', 'Webhook received.');

    Queue::assertPushed(ExecuteWorkflowJob::class, function ($job) use ($workflow) {
        return $job->workflowId === $workflow->id;
    });
});

it('returns 404 for unknown webhook path', function () {
    $this->postJson('/workflow-webhook/nonexistent-uuid', ['data' => 1])
        ->assertNotFound();
});

it('returns 405 for wrong HTTP method', function () {
    $uuid = Str::uuid()->toString();

    $workflow = Workflow::factory()->active()->create();
    WorkflowNode::factory()->trigger('webhook')->create([
        'workflow_id' => $workflow->id,
        'name' => 'Webhook',
        'config' => [
            'path' => $uuid,
            'method' => 'PUT',
            'auth_type' => 'none',
        ],
    ]);

    $this->postJson("/workflow-webhook/{$uuid}", ['data' => 1])
        ->assertStatus(405);
});

it('returns 401 for invalid bearer token', function () {
    $uuid = Str::uuid()->toString();

    $workflow = Workflow::factory()->active()->create();
    WorkflowNode::factory()->trigger('webhook')->create([
        'workflow_id' => $workflow->id,
        'name' => 'Webhook',
        'config' => [
            'path' => $uuid,
            'method' => 'POST',
            'auth_type' => 'bearer',
            'auth_value' => 'secret-token-123',
        ],
    ]);

    $this->postJson("/workflow-webhook/{$uuid}", ['data' => 1], [
        'Authorization' => 'Bearer wrong-token',
    ])->assertUnauthorized();
});

it('accepts valid bearer token', function () {
    Queue::fake();

    $uuid = Str::uuid()->toString();

    $workflow = Workflow::factory()->active()->create();
    WorkflowNode::factory()->trigger('webhook')->create([
        'workflow_id' => $workflow->id,
        'name' => 'Webhook',
        'config' => [
            'path' => $uuid,
            'method' => 'POST',
            'auth_type' => 'bearer',
            'auth_value' => 'secret-token-123',
        ],
    ]);

    $this->postJson("/workflow-webhook/{$uuid}", ['data' => 1], [
        'Authorization' => 'Bearer secret-token-123',
    ])->assertStatus(202);

    Queue::assertPushed(ExecuteWorkflowJob::class);
});
