<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Listeners;

use Aftandilmmd\WorkflowAutomation\Enums\NodeType;
use Aftandilmmd\WorkflowAutomation\Jobs\ExecuteWorkflowJob;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowNode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

final class ModelEventListener
{
    /**
     * Register model event listeners for all active model_event triggers.
     */
    public static function register(): void
    {
        $triggers = self::getActiveTriggers();

        foreach ($triggers as $trigger) {
            $modelClass = $trigger['config']['model'] ?? null;
            $events = $trigger['config']['events'] ?? [];

            if (! $modelClass || ! class_exists($modelClass) || empty($events)) {
                continue;
            }

            $usesSoftDeletes = in_array(SoftDeletes::class, class_uses_recursive($modelClass));

            foreach ($events as $eventName) {
                if (! $usesSoftDeletes && in_array($eventName, ['restored', 'forceDeleted'])) {
                    continue;
                }

                $modelClass::{$eventName}(function (Model $model) use ($trigger, $eventName) {
                    static::handleEvent($model, $trigger, $eventName);
                });
            }
        }
    }

    private static function handleEvent(Model $model, array $trigger, string $eventName): void
    {
        $config = $trigger['config'];

        // Check only_fields filter for 'updated' event
        if ($eventName === 'updated' && ! empty($config['only_fields'])) {
            $changed = array_keys($model->getDirty());
            $watched = (array) $config['only_fields'];

            if (empty(array_intersect($changed, $watched))) {
                return;
            }
        }

        ExecuteWorkflowJob::dispatch(
            workflowId: $trigger['workflow_id'],
            payload: [$model->toArray()],
            triggerNodeId: $trigger['node_id'],
        )->onQueue(config('workflow-automation.queue', 'default'));
    }

    /**
     * @return array<int, array{workflow_id: int, node_id: int, config: array}>
     */
    private static function getActiveTriggers(): array
    {
        try {
            return Cache::remember('workflow:model_event_triggers', 60, function () {
                return WorkflowNode::query()
                    ->where('type', NodeType::Trigger)
                    ->where('node_key', 'model_event')
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
