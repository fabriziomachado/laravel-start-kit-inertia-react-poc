<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Nodes\Conditions;

use Aftandilmmd\WorkflowAutomation\Attributes\AsWorkflowNode;
use Aftandilmmd\WorkflowAutomation\Contracts\NodeInterface;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeInput;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeOutput;
use Aftandilmmd\WorkflowAutomation\Enums\NodeType;
use Aftandilmmd\WorkflowAutomation\Enums\Operator;

#[AsWorkflowNode(key: 'switch', type: NodeType::Condition, label: 'Switch')]
final class SwitchCondition implements NodeInterface
{
    use \Aftandilmmd\WorkflowAutomation\Nodes\HasDocumentation;

    public static function configSchema(): array
    {
        return [
            ['key' => 'field', 'type' => 'string', 'label' => 'Field to check', 'required' => true, 'supports_expression' => true],
            ['key' => 'cases', 'type' => 'array_of_objects', 'label' => 'Cases', 'required' => true, 'schema' => [
                ['key' => 'port', 'type' => 'string', 'label' => 'Port Name (e.g. case_premium)'],
                ['key' => 'operator', 'type' => 'select', 'label' => 'Operator', 'options' => array_column(Operator::cases(), 'value')],
                ['key' => 'value', 'type' => 'string', 'label' => 'Value'],
            ]],
            ['key' => 'fallthrough', 'type' => 'boolean', 'label' => 'Route unmatched to "default" port', 'required' => false],
        ];
    }

    public static function outputSchema(): array
    {
        return [];
    }

    public function inputPorts(): array
    {
        return ['main'];
    }

    public function outputPorts(): array
    {
        return ['default']; // Dynamic case_* ports are created from config
    }

    public function execute(NodeInput $input, array $config): NodeOutput
    {
        $portItems = [];
        $cases = $config['cases'] ?? [];

        foreach ($input->items as $item) {
            $fieldValue = data_get($item, $config['field']);
            $matched = false;

            foreach ($cases as $case) {
                if (Operator::from($case['operator'])->evaluate($fieldValue, $case['value'])) {
                    $portItems[$case['port']][] = $item;
                    $matched = true;

                    break;
                }
            }

            if (! $matched && ($config['fallthrough'] ?? true)) {
                $portItems['default'][] = $item;
            }
        }

        return NodeOutput::ports($portItems);
    }
}
