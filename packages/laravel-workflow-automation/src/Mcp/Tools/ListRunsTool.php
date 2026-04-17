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

#[Name('list_runs')]
#[Title('List Runs')]
#[Description('List execution runs for a workflow, ordered by most recent first.')]
#[IsReadOnly]
final class ListRunsTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'workflow_id' => $schema->integer()->required()->description('The workflow ID'),
            'page' => $schema->integer()->description('Page number')->default(1),
            'per_page' => $schema->integer()->description('Items per page')->default(15),
        ];
    }

    public function handle(Request $request): Response
    {
        $page = $request->get('page') ?? 1;
        $perPage = $request->get('per_page') ?? 15;

        $paginator = WorkflowRun::where('workflow_id', $request->get('workflow_id'))
            ->latest()
            ->paginate($perPage, ['*'], 'page', $page);

        $items = collect($paginator->items())->map(fn (WorkflowRun $run) => [
            'id' => $run->id,
            'status' => $run->status->value,
            'started_at' => $run->started_at,
            'finished_at' => $run->finished_at,
        ])->all();

        return Response::json([
            'items' => $items,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
