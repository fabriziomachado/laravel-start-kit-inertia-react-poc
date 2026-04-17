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

#[Name('delete_workflow')]
#[Title('Delete Workflow')]
#[Description('Permanently delete a workflow and all its nodes, edges, and run history.')]
#[IsDestructive]
final class DeleteWorkflowTool extends Tool
{
    public function __construct(
        protected WorkflowService $service,
    ) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'workflow_id' => $schema->integer()->required()->description('The workflow ID'),
        ];
    }

    public function handle(Request $request): Response
    {
        $this->service->delete($request->get('workflow_id'));

        return Response::text('Workflow deleted successfully.');
    }
}
