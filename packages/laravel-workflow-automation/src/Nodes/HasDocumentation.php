<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Nodes;

use Aftandilmmd\WorkflowAutomation\Attributes\AsWorkflowNode;
use Aftandilmmd\WorkflowAutomation\Enums\NodeType;
use ReflectionClass;

trait HasDocumentation
{
    public static function documentation(): ?string
    {
        $ref = new ReflectionClass(static::class);
        $attrs = $ref->getAttributes(AsWorkflowNode::class);

        if (! $attrs) {
            return null;
        }

        $attr = $attrs[0]->newInstance();
        $filename = str_replace('_', '-', $attr->key).'.md';
        $folder = $attr->type === NodeType::Trigger ? 'triggers' : 'nodes';
        $path = dirname(__DIR__, 2)."/docs/{$folder}/{$filename}";

        return file_exists($path) ? file_get_contents($path) : null;
    }
}
