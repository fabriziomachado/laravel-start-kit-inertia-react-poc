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

#[Name('create_folder')]
#[Title('Create Folder')]
#[Description('Create a new workflow folder for organizing workflows hierarchically.')]
final class CreateFolderTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->required()->description('Folder name'),
            'parent_id' => $schema->integer()->description('Parent folder ID for nesting'),
        ];
    }

    public function handle(Request $request): Response
    {
        $folder = WorkflowFolder::create(array_filter([
            'name' => $request->get('name'),
            'parent_id' => $request->get('parent_id'),
        ], fn ($v) => ! is_null($v)));

        return Response::json([
            'folder' => ['id' => $folder->id, 'name' => $folder->name, 'parent_id' => $folder->parent_id],
        ]);
    }
}
