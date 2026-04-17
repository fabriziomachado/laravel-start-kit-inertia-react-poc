<?php

declare(strict_types=1);

use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowEdge;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowNode;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowNodeRun;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowRun;

// ── List / Show ──────────────────────────────────────────────────

it('lists workflows', function () {
    Workflow::factory()->count(3)->create();

    $this->getJson('/workflow-engine/workflows')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('shows a workflow with nodes and edges', function () {
    $workflow = Workflow::factory()->create();
    WorkflowNode::factory()->trigger()->create(['workflow_id' => $workflow->id]);
    WorkflowNode::factory()->action('set_fields')->withConfig(['fields' => []])->create(['workflow_id' => $workflow->id]);

    $this->getJson("/workflow-engine/workflows/{$workflow->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $workflow->id)
        ->assertJsonCount(2, 'data.nodes');
});

// ── Create / Update / Delete ─────────────────────────────────────

it('creates a workflow', function () {
    $this->postJson('/workflow-engine/workflows', [
        'name' => 'Test Workflow',
        'description' => 'A test workflow',
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Test Workflow');

    $this->assertDatabaseHas('workflows', ['name' => 'Test Workflow']);
});

it('validates required fields on create', function () {
    $this->postJson('/workflow-engine/workflows', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('updates a workflow', function () {
    $workflow = Workflow::factory()->create(['name' => 'Old Name']);

    $this->putJson("/workflow-engine/workflows/{$workflow->id}", [
        'name' => 'New Name',
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'New Name');
});

it('deletes a workflow', function () {
    $workflow = Workflow::factory()->create();

    $this->deleteJson("/workflow-engine/workflows/{$workflow->id}")
        ->assertOk();

    $this->assertDatabaseMissing('workflows', ['id' => $workflow->id]);
});

it('deletes a workflow and removes related runs and nodes', function () {
    $workflow = Workflow::factory()->create();
    $node = WorkflowNode::factory()->trigger()->create(['workflow_id' => $workflow->id]);
    $run = WorkflowRun::factory()->create([
        'workflow_id' => $workflow->id,
        'trigger_node_id' => $node->id,
    ]);
    WorkflowNodeRun::factory()->create([
        'workflow_run_id' => $run->id,
        'node_id' => $node->id,
    ]);

    $this->deleteJson("/workflow-engine/workflows/{$workflow->id}")
        ->assertOk();

    $this->assertDatabaseMissing('workflows', ['id' => $workflow->id]);
    $this->assertDatabaseMissing(config('workflow-automation.tables.runs', 'workflow_runs'), ['id' => $run->id]);
    $this->assertDatabaseMissing(config('workflow-automation.tables.nodes', 'workflow_nodes'), ['id' => $node->id]);
});

// ── Activate / Deactivate ────────────────────────────────────────

it('activates a workflow', function () {
    $workflow = Workflow::factory()->create(['is_active' => false]);

    $this->postJson("/workflow-engine/workflows/{$workflow->id}/activate")
        ->assertOk()
        ->assertJsonPath('data.is_active', true);
});

it('deactivates a workflow', function () {
    $workflow = Workflow::factory()->active()->create();

    $this->postJson("/workflow-engine/workflows/{$workflow->id}/deactivate")
        ->assertOk()
        ->assertJsonPath('data.is_active', false);
});

// ── Duplicate ────────────────────────────────────────────────────

it('duplicates a workflow with nodes and edges', function () {
    $workflow = Workflow::factory()->active()->create(['name' => 'Original']);
    $trigger = WorkflowNode::factory()->trigger()->create(['workflow_id' => $workflow->id]);
    $action = WorkflowNode::factory()->action('set_fields')->withConfig(['fields' => []])->create(['workflow_id' => $workflow->id]);
    WorkflowEdge::factory()->create([
        'workflow_id' => $workflow->id,
        'source_node_id' => $trigger->id,
        'target_node_id' => $action->id,
    ]);

    $this->postJson("/workflow-engine/workflows/{$workflow->id}/duplicate")
        ->assertOk()
        ->assertJsonPath('data.name', 'Original (Copy)')
        ->assertJsonPath('data.is_active', false)
        ->assertJsonCount(2, 'data.nodes')
        ->assertJsonCount(1, 'data.edges');
});

// ── Validate ─────────────────────────────────────────────────────

it('validates a valid workflow', function () {
    $workflow = Workflow::factory()->create();
    $trigger = WorkflowNode::factory()->trigger()->create(['workflow_id' => $workflow->id, 'name' => 'trigger']);
    $action = WorkflowNode::factory()->action('set_fields')->withConfig(['fields' => ['x' => 'y']])->create([
        'workflow_id' => $workflow->id, 'name' => 'action',
    ]);
    WorkflowEdge::factory()->create([
        'workflow_id' => $workflow->id,
        'source_node_id' => $trigger->id,
        'target_node_id' => $action->id,
    ]);

    $this->postJson("/workflow-engine/workflows/{$workflow->id}/validate")
        ->assertOk()
        ->assertJsonPath('valid', true)
        ->assertJsonPath('errors', []);
});

it('returns errors for invalid workflow', function () {
    $workflow = Workflow::factory()->create();
    // No trigger = invalid

    $this->postJson("/workflow-engine/workflows/{$workflow->id}/validate")
        ->assertUnprocessable()
        ->assertJsonPath('valid', false);
});

// ── Nodes ────────────────────────────────────────────────────────

it('adds a node to a workflow', function () {
    $workflow = Workflow::factory()->create();

    $this->postJson("/workflow-engine/workflows/{$workflow->id}/nodes", [
        'node_key' => 'manual',
        'name' => 'My Trigger',
    ])
        ->assertCreated()
        ->assertJsonPath('data.node_key', 'manual')
        ->assertJsonPath('data.name', 'My Trigger');
});

it('rejects unknown node key', function () {
    $workflow = Workflow::factory()->create();

    $this->postJson("/workflow-engine/workflows/{$workflow->id}/nodes", [
        'node_key' => 'completely_fake',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['node_key']);
});

it('updates a node', function () {
    $workflow = Workflow::factory()->create();
    $node = WorkflowNode::factory()->trigger()->create(['workflow_id' => $workflow->id, 'name' => 'Old']);

    $this->putJson("/workflow-engine/workflows/{$workflow->id}/nodes/{$node->id}", [
        'name' => 'New Name',
        'config' => ['foo' => 'bar'],
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'New Name');
});

it('deletes a node', function () {
    $workflow = Workflow::factory()->create();
    $node = WorkflowNode::factory()->trigger()->create(['workflow_id' => $workflow->id]);

    $this->deleteJson("/workflow-engine/workflows/{$workflow->id}/nodes/{$node->id}")
        ->assertOk();

    $this->assertDatabaseMissing('workflow_nodes', ['id' => $node->id]);
});

it('updates node position', function () {
    $workflow = Workflow::factory()->create();
    $node = WorkflowNode::factory()->trigger()->create(['workflow_id' => $workflow->id]);

    $this->patchJson("/workflow-engine/workflows/{$workflow->id}/nodes/{$node->id}/position", [
        'position_x' => 100,
        'position_y' => 200,
    ])
        ->assertOk()
        ->assertJsonPath('data.position_x', 100)
        ->assertJsonPath('data.position_y', 200);
});

// ── Edges ────────────────────────────────────────────────────────

it('creates an edge', function () {
    $workflow = Workflow::factory()->create();
    $trigger = WorkflowNode::factory()->trigger()->create(['workflow_id' => $workflow->id]);
    $action = WorkflowNode::factory()->action('set_fields')->withConfig(['fields' => []])->create(['workflow_id' => $workflow->id]);

    $this->postJson("/workflow-engine/workflows/{$workflow->id}/edges", [
        'source_node_id' => $trigger->id,
        'target_node_id' => $action->id,
    ])
        ->assertCreated()
        ->assertJsonPath('data.source_node_id', $trigger->id)
        ->assertJsonPath('data.target_node_id', $action->id);
});

it('rejects self-referencing edge', function () {
    $workflow = Workflow::factory()->create();
    $node = WorkflowNode::factory()->trigger()->create(['workflow_id' => $workflow->id]);

    $this->postJson("/workflow-engine/workflows/{$workflow->id}/edges", [
        'source_node_id' => $node->id,
        'target_node_id' => $node->id,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['target_node_id']);
});

it('deletes an edge', function () {
    $workflow = Workflow::factory()->create();
    $trigger = WorkflowNode::factory()->trigger()->create(['workflow_id' => $workflow->id]);
    $action = WorkflowNode::factory()->action('set_fields')->withConfig(['fields' => []])->create(['workflow_id' => $workflow->id]);
    $edge = WorkflowEdge::factory()->create([
        'workflow_id' => $workflow->id,
        'source_node_id' => $trigger->id,
        'target_node_id' => $action->id,
    ]);

    $this->deleteJson("/workflow-engine/workflows/{$workflow->id}/edges/{$edge->id}")
        ->assertOk();

    $this->assertDatabaseMissing('workflow_edges', ['id' => $edge->id]);
});

// ── Registry ─────────────────────────────────────────────────────

it('lists all available node types', function () {
    $this->getJson('/workflow-engine/registry/nodes')
        ->assertOk()
        ->assertJsonStructure(['data' => [['key', 'label', 'type', 'input_ports', 'output_ports', 'config_schema']]]);
});

it('shows a single node type', function () {
    $this->getJson('/workflow-engine/registry/nodes/manual')
        ->assertOk()
        ->assertJsonPath('data.key', 'manual');
});

it('returns 404 for unknown node type', function () {
    $this->getJson('/workflow-engine/registry/nodes/does_not_exist')
        ->assertNotFound();
});
