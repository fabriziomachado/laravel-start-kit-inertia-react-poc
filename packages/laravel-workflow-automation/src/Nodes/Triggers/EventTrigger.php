<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Nodes\Triggers;

use Aftandilmmd\WorkflowAutomation\Attributes\AsWorkflowNode;
use Aftandilmmd\WorkflowAutomation\Contracts\TriggerInterface;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeInput;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeOutput;
use Aftandilmmd\WorkflowAutomation\Enums\NodeType;

#[AsWorkflowNode(key: 'event', type: NodeType::Trigger, label: 'Event')]
final class EventTrigger implements TriggerInterface
{
    use \Aftandilmmd\WorkflowAutomation\Nodes\HasDocumentation;

    public static function configSchema(): array
    {
        return [
            ['key' => 'event_class', 'type' => 'string', 'label' => 'Event Class (e.g. App\\Events\\OrderPlaced)', 'required' => true],
        ];
    }

    public static function outputSchema(): array
    {
        return [
            'main' => [
                ['key' => 'event', 'type' => 'object', 'label' => 'Event Data'],
            ],
        ];
    }

    public function inputPorts(): array
    {
        return [];
    }

    public function outputPorts(): array
    {
        return ['main'];
    }

    public function register(int $workflowId, int $nodeId, array $config): void
    {
        // Registration handled by EventListener during boot
    }

    public function unregister(int $workflowId, int $nodeId, array $config): void {}

    public function extractPayload(mixed $event): array
    {
        if (is_object($event)) {
            if (method_exists($event, 'toArray')) {
                return [$event->toArray()];
            }

            return [get_object_vars($event)];
        }

        return is_array($event) ? [$event] : [[]];
    }

    public function execute(NodeInput $input, array $config): NodeOutput
    {
        return NodeOutput::main($input->items);
    }
}
