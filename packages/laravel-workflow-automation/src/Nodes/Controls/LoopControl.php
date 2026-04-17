<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Nodes\Controls;

use Aftandilmmd\WorkflowAutomation\Attributes\AsWorkflowNode;
use Aftandilmmd\WorkflowAutomation\Contracts\NodeInterface;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeInput;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeOutput;
use Aftandilmmd\WorkflowAutomation\Enums\NodeType;

#[AsWorkflowNode(key: 'loop', type: NodeType::Control, label: 'Loop')]
final class LoopControl implements NodeInterface
{
    use \Aftandilmmd\WorkflowAutomation\Nodes\HasDocumentation;

    public static function configSchema(): array
    {
        return [
            ['key' => 'source_field', 'type' => 'string', 'label' => 'Array field to iterate (e.g. items, orders)', 'required' => true, 'supports_expression' => true],
        ];
    }

    public static function outputSchema(): array
    {
        return [
            'loop_item' => [
                ['key' => '_loop_index', 'type' => 'integer', 'label' => 'Loop Index'],
                ['key' => '_loop_parent', 'type' => 'object', 'label' => 'Parent Item'],
                ['key' => '_loop_item', 'type' => 'object', 'label' => 'Current Loop Item'],
            ],
        ];
    }

    public function inputPorts(): array
    {
        return ['main'];
    }

    public function outputPorts(): array
    {
        return ['loop_item', 'loop_done'];
    }

    public function execute(NodeInput $input, array $config): NodeOutput
    {
        $loopItems = [];

        foreach ($input->items as $item) {
            $array = data_get($item, $config['source_field'], []);

            if (! is_array($array)) {
                $array = [$array];
            }

            foreach ($array as $index => $element) {
                $loopItems[] = [
                    '_loop_index' => $index,
                    '_loop_parent' => $item,
                    '_loop_item' => is_array($element) ? $element : ['value' => $element],
                ];
            }
        }

        return NodeOutput::ports([
            'loop_item' => $loopItems,
            'loop_done' => $input->items,
        ]);
    }
}
