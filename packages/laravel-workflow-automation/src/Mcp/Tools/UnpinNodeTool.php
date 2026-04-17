<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Mcp\Tools;

use Aftandilmmd\WorkflowAutomation\Models\WorkflowNode;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Name('unpin_node')]
#[Title('Unpin Node Test Data')]
#[Description('Remove pinned test data from a node. The node will resume normal execution during test runs.')]
final class UnpinNodeTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'node_id' => $schema->integer()->required()->description('The node ID to unpin'),
        ];
    }

    public function handle(Request $request): Response
    {
        $node = WorkflowNode::findOrFail($request->get('node_id'));

        $node->update(['pinned_data' => null]);
        $node->refresh();

        return Response::json([
            'id' => $node->id,
            'workflow_id' => $node->workflow_id,
            'name' => $node->name,
            'node_key' => $node->node_key,
            'pinned_data' => null,
        ]);
    }
}
