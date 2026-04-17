<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Facades;

use Aftandilmmd\WorkflowAutomation\Services\WorkflowService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Aftandilmmd\WorkflowAutomation\Models\WorkflowRun run(int|\Aftandilmmd\WorkflowAutomation\Models\Workflow $workflow, array $payload = [])
 * @method static void runAsync(int|\Aftandilmmd\WorkflowAutomation\Models\Workflow $workflow, array $payload = [])
 * @method static \Aftandilmmd\WorkflowAutomation\Models\WorkflowRun resume(int|\Aftandilmmd\WorkflowAutomation\Models\WorkflowRun $run, string $resumeToken, array $payload = [])
 * @method static \Aftandilmmd\WorkflowAutomation\Models\WorkflowRun cancel(int|\Aftandilmmd\WorkflowAutomation\Models\WorkflowRun $run)
 * @method static \Aftandilmmd\WorkflowAutomation\Models\WorkflowRun replay(int|\Aftandilmmd\WorkflowAutomation\Models\WorkflowRun $run)
 * @method static \Aftandilmmd\WorkflowAutomation\Models\WorkflowRun retryFromFailure(int|\Aftandilmmd\WorkflowAutomation\Models\WorkflowRun $run)
 * @method static \Aftandilmmd\WorkflowAutomation\Models\WorkflowRun retryNode(int|\Aftandilmmd\WorkflowAutomation\Models\WorkflowRun $run, int $nodeId)
 * @method static \Aftandilmmd\WorkflowAutomation\Models\Workflow create(array $data)
 * @method static \Aftandilmmd\WorkflowAutomation\Models\Workflow update(int|\Aftandilmmd\WorkflowAutomation\Models\Workflow $workflow, array $data)
 * @method static void delete(int|\Aftandilmmd\WorkflowAutomation\Models\Workflow $workflow)
 * @method static \Aftandilmmd\WorkflowAutomation\Models\Workflow duplicate(int|\Aftandilmmd\WorkflowAutomation\Models\Workflow $workflow)
 * @method static \Aftandilmmd\WorkflowAutomation\Models\Workflow activate(int|\Aftandilmmd\WorkflowAutomation\Models\Workflow $workflow)
 * @method static \Aftandilmmd\WorkflowAutomation\Models\Workflow deactivate(int|\Aftandilmmd\WorkflowAutomation\Models\Workflow $workflow)
 * @method static array validate(int|\Aftandilmmd\WorkflowAutomation\Models\Workflow $workflow)
 * @method static \Aftandilmmd\WorkflowAutomation\Models\WorkflowNode addNode(int|\Aftandilmmd\WorkflowAutomation\Models\Workflow $workflow, string $nodeKey, array $config = [], ?string $name = null)
 * @method static \Aftandilmmd\WorkflowAutomation\Models\WorkflowEdge connect(int|\Aftandilmmd\WorkflowAutomation\Models\WorkflowNode $source, int|\Aftandilmmd\WorkflowAutomation\Models\WorkflowNode $target, string $sourcePort = 'main', string $targetPort = 'main')
 * @method static void removeNode(int $nodeId)
 * @method static void removeEdge(int $edgeId)
 *
 * @see WorkflowService
 */
final class Workflow extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return WorkflowService::class;
    }
}
