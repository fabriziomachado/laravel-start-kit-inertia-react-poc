<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Nodes\Controls;

use Aftandilmmd\WorkflowAutomation\Attributes\AsWorkflowNode;
use Aftandilmmd\WorkflowAutomation\Contracts\NodeInterface;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeInput;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeOutput;
use Aftandilmmd\WorkflowAutomation\Enums\NodeType;

#[AsWorkflowNode(key: 'error_handler', type: NodeType::Control, label: 'Error Handler')]
final class ErrorHandlerControl implements NodeInterface
{
    use \Aftandilmmd\WorkflowAutomation\Nodes\HasDocumentation;

    public static function configSchema(): array
    {
        return [
            ['key' => 'rules', 'type' => 'array_of_objects', 'label' => 'Routing Rules', 'required' => true, 'schema' => [
                ['key' => 'match', 'type' => 'string', 'label' => 'Error message pattern (regex)'],
                ['key' => 'route', 'type' => 'select', 'label' => 'Route to port', 'options' => ['notify', 'retry', 'ignore', 'stop']],
            ]],
            ['key' => 'default_route', 'type' => 'select', 'label' => 'Default route (no match)', 'options' => ['notify', 'retry', 'ignore', 'stop'], 'required' => false],
        ];
    }

    public static function outputSchema(): array
    {
        return [];
    }

    public function inputPorts(): array
    {
        return ['main'];
    }

    public function outputPorts(): array
    {
        return ['notify', 'retry', 'ignore', 'stop'];
    }

    public function execute(NodeInput $input, array $config): NodeOutput
    {
        $portItems = [];
        $rules = $config['rules'] ?? [];
        $defaultRoute = $config['default_route'] ?? 'notify';

        foreach ($input->items as $item) {
            $errorMessage = $item['error'] ?? $item['error_message'] ?? '';
            $routed = false;

            foreach ($rules as $rule) {
                $pattern = '/'.$rule['match'].'/i';

                if (@preg_match($pattern, (string) $errorMessage)) {
                    $portItems[$rule['route']][] = $item;
                    $routed = true;

                    break;
                }
            }

            if (! $routed) {
                $portItems[$defaultRoute][] = $item;
            }
        }

        return NodeOutput::ports($portItems);
    }
}
