<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Nodes\Utilities;

use Aftandilmmd\WorkflowAutomation\Attributes\AsWorkflowNode;
use Aftandilmmd\WorkflowAutomation\Contracts\NodeInterface;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeInput;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeOutput;
use Aftandilmmd\WorkflowAutomation\Enums\AggregateFunction;
use Aftandilmmd\WorkflowAutomation\Enums\NodeType;

#[AsWorkflowNode(key: 'aggregate', type: NodeType::Utility, label: 'Aggregate')]
final class AggregateUtility implements NodeInterface
{
    use \Aftandilmmd\WorkflowAutomation\Nodes\HasDocumentation;

    public static function configSchema(): array
    {
        return [
            ['key' => 'group_by', 'type' => 'string', 'label' => 'Group by field (empty = aggregate all)', 'required' => false],
            ['key' => 'operations', 'type' => 'array_of_objects', 'label' => 'Operations', 'required' => true, 'schema' => [
                ['key' => 'field', 'type' => 'string', 'label' => 'Field'],
                ['key' => 'function', 'type' => 'select', 'label' => 'Function', 'options' => array_column(AggregateFunction::cases(), 'value')],
                ['key' => 'alias', 'type' => 'string', 'label' => 'Output alias'],
            ]],
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
        $groupBy = $config['group_by'] ?? null;
        $operations = $config['operations'] ?? [];

        // Group items
        $groups = [];
        foreach ($input->items as $item) {
            $key = $groupBy ? (string) data_get($item, $groupBy, '_ungrouped') : '_all';
            $groups[$key][] = $item;
        }

        // Aggregate each group
        $results = [];
        foreach ($groups as $key => $items) {
            $row = $groupBy ? [$groupBy => $key] : [];

            foreach ($operations as $op) {
                $values = array_map(fn (array $item) => data_get($item, $op['field']), $items);
                $alias = $op['alias'] ?? $op['field'].'_'.$op['function'];
                $row[$alias] = AggregateFunction::from($op['function'])->compute($values);
            }

            $results[] = $row;
        }

        return NodeOutput::main($results);
    }
}
