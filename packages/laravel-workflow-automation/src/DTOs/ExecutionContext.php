<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\DTOs;

final class ExecutionContext
{
    /** @var array<int, array<string, array<int, array<string, mixed>>>> */
    private array $nodeOutputs = [];

    private bool $testMode = false;

    public function __construct(
        public readonly int $workflowRunId,
        public readonly int $workflowId,
        public readonly array $initialPayload = [],
    ) {}

    public function enableTestMode(): void
    {
        $this->testMode = true;
    }

    public function isTestMode(): bool
    {
        return $this->testMode;
    }

    /**
     * Store a node's output for a specific port.
     */
    public function setNodeOutput(int $nodeId, string $port, array $items): void
    {
        $this->nodeOutputs[$nodeId][$port] = $items;
    }

    /**
     * Retrieve a node's output for a specific port.
     */
    public function getNodeOutput(int $nodeId, string $port = 'main'): array
    {
        return $this->nodeOutputs[$nodeId][$port] ?? [];
    }

    /**
     * Get all stored node outputs (for persisting to workflow_runs.context).
     */
    public function getAllOutputs(): array
    {
        return $this->nodeOutputs;
    }

    /**
     * Restore previously persisted outputs (for resume scenarios).
     */
    public function restoreOutputs(array $outputs): void
    {
        $this->nodeOutputs = $outputs;
    }

    /**
     * Build a flat variables map for the expression evaluator.
     *
     * @param  array<string, int>  $nodeNameMap  Map of node name => node ID.
     * @param  array<string, mixed>  $currentItem  The current item being processed.
     * @return array<string, mixed>
     */
    public function toVariables(array $nodeNameMap = [], array $currentItem = []): array
    {
        $triggerOutput = [];
        foreach ($this->nodeOutputs as $outputs) {
            if (isset($outputs['main'])) {
                $triggerOutput = $outputs['main'];
                break;
            }
        }

        $namedNodes = [];
        foreach ($nodeNameMap as $name => $nodeId) {
            if (isset($this->nodeOutputs[$nodeId])) {
                $namedNodes[$name] = $this->nodeOutputs[$nodeId];
            }
        }

        return [
            'trigger' => $triggerOutput,
            'node' => $this->nodeOutputs,
            'nodes' => $namedNodes,
            'item' => $currentItem,
            'payload' => $this->initialPayload,
        ];
    }
}
