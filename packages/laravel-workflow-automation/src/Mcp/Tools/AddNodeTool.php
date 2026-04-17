<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Mcp\Tools;

use Aftandilmmd\WorkflowAutomation\Services\WorkflowService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Name('add_node')]
#[Title('Add Node')]
#[Description('Add a node to a workflow. Use list_node_types first to see available node types and their config schema.')]
final class AddNodeTool extends Tool
{
    public function __construct(
        protected WorkflowService $service,
    ) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'workflow_id' => $schema->integer()->required()->description('The workflow ID'),
            'node_key' => $schema->string()->required()->description('Node type key e.g. send_mail, if_condition. Use list_node_types to see all.'),
            'name' => $schema->string()->required()->description('Display name for the node'),
            'config' => $schema->string()->required()->description('JSON string of the node configuration object (use "{}" if the node has no config fields). Use show_node_type to see the schema.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $configRaw = $request->get('config');
        $config = self::normalizeConfigArgument($configRaw);

        if ($config === null) {
            return Response::error('Parameter config must be a JSON object, a JSON string of an object, or an empty array.');
        }

        $node = $this->service->addNode(
            $request->get('workflow_id'),
            $request->get('node_key'),
            $config,
            $request->get('name'),
        );

        return Response::json([
            'id' => $node->id,
            'workflow_id' => $node->workflow_id,
            'name' => $node->name,
            'node_key' => $node->node_key,
            'type' => $node->type->value,
            'config' => $node->config,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function normalizeConfigArgument(mixed $configRaw): ?array
    {
        if (is_array($configRaw)) {
            return $configRaw;
        }

        if (! is_string($configRaw)) {
            return null;
        }

        $decoded = json_decode($configRaw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }
}
