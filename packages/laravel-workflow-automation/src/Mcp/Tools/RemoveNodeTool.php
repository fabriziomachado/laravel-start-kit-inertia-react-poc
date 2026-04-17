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

#[Name('remove_node')]
#[Title('Remove Node')]
#[Description('Remove a node and all its connected edges from the workflow.')]
#[IsDestructive]
final class RemoveNodeTool extends Tool
{
    public function __construct(
        protected WorkflowService $service,
    ) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'node_id' => $schema->integer()->required()->description('The node ID to remove'),
        ];
    }

    public function handle(Request $request): Response
    {
        $this->service->removeNode($request->get('node_id'));

        return Response::text('Node and its connected edges have been removed.');
    }
}
