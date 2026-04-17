<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Nodes\Utilities;

use Aftandilmmd\WorkflowAutomation\Attributes\AsWorkflowNode;
use Aftandilmmd\WorkflowAutomation\Contracts\ExpressionEvaluatorInterface;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeInput;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeOutput;
use Aftandilmmd\WorkflowAutomation\Enums\NodeType;
use Aftandilmmd\WorkflowAutomation\Nodes\BaseNode;
use Throwable;

#[AsWorkflowNode(key: 'code', type: NodeType::Code, label: 'Code / Expression')]
final class CodeUtility extends BaseNode
{
    public function __construct(
        private readonly ExpressionEvaluatorInterface $evaluator,
    ) {}

    public static function configSchema(): array
    {
        return [
            ['key' => 'mode', 'type' => 'select', 'label' => 'Mode', 'options' => ['transform', 'filter'], 'required' => true],
            ['key' => 'expression', 'type' => 'textarea', 'label' => 'Expression', 'required' => true, 'supports_expression' => true],
        ];
    }

    public function execute(NodeInput $input, array $config): NodeOutput
    {
        $mode = $config['mode'] ?? 'transform';
        $expression = $config['expression'] ?? '';

        $results = [];

        foreach ($input->items as $item) {
            try {
                $variables = $input->context->toVariables([], $item);
                $result = $this->evaluator->resolve($expression, $variables);

                if ($mode === 'filter') {
                    if ($result) {
                        $results[] = $item;
                    }
                } else {
                    // Transform mode: result should be an array (new item), or we merge it
                    if (is_array($result)) {
                        $results[] = $result;
                    } else {
                        $results[] = array_merge($item, ['_result' => $result]);
                    }
                }
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
