<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Listeners;

use Aftandilmmd\WorkflowAutomation\Enums\NodeType;
use Aftandilmmd\WorkflowAutomation\Jobs\ExecuteWorkflowJob;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowNode;
use Aftandilmmd\WorkflowAutomation\Registry\NodeRegistry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

final class EventListener
{
    /**
     * Register Laravel event listeners for all active event triggers.
     */
    public static function register(): void
    {
        $triggers = self::getActiveTriggers();

        foreach ($triggers as $trigger) {
            $eventClass = $trigger['config']['event_class'] ?? null;

            if (! $eventClass || ! class_exists($eventClass)) {
                continue;
            }

            Event::listen($eventClass, function (object $event) use ($trigger) {
                static::handleEvent($event, $trigger);
            });
        }
    }

    private static function handleEvent(object $event, array $trigger): void
    {
        $node = app(NodeRegistry::class)->resolve('event');

        ExecuteWorkflowJob::dispatch(
            workflowId: $trigger['workflow_id'],
            payload: $node->extractPayload($event),
            triggerNodeId: $trigger['node_id'],
        )->onQueue(config('workflow-automation.queue', 'default'));
    }

    /**
     * @return array<int, array{workflow_id: int, node_id: int, config: array}>
     */
    private static function getActiveTriggers(): array
    {
        try {
            return Cache::remember('workflow:event_triggers', 60, function () {
                return WorkflowNode::query()
                    ->where('type', NodeType::Trigger)
                    ->where('node_key', 'event')
                    ->whereHas('workflow', fn ($q) => $q->where('is_active', true))
                    ->get()
                    ->map(fn (WorkflowNode $node) => [
                        'workflow_id' => $node->workflow_id,
                        'node_id' => $node->id,
                        'config' => $node->config ?? [],
                    ])
                    ->toArray();
            });
        } catch (\Illuminate\Database\QueryException) {
            return [];
        }
    }
}
