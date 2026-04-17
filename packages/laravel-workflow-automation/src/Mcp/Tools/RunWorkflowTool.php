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

#[Name('run_workflow')]
#[Title('Run Workflow')]
#[Description('Execute a workflow with an optional payload. The workflow must be active. Returns the run result with status and node execution details.')]
final class RunWorkflowTool extends Tool
{
    public function __construct(
        protected WorkflowService $service,
    ) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'workflow_id' => $schema->integer()->required()->description('The workflow ID'),
            'payload' => $schema->array()->description('Array of data items to pass to the workflow trigger. Each item is an object.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $run = $this->service->run(
            $request->get('workflow_id'),
            $request->get('payload', []),
        );

        $run->load('nodeRuns.node');

        return Response::json([
            'id' => $run->id,
            'workflow_id' => $run->workflow_id,
            'status' => $run->status->value,
            'started_at' => $run->started_at,
            'finished_at' => $run->finished_at,
            'node_runs' => $run->nodeRuns->map(fn ($nr) => [
                'node_id' => $nr->node_id,
                'node_name' => $nr->node->name,
                'node_key' => $nr->node->node_key,
                'status' => $nr->status->value,
                'duration_ms' => $nr->duration_ms,
                'error_message' => $nr->error_message,
            ])->all(),
        ]);
    }
}
