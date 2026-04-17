<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Nodes\Annotations;

use Aftandilmmd\WorkflowAutomation\Attributes\AsWorkflowNode;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeInput;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeOutput;
use Aftandilmmd\WorkflowAutomation\Enums\NodeType;
use Aftandilmmd\WorkflowAutomation\Nodes\BaseNode;

#[AsWorkflowNode(key: 'sticky_note', type: NodeType::Annotation, label: 'Sticky Note')]
final class StickyNote extends BaseNode
{
    public static function configSchema(): array
    {
        return [
            ['key' => 'content', 'type' => 'textarea', 'label' => 'Content'],
            ['key' => 'color', 'type' => 'select', 'label' => 'Color', 'options' => ['yellow', 'blue', 'green', 'pink', 'purple'], 'default' => 'yellow'],
        ];
    }

    public function inputPorts(): array
    {
        return [];
    }

    public function outputPorts(): array
    {
        return [];
    }

    public function execute(NodeInput $input, array $config): NodeOutput
    {
        return NodeOutput::main($input->items);
    }
}
