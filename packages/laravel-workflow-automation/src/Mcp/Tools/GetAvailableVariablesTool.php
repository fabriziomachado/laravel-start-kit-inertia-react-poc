<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Mcp\Tools;

use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowNode;
use Aftandilmmd\WorkflowAutomation\Registry\NodeRegistry;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_available_variables')]
#[Title('Get Available Variables')]
#[Description('Get all variables available for a node\'s expressions — globals, upstream node outputs, and built-in functions. Use this to discover what variables can be used in {{ }} expressions when configuring a node.')]
#[IsReadOnly]
final class GetAvailableVariablesTool extends Tool
{
    public function __construct(
        protected NodeRegistry $registry,
    ) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'workflow_id' => $schema->integer()->required()->description('The workflow ID'),
            'node_id' => $schema->integer()->required()->description('The node ID to get available variables for'),
        ];
    }

    public function handle(Request $request): Response
    {
        $workflow = Workflow::findOrFail($request->get('workflow_id'));
        $node = WorkflowNode::findOrFail($request->get('node_id'));

        $nodes = $workflow->nodes()->get()->keyBy('id');
        $edges = $workflow->edges()->get();

        // BFS backward from target node to find all upstream nodes
        $reverseAdj = [];
        foreach ($edges as $edge) {
            $reverseAdj[$edge->target_node_id][] = $edge->source_node_id;
        }

        $upstreamIds = [];
        $queue = [$node->id];
        while (! empty($queue)) {
            $current = array_shift($queue);
            foreach ($reverseAdj[$current] ?? [] as $parentId) {
                if (! isset($upstreamIds[$parentId])) {
                    $upstreamIds[$parentId] = true;
                    $queue[] = $parentId;
                }
            }
        }

        // Build upstream nodes with their output schemas
        $upstreamNodes = [];
        foreach ($upstreamIds as $nodeId => $_) {
            $upNode = $nodes->get($nodeId);
            if (! $upNode || ! $this->registry->has($upNode->node_key)) {
                continue;
            }

            $outputSchema = $this->registry->resolve($upNode->node_key)::outputSchema();
            $nodeName = $upNode->name ?: $upNode->node_key;

            $variables = [];
            foreach ($outputSchema as $port => $fields) {
                foreach ($fields as $field) {
                    if ($field['key'] === '*') {
                        continue;
                    }
                    $variables[] = [
                        'path' => "nodes.{$nodeName}.{$port}.0.{$field['key']}",
                        'type' => $field['type'],
                        'label' => $field['label'],
                    ];
                }
            }

            $upstreamNodes[] = [
                'node_id' => $nodeId,
                'node_name' => $nodeName,
                'node_key' => $upNode->node_key,
                'variables' => $variables,
            ];
        }

        return Response::json([
            'globals' => [
                ['path' => 'item', 'type' => 'object', 'label' => 'Current Item'],
                ['path' => 'payload', 'type' => 'object', 'label' => 'Initial Payload'],
                ['path' => 'trigger', 'type' => 'array', 'label' => 'Trigger Output'],
            ],
            'nodes' => $upstreamNodes,
            'functions' => $this->getAvailableFunctions(),
        ]);
    }

    private function getAvailableFunctions(): array
    {
        return [
            ['name' => 'upper', 'args' => 'value', 'label' => 'Uppercase'],
            ['name' => 'lower', 'args' => 'value', 'label' => 'Lowercase'],
            ['name' => 'trim', 'args' => 'value', 'label' => 'Trim whitespace'],
            ['name' => 'length', 'args' => 'value', 'label' => 'String length / Array count'],
            ['name' => 'contains', 'args' => 'haystack, needle', 'label' => 'Contains substring'],
            ['name' => 'replace', 'args' => 'search, replace, subject', 'label' => 'Replace text'],
            ['name' => 'split', 'args' => 'separator, string', 'label' => 'Split string'],
            ['name' => 'join', 'args' => 'glue, array', 'label' => 'Join array'],
            ['name' => 'round', 'args' => 'value, precision?', 'label' => 'Round number'],
            ['name' => 'abs', 'args' => 'value', 'label' => 'Absolute value'],
            ['name' => 'min', 'args' => '...values', 'label' => 'Minimum'],
            ['name' => 'max', 'args' => '...values', 'label' => 'Maximum'],
            ['name' => 'sum', 'args' => 'array', 'label' => 'Sum array'],
            ['name' => 'count', 'args' => 'array', 'label' => 'Count items'],
            ['name' => 'first', 'args' => 'array', 'label' => 'First element'],
            ['name' => 'last', 'args' => 'array', 'label' => 'Last element'],
            ['name' => 'pluck', 'args' => 'array, key', 'label' => 'Pluck field from array'],
            ['name' => 'now', 'args' => '', 'label' => 'Current datetime'],
            ['name' => 'date_format', 'args' => 'date, format', 'label' => 'Format date'],
            ['name' => 'int', 'args' => 'value', 'label' => 'Cast to integer'],
            ['name' => 'string', 'args' => 'value', 'label' => 'Cast to string'],
            ['name' => 'json_encode', 'args' => 'value', 'label' => 'JSON encode'],
            ['name' => 'json_decode', 'args' => 'value', 'label' => 'JSON decode'],
        ];
    }
}
