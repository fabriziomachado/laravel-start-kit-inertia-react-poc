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

#[Name('create_workflow')]
#[Title('Create Workflow')]
#[Description('Create a new workflow. After creating, add nodes with add_node and connect them with connect_nodes.')]
final class CreateWorkflowTool extends Tool
{
    public function __construct(
        protected WorkflowService $service,
    ) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->required()->description('Workflow name'),
            'description' => $schema->string()->description('Workflow description'),
            'folder_id' => $schema->integer()->description('Folder ID to place the workflow in'),
            'tag_ids' => $schema->array()->description('Array of tag IDs to assign'),
        ];
    }

    public function handle(Request $request): Response
    {
        $data = array_filter([
            'name' => $request->get('name'),
            'description' => $request->get('description'),
            'folder_id' => $request->get('folder_id'),
            'tag_ids' => $request->get('tag_ids'),
        ], fn ($v) => ! is_null($v));

        $workflow = $this->service->create($data);

        return Response::json([
            'workflow' => [
                'id' => $workflow->id,
                'name' => $workflow->name,
                'description' => $workflow->description,
                'is_active' => $workflow->is_active,
                'folder_id' => $workflow->folder_id,
                'tags' => ($workflow->tags ?? collect())->map(fn ($t) => ['id' => $t->id, 'name' => $t->name])->all(),
            ],
        ]);
    }
}
