<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Nodes\Controls;

use Aftandilmmd\WorkflowAutomation\Attributes\AsWorkflowNode;
use Aftandilmmd\WorkflowAutomation\Contracts\NodeInterface;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeInput;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeOutput;
use Aftandilmmd\WorkflowAutomation\Enums\NodeType;
use Aftandilmmd\WorkflowAutomation\Enums\RunStatus;
use Aftandilmmd\WorkflowAutomation\Jobs\ResumeWorkflowJob;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowRun;
use Illuminate\Support\Str;

#[AsWorkflowNode(key: 'wait_resume', type: NodeType::Control, label: 'Wait / Resume')]
final class WaitResumeControl implements NodeInterface
{
    use \Aftandilmmd\WorkflowAutomation\Nodes\HasDocumentation;

    public static function configSchema(): array
    {
        return [
            ['key' => 'timeout_seconds', 'type' => 'integer', 'label' => 'Timeout (seconds, 0 = no timeout)', 'required' => false],
        ];
    }

    public static function outputSchema(): array
    {
        return [];
    }

    public function inputPorts(): array
    {
        return ['main'];
    }

    public function outputPorts(): array
    {
        return ['resume', 'timeout'];
    }

    public function execute(NodeInput $input, array $config): NodeOutput
    {
        $resumeToken = Str::uuid()->toString();

        $run = WorkflowRun::find($input->context->workflowRunId);

        if (! $run) {
            return NodeOutput::port('resume', $input->items);
        }

        // Pause the workflow
        $run->update([
            'status' => RunStatus::Waiting,
            'context' => $input->context->getAllOutputs(),
        ]);

        // Find current node ID
        $nodeRun = $run->nodeRuns()->where('status', 'running')->latest()->first();
        $nodeId = $nodeRun?->node_id ?? 0;

        // Schedule timeout if configured
        $timeout = (int) ($config['timeout_seconds'] ?? 0);

        if ($timeout > 0) {
            ResumeWorkflowJob::dispatch(
                workflowRunId: $run->id,
                resumeFromNodeId: $nodeId,
                payload: ['timed_out' => true],
                resumePort: 'timeout',
            )->delay($timeout);
        }

        // Return resume token in output (stored in node_run.output for later lookup)
        return NodeOutput::ports([
            'resume' => [['resume_token' => $resumeToken, 'waiting_items' => $input->items]],
        ]);
    }
}
