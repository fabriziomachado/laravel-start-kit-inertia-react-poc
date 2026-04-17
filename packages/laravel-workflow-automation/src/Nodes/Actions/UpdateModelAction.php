<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Nodes\Actions;

use Aftandilmmd\WorkflowAutomation\Attributes\AsWorkflowNode;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeInput;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeOutput;
use Aftandilmmd\WorkflowAutomation\Enums\NodeType;
use Aftandilmmd\WorkflowAutomation\Nodes\BaseNode;
use Throwable;

#[AsWorkflowNode(key: 'update_model', type: NodeType::Action, label: 'Update Model')]
final class UpdateModelAction extends BaseNode
{
    public static function configSchema(): array
    {
        return [
            ['key' => 'model', 'type' => 'model_select', 'label' => 'Model', 'required' => true],
            ['key' => 'find_by', 'type' => 'string', 'label' => 'Find by field (e.g. id)', 'required' => true],
            ['key' => 'find_value', 'type' => 'string', 'label' => 'Find value', 'required' => true, 'supports_expression' => true],
            ['key' => 'fields', 'type' => 'keyvalue', 'label' => 'Fields to update', 'required' => true, 'supports_expression' => true],
        ];
    }

    public static function outputSchema(): array
    {
        return [
            'main' => [
                ['key' => 'updated_model', 'type' => 'object', 'label' => 'Updated Model'],
            ],
        ];
    }

    public function execute(NodeInput $input, array $config): NodeOutput
    {
        $results = [];

        foreach ($input->items as $item) {
            try {
                $model = app($config['model'])
                    ->where($config['find_by'], $config['find_value'])
                    ->firstOrFail();

                $model->update($config['fields']);

                $results[] = array_merge($item, ['updated_model' => $model->fresh()->toArray()]);
            } catch (Throwable $e) {
                return NodeOutput::ports([
                    'main' => $results,
                    'error' => [array_merge($item, ['error' => $e->getMessage()])],
                ]);
            }
        }

        return NodeOutput::main($results);
    }
}
