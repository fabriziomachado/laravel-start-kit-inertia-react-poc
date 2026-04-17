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

#[Name('create_tag')]
#[Title('Create Tag')]
#[Description('Create a new workflow tag for organizing workflows.')]
final class CreateTagTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->required()->description('Tag name (must be unique)'),
            'color' => $schema->string()->description('Hex color code (e.g. #FF0000)'),
        ];
    }

    public function handle(Request $request): Response
    {
        $tag = WorkflowTag::create(array_filter([
            'name' => $request->get('name'),
            'color' => $request->get('color'),
        ], fn ($v) => ! is_null($v)));

        return Response::json([
            'tag' => ['id' => $tag->id, 'name' => $tag->name, 'color' => $tag->color],
        ]);
    }
}
