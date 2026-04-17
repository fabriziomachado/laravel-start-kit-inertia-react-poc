<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Contracts;

use Aftandilmmd\WorkflowAutomation\DTOs\NodeInput;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeOutput;

interface NodeInterface
{
    /**
     * Config field definitions for UI form generation.
     *
     * Each entry: ['key' => string, 'type' => string, 'label' => string, 'required' => bool, ...]
     *
     * @return array<int, array<string, mixed>>
     */
    public static function configSchema(): array;

    /**
     * Output field definitions per port for variable discovery.
     *
     * Each entry: ['key' => string, 'type' => string, 'label' => string]
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function outputSchema(): array;

    /**
     * Input port names this node accepts.
     * Trigger nodes return an empty array.
     *
     * @return string[]
     */
    public function inputPorts(): array;

    /**
     * Output port names this node can produce.
     *
     * @return string[]
     */
    public function outputPorts(): array;

    /**
     * Execute the node's logic.
     */
    public function execute(NodeInput $input, array $config): NodeOutput;
}
