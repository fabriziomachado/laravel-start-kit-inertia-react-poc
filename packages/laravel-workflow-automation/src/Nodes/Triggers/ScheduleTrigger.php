<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Nodes\Triggers;

use Aftandilmmd\WorkflowAutomation\Attributes\AsWorkflowNode;
use Aftandilmmd\WorkflowAutomation\Contracts\TriggerInterface;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeInput;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeOutput;
use Aftandilmmd\WorkflowAutomation\Enums\NodeType;

#[AsWorkflowNode(key: 'schedule', type: NodeType::Trigger, label: 'Schedule')]
final class ScheduleTrigger implements TriggerInterface
{
    use \Aftandilmmd\WorkflowAutomation\Nodes\HasDocumentation;

    public static function configSchema(): array
    {
        return [
            ['key' => 'cron', 'type' => 'string', 'label' => 'Cron Expression (e.g. 0 9 * * 1)', 'required' => false],
            ['key' => 'interval_type', 'type' => 'select', 'label' => 'Interval Type', 'options' => ['minutes', 'hours', 'days', 'custom_cron'], 'required' => true],
            ['key' => 'interval_value', 'type' => 'integer', 'label' => 'Interval Value', 'required' => false],
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

    public function register(int $workflowId, int $nodeId, array $config): void
    {
        // Handled by ScheduleRunCommand
    }

    public function unregister(int $workflowId, int $nodeId, array $config): void {}

    public function extractPayload(mixed $event): array
    {
        return [['triggered_at' => now()->toISOString()]];
    }

    public function execute(NodeInput $input, array $config): NodeOutput
    {
        return NodeOutput::main($input->items);
    }
}
