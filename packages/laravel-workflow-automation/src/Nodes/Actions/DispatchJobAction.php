<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Nodes\Actions;

use Aftandilmmd\WorkflowAutomation\Attributes\AsWorkflowNode;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeInput;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeOutput;
use Aftandilmmd\WorkflowAutomation\Enums\NodeType;
use Aftandilmmd\WorkflowAutomation\Nodes\BaseNode;
use Throwable;

#[AsWorkflowNode(key: 'dispatch_job', type: NodeType::Action, label: 'Dispatch Job')]
final class DispatchJobAction extends BaseNode
{
    public static function configSchema(): array
    {
        return [
            ['key' => 'job_class', 'type' => 'string', 'label' => 'Job Class', 'required' => true],
            ['key' => 'queue', 'type' => 'string', 'label' => 'Queue Name', 'required' => false],
            ['key' => 'delay', 'type' => 'integer', 'label' => 'Delay (seconds)', 'required' => false],
            ['key' => 'with_item', 'type' => 'boolean', 'label' => 'Pass item data to job', 'required' => false],
        ];
    }

    public static function outputSchema(): array
    {
        return [
            'main' => [
                ['key' => 'job_dispatched', 'type' => 'string', 'label' => 'Dispatched Job Class'],
            ],
        ];
    }

    public function execute(NodeInput $input, array $config): NodeOutput
    {
        $results = [];

        foreach ($input->items as $item) {
            try {
                $jobClass = $config['job_class'];
                $payload = ($config['with_item'] ?? false) ? $item : [];
                $job = new $jobClass($payload);

                if (! empty($config['queue'])) {
                    $job = $job->onQueue($config['queue']);
                }

                if (! empty($config['delay'])) {
                    $job = $job->delay($config['delay']);
                }

                dispatch($job);

                $results[] = array_merge($item, ['job_dispatched' => $jobClass]);
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
