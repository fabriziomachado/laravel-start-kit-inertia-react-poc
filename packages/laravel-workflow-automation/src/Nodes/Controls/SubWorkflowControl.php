<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Nodes\Controls;

use Aftandilmmd\WorkflowAutomation\Attributes\AsWorkflowNode;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeInput;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeOutput;
use Aftandilmmd\WorkflowAutomation\Engine\GraphExecutor;
use Aftandilmmd\WorkflowAutomation\Enums\NodeType;
use Aftandilmmd\WorkflowAutomation\Enums\RunStatus;
use Aftandilmmd\WorkflowAutomation\Jobs\ExecuteWorkflowJob;
use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use Aftandilmmd\WorkflowAutomation\Nodes\BaseNode;

#[AsWorkflowNode(key: 'sub_workflow', type: NodeType::Control, label: 'Sub Workflow')]
final class SubWorkflowControl extends BaseNode
{
    public static function configSchema(): array
    {
        return [
            ['key' => 'workflow_id', 'type' => 'workflow_select', 'label' => 'Workflow', 'required' => true],
            ['key' => 'pass_items', 'type' => 'boolean', 'label' => 'Pass items as payload', 'required' => false],
            ['key' => 'wait_for_result', 'type' => 'boolean', 'label' => 'Wait for result', 'required' => false],
        ];
    }

    public static function outputSchema(): array
    {
        return [
            'main' => [
                ['key' => 'sub_workflow_run_id', 'type' => 'number', 'label' => 'Sub Workflow Run ID'],
                ['key' => 'status', 'type' => 'string', 'label' => 'Run Status'],
                ['key' => 'output', 'type' => 'object', 'label' => 'Sub Workflow Output (sync only)'],
            ],
        ];
    }

    public function execute(NodeInput $input, array $config): NodeOutput
    {
        $workflow = Workflow::find($config['workflow_id']);

        if (! $workflow) {
            return NodeOutput::ports([
                'main' => [],
                'error' => [['error' => "Workflow not found: {$config['workflow_id']}"]],
            ]);
        }

        $payload = ($config['pass_items'] ?? false) ? $input->items : [];

        if ($config['wait_for_result'] ?? false) {
            $executor = app(GraphExecutor::class);
            $parentRunId = $input->context->workflowRunId;
            $run = $executor->execute($workflow, $payload, parentRunId: $parentRunId);

            if ($run->status === RunStatus::Failed) {
                return NodeOutput::ports([
                    'main' => [],
                    'error' => [[
                        'sub_workflow_run_id' => $run->id,
                        'status' => $run->status->value,
                        'error_message' => $run->error_message,
                    ]],
                ]);
            }

            return NodeOutput::main([[
                'sub_workflow_run_id' => $run->id,
                'status' => $run->status->value,
                'output' => $run->context ?? [],
            ]]);
        }

        ExecuteWorkflowJob::dispatch($workflow->id, $payload);

        return NodeOutput::main($input->items);
    }
}
