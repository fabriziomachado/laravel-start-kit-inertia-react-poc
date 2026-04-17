<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Nodes\Controls;

use Aftandilmmd\WorkflowAutomation\Attributes\AsWorkflowNode;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeInput;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeOutput;
use Aftandilmmd\WorkflowAutomation\Enums\NodeType;
use Aftandilmmd\WorkflowAutomation\Enums\RunStatus;
use Aftandilmmd\WorkflowAutomation\Jobs\ResumeWorkflowJob;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowRun;
use Aftandilmmd\WorkflowAutomation\Nodes\BaseNode;

#[AsWorkflowNode(key: 'delay', type: NodeType::Control, label: 'Delay')]
final class DelayControl extends BaseNode
{
    public static function configSchema(): array
    {
        return [
            ['key' => 'delay_type', 'type' => 'select', 'label' => 'Delay Type', 'options' => ['seconds', 'minutes', 'hours'], 'required' => true],
            ['key' => 'delay_value', 'type' => 'integer', 'label' => 'Delay Value', 'required' => true],
        ];
    }

    public function outputPorts(): array
    {
        return ['main'];
    }

    public function execute(NodeInput $input, array $config): NodeOutput
    {
        $seconds = match ($config['delay_type'] ?? 'seconds') {
            'seconds' => (int) ($config['delay_value'] ?? 0),
            'minutes' => (int) ($config['delay_value'] ?? 0) * 60,
            'hours' => (int) ($config['delay_value'] ?? 0) * 3600,
            default => 0,
        };

        if ($seconds <= 0) {
            return NodeOutput::main($input->items);
        }

        // Pause the run and schedule a delayed resume job
        $run = WorkflowRun::find($input->context->workflowRunId);

        if ($run) {
            $run->update([
                'status' => RunStatus::Waiting,
                'context' => $input->context->getAllOutputs(),
            ]);

            // Find this node's ID from the run's current node runs
            $nodeRun = $run->nodeRuns()->where('status', 'running')->latest()->first();
            $nodeId = $nodeRun?->node_id ?? 0;

            ResumeWorkflowJob::dispatch(
                workflowRunId: $run->id,
                resumeFromNodeId: $nodeId,
                payload: $input->items,
                resumePort: 'main',
            )->delay($seconds);
        }

        // Return empty — the resume job will continue the flow
        return NodeOutput::main([]);
    }
}
