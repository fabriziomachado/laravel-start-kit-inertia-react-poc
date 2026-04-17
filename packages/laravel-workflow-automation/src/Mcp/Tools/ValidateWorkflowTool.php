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
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('validate_workflow')]
#[Title('Validate Workflow')]
#[Description('Validate a workflow\'s graph structure. Returns validation errors if any. A valid workflow has exactly one trigger, all nodes connected, no cycles, and valid port connections.')]
#[IsReadOnly]
final class ValidateWorkflowTool extends Tool
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
        $errors = $this->service->validate($request->get('workflow_id'));

        return Response::json([
            'valid' => empty($errors),
            'errors' => $errors,
        ]);
    }
}
