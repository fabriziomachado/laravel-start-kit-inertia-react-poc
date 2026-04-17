<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Mcp\Tools;

use Aftandilmmd\WorkflowAutomation\Models\WorkflowRun;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_run')]
#[Title('Get Run')]
#[Description('Get detailed information about a workflow run including per-node execution results.')]
#[IsReadOnly]
final class GetRunTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'run_id' => $schema->integer()->required()->description('The workflow run ID'),
        ];
    }

    public function handle(Request $request): Response
    {
        $run = WorkflowRun::with('nodeRuns.node')
            ->findOrFail($request->get('run_id'));

        return Response::json([
            'id' => $run->id,
            'workflow_id' => $run->workflow_id,
            'status' => $run->status->value,
            'initial_payload' => $run->initial_payload,
            'started_at' => $run->started_at,
            'finished_at' => $run->finished_at,
            'node_runs' => $run->nodeRuns->map(fn ($nr) => [
                'node_id' => $nr->node_id,
                'node_name' => $nr->node->name,
                'node_key' => $nr->node->node_key,
                'status' => $nr->status->value,
                'duration_ms' => $nr->duration_ms,
                'input' => $nr->input,
                'output' => $nr->output,
                'error_message' => $nr->error_message,
            ])->all(),
        ]);
    }
}
