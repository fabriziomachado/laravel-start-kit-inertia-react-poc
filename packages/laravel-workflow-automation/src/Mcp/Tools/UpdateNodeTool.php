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
            'name' => $schema->string()->nullable()->required()->description('New display name; use null to leave unchanged'),
            'config' => $schema->string()->nullable()->required()->description('JSON string of the new configuration object; use null to leave unchanged'),
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
            $configRaw = $request->get('config');

            if (is_string($configRaw)) {
                $decoded = json_decode($configRaw, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    return Response::error('Invalid JSON in config: '.json_last_error_msg());
                }

                if (! is_array($decoded)) {
                    return Response::error('Parameter config must decode to a JSON object.');
                }

                $data['config'] = $decoded;
            } elseif (is_array($configRaw)) {
                $data['config'] = $configRaw;
            }
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
