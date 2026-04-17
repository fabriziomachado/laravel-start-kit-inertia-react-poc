<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Nodes\Triggers;

use Aftandilmmd\WorkflowAutomation\Attributes\AsWorkflowNode;
use Aftandilmmd\WorkflowAutomation\Contracts\TriggerInterface;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeInput;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeOutput;
use Aftandilmmd\WorkflowAutomation\Enums\NodeType;

#[AsWorkflowNode(key: 'model_event', type: NodeType::Trigger, label: 'Model Event')]
final class ModelEventTrigger implements TriggerInterface
{
    use \Aftandilmmd\WorkflowAutomation\Nodes\HasDocumentation;

    public static function configSchema(): array
    {
        return [
            ['key' => 'model', 'type' => 'model_select', 'label' => 'Model', 'required' => true],
            ['key' => 'events', 'type' => 'multiselect', 'label' => 'Events', 'options_from' => 'model_events', 'required' => true],
            ['key' => 'only_fields', 'type' => 'array', 'label' => 'Only when these fields change (updated only, empty = all)', 'required' => false],
        ];
    }

    public static function outputSchema(): array
    {
        return [
            'main' => [
                ['key' => 'model', 'type' => 'object', 'label' => 'Model Data'],
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
        // Registration handled by ModelEventListener during boot
    }

    public function unregister(int $workflowId, int $nodeId, array $config): void {}

    public function extractPayload(mixed $event): array
    {
        if ($event instanceof \Illuminate\Database\Eloquent\Model) {
            return [$event->toArray()];
        }

        return is_array($event) ? [$event] : [[]];
    }

    public function execute(NodeInput $input, array $config): NodeOutput
    {
        return NodeOutput::main($input->items);
    }
}
