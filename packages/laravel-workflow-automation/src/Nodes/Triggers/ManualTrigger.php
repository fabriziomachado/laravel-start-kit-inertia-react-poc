<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Nodes\Triggers;

use Aftandilmmd\WorkflowAutomation\Attributes\AsWorkflowNode;
use Aftandilmmd\WorkflowAutomation\Contracts\TriggerInterface;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeInput;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeOutput;
use Aftandilmmd\WorkflowAutomation\Enums\NodeType;

#[AsWorkflowNode(key: 'manual', type: NodeType::Trigger, label: 'Manual Trigger')]
final class ManualTrigger implements TriggerInterface
{
    use \Aftandilmmd\WorkflowAutomation\Nodes\HasDocumentation;

    public static function configSchema(): array
    {
        return [
            ['key' => 'input_schema', 'type' => 'json', 'label' => 'Expected Input Schema', 'required' => false],
        ];
    }

    public static function outputSchema(): array
    {
        return [];
    }

    public function inputPorts(): array
    {
        return [];
    }

    public function outputPorts(): array
    {
        return ['main'];
    }

    public function register(int $workflowId, int $nodeId, array $config): void {}

    public function unregister(int $workflowId, int $nodeId, array $config): void {}

    public function extractPayload(mixed $event): array
    {
        return is_array($event) ? (array_is_list($event) ? $event : [$event]) : [[]];
    }

    public function execute(NodeInput $input, array $config): NodeOutput
    {
        return NodeOutput::main($input->items);
    }
}
