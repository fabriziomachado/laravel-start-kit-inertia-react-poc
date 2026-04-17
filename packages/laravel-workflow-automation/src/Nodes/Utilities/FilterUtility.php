<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Nodes\Utilities;

use Aftandilmmd\WorkflowAutomation\Attributes\AsWorkflowNode;
use Aftandilmmd\WorkflowAutomation\Contracts\NodeInterface;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeInput;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeOutput;
use Aftandilmmd\WorkflowAutomation\Enums\NodeType;
use Aftandilmmd\WorkflowAutomation\Enums\Operator;

#[AsWorkflowNode(key: 'filter', type: NodeType::Utility, label: 'Filter')]
final class FilterUtility implements NodeInterface
{
    use \Aftandilmmd\WorkflowAutomation\Nodes\HasDocumentation;

    public static function configSchema(): array
    {
        return [
            ['key' => 'conditions', 'type' => 'array_of_objects', 'label' => 'Conditions', 'required' => true, 'schema' => [
                ['key' => 'field', 'type' => 'string', 'label' => 'Field'],
                ['key' => 'operator', 'type' => 'select', 'label' => 'Operator', 'options' => array_column(Operator::cases(), 'value')],
                ['key' => 'value', 'type' => 'string', 'label' => 'Value'],
            ]],
            ['key' => 'logic', 'type' => 'select', 'label' => 'Logic', 'options' => ['and', 'or'], 'required' => false],
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
        return ['main'];
    }

    public function execute(NodeInput $input, array $config): NodeOutput
    {
        $conditions = $config['conditions'] ?? [];
        $logic = $config['logic'] ?? 'and';

        $filtered = array_filter($input->items, function (array $item) use ($conditions, $logic) {
            $results = array_map(
                fn (array $cond) => Operator::from($cond['operator'])->evaluate(
                    data_get($item, $cond['field']),
                    $cond['value'] ?? null,
                ),
                $conditions,
            );

            return $logic === 'and'
                ? ! in_array(false, $results, true)
                : in_array(true, $results, true);
        });

        return NodeOutput::main(array_values($filtered));
    }
}
