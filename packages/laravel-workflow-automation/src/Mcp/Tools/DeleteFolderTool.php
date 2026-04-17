<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Mcp\Tools;

use Aftandilmmd\WorkflowAutomation\Models\WorkflowFolder;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Name('delete_folder')]
#[Title('Delete Folder')]
#[Description('Delete a workflow folder. Child folders and workflows in this folder will also be affected.')]
final class DeleteFolderTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'folder_id' => $schema->integer()->required()->description('The folder ID to delete'),
        ];
    }

    public function handle(Request $request): Response
    {
        $folder = WorkflowFolder::findOrFail($request->get('folder_id'));
        $folder->delete();

        return Response::json(['message' => 'Folder deleted.']);
    }
}
