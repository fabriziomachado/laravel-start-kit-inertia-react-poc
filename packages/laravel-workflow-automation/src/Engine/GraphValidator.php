<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Engine;

use Aftandilmmd\WorkflowAutomation\Enums\NodeType;
use Aftandilmmd\WorkflowAutomation\Exceptions\WorkflowValidationException;
use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use Aftandilmmd\WorkflowAutomation\Registry\NodeRegistry;
use Illuminate\Support\Collection;

final class GraphValidator
{
    public function __construct(
        private readonly NodeRegistry $registry,
    ) {}

    /**
     * Validate a workflow graph. Throws on failure.
     *
     * @throws WorkflowValidationException
     */
    public function validate(Workflow $workflow): void
    {
        $nodes = $workflow->nodes()->get();
        $edges = $workflow->edges()->get();
        $errors = [];

        $this->checkTriggerExists($nodes, $errors);
        $this->checkNodesRegistered($nodes, $errors);
        $this->checkPortValidity($nodes, $edges, $errors);
        $this->checkCycles($nodes, $edges, $errors);
        $this->checkConnectivity($nodes, $edges, $errors);
        $this->checkRequiredConfig($nodes, $errors);

        if (! empty($errors)) {
            throw new WorkflowValidationException($errors);
        }
    }

    /**
     * Validate and return errors array (non-throwing).
     *
     * @return string[]
     */
    public function errors(Workflow $workflow): array
    {
        try {
            $this->validate($workflow);

            return [];
        } catch (WorkflowValidationException $e) {
            return $e->errors;
        }
    }

    private function checkTriggerExists(Collection $nodes, array &$errors): void
    {
        $triggers = $nodes->where('type', NodeType::Trigger);

        if ($triggers->isEmpty()) {
            $errors[] = 'Workflow must have at least one trigger node.';
        }

        if ($triggers->count() > 1) {
            $errors[] = 'Workflow must have exactly one trigger node, found '.$triggers->count().'.';
        }
    }

    private function checkNodesRegistered(Collection $nodes, array &$errors): void
    {
        foreach ($nodes as $node) {
            if (! $this->registry->has($node->node_key)) {
                $nodeName = $node->name ?: $node->node_key;
                $errors[] = "Node '{$nodeName}' (id:{$node->id}) uses unregistered key: {$node->node_key}";
            }
        }
    }

    private function checkPortValidity(Collection $nodes, Collection $edges, array &$errors): void
    {
        $nodeMap = $nodes->keyBy('id');

        foreach ($edges as $edge) {
            $source = $nodeMap->get($edge->source_node_id);
            $target = $nodeMap->get($edge->target_node_id);

            if (! $source) {
                $errors[] = "Edge {$edge->id} references non-existent source node: {$edge->source_node_id}";

                continue;
            }

            if (! $target) {
                $errors[] = "Edge {$edge->id} references non-existent target node: {$edge->target_node_id}";

                continue;
            }

            if ($this->registry->has($source->node_key)) {
                $sourceInstance = $this->registry->resolve($source->node_key);
                $outputPorts = $sourceInstance->outputPorts();

                // Allow dynamic ports for switch nodes (case_*)
                if (! in_array($edge->source_port, $outputPorts) && ! str_starts_with($edge->source_port, 'case_')) {
                    $errors[] = "Edge {$edge->id}: source node '{$source->name}' does not have output port '{$edge->source_port}'. Available: ".implode(', ', $outputPorts);
                }
            }

            if ($this->registry->has($target->node_key)) {
                $targetInstance = $this->registry->resolve($target->node_key);
                $inputPorts = $targetInstance->inputPorts();

                if (! empty($inputPorts) && ! in_array($edge->target_port, $inputPorts)) {
                    $errors[] = "Edge {$edge->id}: target node '{$target->name}' does not have input port '{$edge->target_port}'. Available: ".implode(', ', $inputPorts);
                }
            }
        }
    }

    /**
     * Detect cycles using DFS with color marking.
     */
    private function checkCycles(Collection $nodes, Collection $edges, array &$errors): void
    {
        $adjacency = [];
        foreach ($edges as $edge) {
            $adjacency[$edge->source_node_id][] = $edge->target_node_id;
        }

        // WHITE=0, GRAY=1, BLACK=2
        $colors = [];
        foreach ($nodes as $node) {
            $colors[$node->id] = 0;
        }

        foreach ($nodes as $node) {
            if ($colors[$node->id] === 0) {
                if ($this->dfsCycleCheck($node->id, $adjacency, $colors)) {
                    $errors[] = 'Workflow graph contains a cycle. Cycles are not allowed.';

                    return;
                }
            }
        }
    }

    private function dfsCycleCheck(int $nodeId, array $adjacency, array &$colors): bool
    {
        $colors[$nodeId] = 1; // GRAY — visiting

        foreach ($adjacency[$nodeId] ?? [] as $neighbor) {
            if (! isset($colors[$neighbor])) {
                continue;
            }

            if ($colors[$neighbor] === 1) {
                return true; // Back edge — cycle found
            }

            if ($colors[$neighbor] === 0 && $this->dfsCycleCheck($neighbor, $adjacency, $colors)) {
                return true;
            }
        }

        $colors[$nodeId] = 2; // BLACK — done

        return false;
    }

    /**
     * Check that all non-trigger nodes are reachable from the trigger.
     */
    private function checkConnectivity(Collection $nodes, Collection $edges, array &$errors): void
    {
        $trigger = $nodes->firstWhere('type', NodeType::Trigger);

        if (! $trigger) {
            return; // Already reported by checkTriggerExists
        }

        $adjacency = [];
        foreach ($edges as $edge) {
            $adjacency[$edge->source_node_id][] = $edge->target_node_id;
        }

        $visited = [];
        $this->bfsReach($trigger->id, $adjacency, $visited);

        foreach ($nodes as $node) {
            if ($node->id !== $trigger->id && ! isset($visited[$node->id])) {
                $nodeName = $node->name ?: $node->node_key;
                $errors[] = "Node '{$nodeName}' (id:{$node->id}) is unreachable from the trigger.";
            }
        }
    }

    private function bfsReach(int $startId, array $adjacency, array &$visited): void
    {
        $queue = [$startId];
        $visited[$startId] = true;

        while (! empty($queue)) {
            $current = array_shift($queue);

            foreach ($adjacency[$current] ?? [] as $neighbor) {
                if (! isset($visited[$neighbor])) {
                    $visited[$neighbor] = true;
                    $queue[] = $neighbor;
                }
            }
        }
    }

    private function checkRequiredConfig(Collection $nodes, array &$errors): void
    {
        foreach ($nodes as $node) {
            if (! $this->registry->has($node->node_key)) {
                continue;
            }

            $schema = $this->registry->resolve($node->node_key)::configSchema();
            $config = $node->config ?? [];

            foreach ($schema as $field) {
                if (! ($field['required'] ?? false)) {
                    continue;
                }

                // Skip fields hidden by show_when conditions
                if (isset($field['show_when']) && isset($config[$field['show_when']['key']])) {
                    if ($config[$field['show_when']['key']] !== $field['show_when']['value']) {
                        continue;
                    }
                }

                if (! isset($config[$field['key']])) {
                    $nodeName = $node->name ?: $node->node_key;
                    $errors[] = "Node '{$nodeName}' (id:{$node->id}) is missing required config field: {$field['key']}";
                }
            }
        }
    }
}
