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
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_tags')]
#[Title('List Tags')]
#[Description('List all workflow tags.')]
#[IsReadOnly]
final class ListTagsTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): Response
    {
        $tags = WorkflowTag::orderBy('name')
            ->get()
            ->map(fn (WorkflowTag $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'color' => $t->color,
            ])
            ->all();

        return Response::json(['tags' => $tags]);
    }
}
