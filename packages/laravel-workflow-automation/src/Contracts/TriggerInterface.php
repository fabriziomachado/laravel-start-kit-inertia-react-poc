<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Contracts;

interface TriggerInterface extends NodeInterface
{
    /**
     * Register this trigger in the system (e.g. event listener, schedule entry, webhook route).
     */
    public function register(int $workflowId, int $nodeId, array $config): void;

    /**
     * Remove this trigger from the system.
     */
    public function unregister(int $workflowId, int $nodeId, array $config): void;

    /**
     * Convert the raw trigger event into a standard items array.
     *
     * @return array<int, array<string, mixed>>
     */
    public function extractPayload(mixed $event): array;
}
