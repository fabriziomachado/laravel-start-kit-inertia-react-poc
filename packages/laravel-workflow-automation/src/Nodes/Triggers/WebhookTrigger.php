<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Nodes\Triggers;

use Aftandilmmd\WorkflowAutomation\Attributes\AsWorkflowNode;
use Aftandilmmd\WorkflowAutomation\Contracts\TriggerInterface;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeInput;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeOutput;
use Aftandilmmd\WorkflowAutomation\Enums\NodeType;

#[AsWorkflowNode(key: 'webhook', type: NodeType::Trigger, label: 'Webhook')]
final class WebhookTrigger implements TriggerInterface
{
    use \Aftandilmmd\WorkflowAutomation\Nodes\HasDocumentation;

    public static function configSchema(): array
    {
        return [
            ['key' => 'path', 'type' => 'string', 'label' => 'Webhook Path (auto-generated UUID)', 'required' => false, 'readonly' => true],
            ['key' => 'method', 'type' => 'select', 'label' => 'HTTP Method', 'options' => ['GET', 'POST', 'PUT', 'PATCH'], 'required' => true],
            ['key' => 'auth_type', 'type' => 'select', 'label' => 'Authentication', 'options' => ['none', 'basic', 'bearer', 'header_key'], 'required' => true],
            ['key' => 'credential_id', 'type' => 'credential', 'label' => 'Credential', 'required' => false, 'credential_types' => ['bearer_token', 'basic_auth', 'header_auth'], 'show_when' => ['key' => 'auth_type', 'value' => ['basic', 'bearer', 'header_key']]],
            ['key' => 'auth_value', 'type' => 'string', 'label' => 'Auth Value (legacy)', 'required' => false, 'description' => 'Use Credential field above instead for encrypted storage.', 'show_when' => ['key' => 'auth_type', 'value' => ['basic', 'bearer', 'header_key']]],
        ];
    }

    public static function outputSchema(): array
    {
        return [
            'main' => [
                ['key' => 'body', 'type' => 'object', 'label' => 'Request Body'],
                ['key' => 'headers', 'type' => 'object', 'label' => 'Request Headers'],
                ['key' => 'query', 'type' => 'object', 'label' => 'Query Parameters'],
            ],
        ];
    }

    public function inputPorts(): array
    {
        return [];
    }

    public function outputPorts(): array
    {
        return ['main'];
    }

    public function register(int $workflowId, int $nodeId, array $config): void
    {
        // Webhook route handled by WebhookController
    }

    public function unregister(int $workflowId, int $nodeId, array $config): void {}

    public function extractPayload(mixed $event): array
    {
        if ($event instanceof \Illuminate\Http\Request) {
            return [$event->all()];
        }

        return is_array($event) ? [$event] : [[]];
    }

    public function execute(NodeInput $input, array $config): NodeOutput
    {
        return NodeOutput::main($input->items);
    }
}
