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

#[Name('update_workflow')]
#[Title('Update Workflow')]
#[Description('Update a workflow\'s name, description, folder, or tags.')]
final class UpdateWorkflowTool extends Tool
{
    public function __construct(
        protected WorkflowService $service,
    ) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'workflow_id' => $schema->integer()->required()->description('The workflow ID'),
            'name' => $schema->string()->description('New workflow name'),
            'description' => $schema->string()->description('New workflow description'),
            'folder_id' => $schema->integer()->description('Move workflow to this folder ID (null to remove)'),
            'tag_ids' => $schema->array()->description('Replace workflow tags with these tag IDs'),
        ];
    }

    public function handle(Request $request): Response
    {
        $data = array_filter([
            'name' => $request->get('name'),
            'description' => $request->get('description'),
            'folder_id' => $request->get('folder_id'),
            'tag_ids' => $request->get('tag_ids'),
        ], fn ($value) => ! is_null($value));

        $workflow = $this->service->update($request->get('workflow_id'), $data);

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
