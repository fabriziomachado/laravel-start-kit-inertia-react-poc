<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Nodes\Conditions;

use Aftandilmmd\WorkflowAutomation\Attributes\AsWorkflowNode;
use Aftandilmmd\WorkflowAutomation\Contracts\NodeInterface;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeInput;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeOutput;
use Aftandilmmd\WorkflowAutomation\Enums\NodeType;
use Aftandilmmd\WorkflowAutomation\Enums\Operator;

#[AsWorkflowNode(key: 'if_condition', type: NodeType::Condition, label: 'IF Condition')]
final class IfCondition implements NodeInterface
{
    use \Aftandilmmd\WorkflowAutomation\Nodes\HasDocumentation;

    public static function configSchema(): array
    {
        return [
            ['key' => 'field', 'type' => 'string', 'label' => 'Field', 'required' => true, 'supports_expression' => true],
            ['key' => 'operator', 'type' => 'select', 'label' => 'Operator', 'required' => true, 'options' => array_column(Operator::cases(), 'value')],
            ['key' => 'value', 'type' => 'string', 'label' => 'Value', 'required' => false, 'supports_expression' => true],
        ];
    }

    public static function outputSchema(): array
    {
        return [
            'true' => [['key' => '*', 'type' => 'passthrough', 'label' => 'Items matching condition']],
            'false' => [['key' => '*', 'type' => 'passthrough', 'label' => 'Items not matching condition']],
        ];
    }

    public function inputPorts(): array
    {
        return ['main'];
    }

    public function outputPorts(): array
    {
        return ['true', 'false'];
    }

    public function execute(NodeInput $input, array $config): NodeOutput
    {
        $trueItems = [];
        $falseItems = [];
        $operator = Operator::from($config['operator']);

        foreach ($input->items as $item) {
            $fieldValue = data_get($item, $config['field']);

            if ($operator->evaluate($fieldValue, $config['value'] ?? null)) {
                $trueItems[] = $item;
            } else {
                $falseItems[] = $item;
            }
        }

        return NodeOutput::ports(['true' => $trueItems, 'false' => $falseItems]);
    }
}
