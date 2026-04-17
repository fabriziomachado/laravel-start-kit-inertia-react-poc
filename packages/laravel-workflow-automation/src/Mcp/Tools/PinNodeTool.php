<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Mcp\Tools;

use Aftandilmmd\WorkflowAutomation\Models\WorkflowNode;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowNodeRun;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Name('pin_node')]
#[Title('Pin Node Test Data')]
#[Description('Pin fixed test data (input/output) to a node. When pinned output exists, the node is skipped during test runs. When pinned input exists, the node executes with the pinned input instead of computed input. Supports two sources: "run" (from a previous node run) or "manual" (custom JSON data).')]
final class PinNodeTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'node_id' => $schema->integer()->required()->description('The node ID to pin data to'),
            'source' => $schema->string()->required()->description('Pin source: "run" (from a node run) or "manual" (custom data)'),
            'node_run_id' => $schema->integer()->description('Required when source is "run". The node run ID to copy input/output from.'),
            'input' => $schema->array()->description('Manual input items to pin (when source is "manual")'),
            'output' => $schema->object()->description('Manual output to pin, keyed by port name e.g. {"main": [...]} (when source is "manual")'),
        ];
    }

    public function handle(Request $request): Response
    {
        $node = WorkflowNode::findOrFail($request->get('node_id'));
        $source = $request->get('source');

        if ($source === 'run') {
            $nodeRun = WorkflowNodeRun::findOrFail($request->get('node_run_id'));

            if ($nodeRun->node_id !== $node->id) {
                return Response::error('Node run does not belong to this node.');
            }

            $pinnedData = [
                'input' => $nodeRun->input,
                'output' => $nodeRun->output,
                'source_run_id' => $nodeRun->workflow_run_id,
            ];
        } else {
            $pinnedData = array_filter([
                'input' => $request->get('input'),
                'output' => $request->get('output'),
            ], fn ($v) => $v !== null);
        }

        $node->update(['pinned_data' => $pinnedData]);
        $node->refresh();

        return Response::json([
            'id' => $node->id,
            'workflow_id' => $node->workflow_id,
            'name' => $node->name,
            'node_key' => $node->node_key,
            'pinned_data' => $node->pinned_data,
        ]);
    }
}
