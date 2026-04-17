<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Nodes\Transformers;

use Aftandilmmd\WorkflowAutomation\Attributes\AsWorkflowNode;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeInput;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeOutput;
use Aftandilmmd\WorkflowAutomation\Enums\NodeType;
use Aftandilmmd\WorkflowAutomation\Nodes\BaseNode;

#[AsWorkflowNode(key: 'set_fields', type: NodeType::Transformer, label: 'Set Fields')]
final class SetFieldsTransformer extends BaseNode
{
    public static function configSchema(): array
    {
        return [
            ['key' => 'fields', 'type' => 'keyvalue', 'label' => 'Fields to set', 'required' => true, 'supports_expression' => true],
            ['key' => 'keep_existing', 'type' => 'boolean', 'label' => 'Keep existing fields', 'required' => false],
        ];
    }

    public static function outputSchema(): array
    {
        return [
            'main' => [['key' => '*', 'type' => 'dynamic', 'label' => 'Fields defined in config']],
        ];
    }

    public function execute(NodeInput $input, array $config): NodeOutput
    {
        $results = [];

        foreach ($input->items as $item) {
            $base = ($config['keep_existing'] ?? true) ? $item : [];
            $results[] = array_merge($base, $config['fields']);
        }

        return NodeOutput::main($results);
    }
}
