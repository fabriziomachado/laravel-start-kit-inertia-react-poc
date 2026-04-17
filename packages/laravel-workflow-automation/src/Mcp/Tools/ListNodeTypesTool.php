<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Mcp\Tools;

use Aftandilmmd\WorkflowAutomation\Registry\NodeRegistry;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_node_types')]
#[Title('List Node Types')]
#[Description('List all available node types with their category, input/output ports, and config schema. Call this first to understand what nodes you can add to a workflow.')]
#[IsReadOnly]
final class ListNodeTypesTool extends Tool
{
    public function __construct(
        protected NodeRegistry $registry,
    ) {}

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): Response
    {
        return Response::json($this->registry->all());
    }
}
