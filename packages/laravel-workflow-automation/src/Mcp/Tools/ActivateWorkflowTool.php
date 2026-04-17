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

#[Name('activate_workflow')]
#[Title('Activate Workflow')]
#[Description('Activate a workflow so it responds to its trigger. Validate the workflow first with validate_workflow.')]
final class ActivateWorkflowTool extends Tool
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
        $workflow = $this->service->activate($request->get('workflow_id'));

        return Response::json([
            'workflow' => [
                'id' => $workflow->id,
                'name' => $workflow->name,
                'description' => $workflow->description,
                'is_active' => $workflow->is_active,
            ],
        ]);
    }
}
