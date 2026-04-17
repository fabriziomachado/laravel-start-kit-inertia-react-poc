<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Nodes\Controls;

use Aftandilmmd\WorkflowAutomation\Attributes\AsWorkflowNode;
use Aftandilmmd\WorkflowAutomation\Contracts\NodeInterface;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeInput;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeOutput;
use Aftandilmmd\WorkflowAutomation\Enums\NodeType;

#[AsWorkflowNode(key: 'merge', type: NodeType::Control, label: 'Merge')]
final class MergeControl implements NodeInterface
{
    use \Aftandilmmd\WorkflowAutomation\Nodes\HasDocumentation;

    public static function configSchema(): array
    {
        return [
            ['key' => 'mode', 'type' => 'select', 'label' => 'Merge Mode', 'options' => ['append', 'zip', 'wait_all'], 'required' => false],
        ];
    }

    public static function outputSchema(): array
    {
        return [];
    }

    public function inputPorts(): array
    {
        return ['main_1', 'main_2', 'main_3', 'main_4'];
    }

    public function outputPorts(): array
    {
        return ['main'];
    }

    public function execute(NodeInput $input, array $config): NodeOutput
    {
        // Items from all input ports are already merged by GraphExecutor
        return NodeOutput::main($input->items);
    }
}
