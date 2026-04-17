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

#[Name('update_node')]
#[Title('Update Node')]
#[Description('Update a node\'s name or configuration.')]
final class UpdateNodeTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'node_id' => $schema->integer()->required()->description('The node ID'),
            'name' => $schema->string()->description('New display name for the node'),
            'config' => $schema->object()->description('New configuration for the node'),
        ];
    }

    public function handle(Request $request): Response
    {
        $node = WorkflowNode::findOrFail($request->get('node_id'));

        $data = [];

        if ($request->get('name') !== null) {
            $data['name'] = $request->get('name');
        }

        if ($request->get('config') !== null) {
            $data['config'] = $request->get('config');
        }

        $node->update($data);

        return Response::json([
            'id' => $node->id,
            'workflow_id' => $node->workflow_id,
            'name' => $node->name,
            'node_key' => $node->node_key,
            'type' => $node->type->value,
            'config' => $node->config,
        ]);
    }
}
