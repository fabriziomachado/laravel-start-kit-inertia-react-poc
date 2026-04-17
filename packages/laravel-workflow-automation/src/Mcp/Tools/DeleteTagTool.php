<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Mcp\Tools;

use Aftandilmmd\WorkflowAutomation\Models\WorkflowTag;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Name('delete_tag')]
#[Title('Delete Tag')]
#[Description('Delete a workflow tag. This removes the tag from all workflows.')]
final class DeleteTagTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'tag_id' => $schema->integer()->required()->description('The tag ID to delete'),
        ];
    }

    public function handle(Request $request): Response
    {
        $tag = WorkflowTag::findOrFail($request->get('tag_id'));
        $tag->delete();

        return Response::json(['message' => 'Tag deleted.']);
    }
}
