<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Mcp\Tools;

use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_workflows')]
#[Title('List Workflows')]
#[Description('List all workflows with their status. Returns id, name, active status, and node/edge counts.')]
#[IsReadOnly]
final class ListWorkflowsTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'page' => $schema->integer()->description('Page number')->default(1),
            'per_page' => $schema->integer()->description('Items per page')->default(15),
            'search' => $schema->string()->description('Search workflows by name'),
            'folder_id' => $schema->integer()->description('Filter by folder ID'),
            'tag_id' => $schema->integer()->description('Filter by tag ID'),
            'tag' => $schema->string()->description('Filter by tag name'),
        ];
    }

    public function handle(Request $request): Response
    {
        $page = $request->get('page') ?? 1;
        $perPage = $request->get('per_page') ?? 15;

        $query = Workflow::withCount(['nodes', 'edges'])->with('tags');

        if ($search = $request->get('search')) {
            $query->where('name', 'like', "%{$search}%");
        }
        if ($folderId = $request->get('folder_id')) {
            $query->where('folder_id', $folderId);
        }
        if ($tagId = $request->get('tag_id')) {
            $query->whereHas('tags', fn ($q) => $q->where($q->getModel()->getTable().'.id', $tagId));
        }
        if ($tagName = $request->get('tag')) {
            $query->whereHas('tags', fn ($q) => $q->where('name', $tagName));
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $items = collect($paginator->items())->map(fn (Workflow $w) => [
            'id' => $w->id,
            'name' => $w->name,
            'description' => $w->description,
            'is_active' => $w->is_active,
            'folder_id' => $w->folder_id,
            'tags' => $w->tags->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'color' => $t->color])->all(),
            'nodes_count' => $w->nodes_count,
            'edges_count' => $w->edges_count,
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
