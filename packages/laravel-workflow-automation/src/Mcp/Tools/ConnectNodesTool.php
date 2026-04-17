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

#[Name('connect_nodes')]
#[Title('Connect Nodes')]
#[Description('Create an edge between two nodes. Connects source node\'s output port to target node\'s input port. Default ports are "main". For IF conditions use source_port "true" or "false". For Switch use "case_0", "case_1", "default".')]
final class ConnectNodesTool extends Tool
{
    public function __construct(
        protected WorkflowService $service,
    ) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'source_node_id' => $schema->integer()->required()->description('The source node ID'),
            'target_node_id' => $schema->integer()->required()->description('The target node ID'),
            'source_port' => $schema->string()->required()->description('Source output port; use "main" unless branching (true/false/case_N).'),
            'target_port' => $schema->string()->required()->description('Target input port; use "main" unless merging multiple inputs.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $edge = $this->service->connect(
            $request->get('source_node_id'),
            $request->get('target_node_id'),
            $request->get('source_port', 'main'),
            $request->get('target_port', 'main'),
        );

        return Response::json([
            'id' => $edge->id,
            'workflow_id' => $edge->workflow_id,
            'source_node_id' => $edge->source_node_id,
            'target_node_id' => $edge->target_node_id,
            'source_port' => $edge->source_port,
            'target_port' => $edge->target_port,
        ]);
    }
}
