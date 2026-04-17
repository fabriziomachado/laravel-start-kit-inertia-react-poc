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
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('remove_edge')]
#[Title('Remove Edge')]
#[Description('Remove an edge (connection) between two nodes.')]
#[IsDestructive]
final class RemoveEdgeTool extends Tool
{
    public function __construct(
        protected WorkflowService $service,
    ) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'edge_id' => $schema->integer()->required()->description('The edge ID to remove'),
        ];
    }

    public function handle(Request $request): Response
    {
        $this->service->removeEdge($request->get('edge_id'));

        return Response::text('Edge has been removed.');
    }
}
