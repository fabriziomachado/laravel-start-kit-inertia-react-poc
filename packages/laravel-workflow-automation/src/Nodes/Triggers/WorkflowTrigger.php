<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Nodes\Triggers;

use Aftandilmmd\WorkflowAutomation\Attributes\AsWorkflowNode;
use Aftandilmmd\WorkflowAutomation\Contracts\TriggerInterface;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeInput;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeOutput;
use Aftandilmmd\WorkflowAutomation\Enums\NodeType;
use Aftandilmmd\WorkflowAutomation\Nodes\HasDocumentation;

#[AsWorkflowNode(key: 'workflow', type: NodeType::Trigger, label: 'Workflow Trigger')]
final class WorkflowTrigger implements TriggerInterface
{
    use HasDocumentation;

    public static function configSchema(): array
    {
        return [
            ['key' => 'source_workflow_id', 'type' => 'workflow_select', 'label' => 'Source Workflow (leave empty for any)', 'required' => false],
            ['key' => 'trigger_on', 'type' => 'select', 'label' => 'Trigger When', 'required' => true, 'options' => [
                ['value' => 'completed', 'label' => 'Completed'],
                ['value' => 'failed', 'label' => 'Failed'],
                ['value' => 'any', 'label' => 'Completed or Failed'],
            ]],
        ];
    }

    public static function outputSchema(): array
    {
        return [
            'main' => [
                ['key' => 'source_workflow_id', 'type' => 'number', 'label' => 'Source Workflow ID'],
                ['key' => 'source_run_id', 'type' => 'number', 'label' => 'Source Run ID'],
                ['key' => 'source_status', 'type' => 'string', 'label' => 'Source Status'],
                ['key' => 'data', 'type' => 'object', 'label' => 'Source Output Data'],
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
        // Registration handled by WorkflowChainListener during boot
    }

    public function unregister(int $workflowId, int $nodeId, array $config): void {}

    public function extractPayload(mixed $event): array
    {
        return is_array($event) ? $event : [[]];
    }

    public function execute(NodeInput $input, array $config): NodeOutput
    {
        return NodeOutput::main($input->items);
    }
}
