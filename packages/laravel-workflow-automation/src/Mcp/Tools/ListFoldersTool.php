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
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_folders')]
#[Title('List Folders')]
#[Description('List all workflow folders as a tree structure.')]
#[IsReadOnly]
final class ListFoldersTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): Response
    {
        $folders = WorkflowFolder::whereNull('parent_id')
            ->with('children')
            ->orderBy('name')
            ->get()
            ->map(fn (WorkflowFolder $f) => $this->mapFolder($f))
            ->all();

        return Response::json(['folders' => $folders]);
    }

    private function mapFolder(WorkflowFolder $folder): array
    {
        $data = [
            'id' => $folder->id,
            'name' => $folder->name,
            'parent_id' => $folder->parent_id,
        ];

        if ($folder->relationLoaded('children') && $folder->children->isNotEmpty()) {
            $data['children'] = $folder->children->map(fn ($c) => $this->mapFolder($c))->all();
        }

        return $data;
    }
}
