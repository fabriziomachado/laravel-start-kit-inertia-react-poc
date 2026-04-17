<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Mcp\Tools;

use Aftandilmmd\WorkflowAutomation\Registry\NodeRegistry;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('show_node_type')]
#[Title('Show Node Type')]
#[Description('Get detailed information about a specific node type including its full config schema, input ports, and output ports.')]
#[IsReadOnly]
final class ShowNodeTypeTool extends Tool
{
    public function __construct(
        protected NodeRegistry $registry,
    ) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'node_key' => $schema->string()->required()->description('The node type key, e.g. send_mail, if_condition'),
        ];
    }

    public function handle(Request $request): Response
    {
        $key = $request->get('node_key');

        if (! $this->registry->has($key)) {
            return Response::error("Node type '{$key}' not found.");
        }

        $meta = $this->registry->getMeta($key);
        $node = $this->registry->resolve($key);

        return Response::json([
            'key' => $key,
            'label' => $meta['label'],
            'type' => $meta['type']->value,
            'input_ports' => $node->inputPorts(),
            'output_ports' => $node->outputPorts(),
            'config_schema' => $node::configSchema(),
        ]);
    }
}
