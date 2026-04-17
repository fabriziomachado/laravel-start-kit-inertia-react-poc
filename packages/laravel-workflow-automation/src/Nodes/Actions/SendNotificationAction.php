<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Nodes\Actions;

use Aftandilmmd\WorkflowAutomation\Attributes\AsWorkflowNode;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeInput;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeOutput;
use Aftandilmmd\WorkflowAutomation\Enums\NodeType;
use Aftandilmmd\WorkflowAutomation\Nodes\BaseNode;
use Throwable;

#[AsWorkflowNode(key: 'send_notification', type: NodeType::Action, label: 'Send Notification')]
final class SendNotificationAction extends BaseNode
{
    public static function configSchema(): array
    {
        return [
            ['key' => 'notification_class', 'type' => 'string', 'label' => 'Notification Class', 'required' => true],
            ['key' => 'notifiable_class', 'type' => 'string', 'label' => 'Notifiable Class', 'required' => true],
            ['key' => 'notifiable_id', 'type' => 'string', 'label' => 'Notifiable ID', 'required' => true, 'supports_expression' => true],
        ];
    }

    public static function outputSchema(): array
    {
        return [
            'main' => [
                ['key' => 'notification_sent', 'type' => 'boolean', 'label' => 'Notification Sent'],
            ],
        ];
    }

    public function execute(NodeInput $input, array $config): NodeOutput
    {
        $results = [];

        foreach ($input->items as $item) {
            try {
                $notifiable = app($config['notifiable_class'])
                    ->find($config['notifiable_id']);

                if ($notifiable) {
                    $notifiable->notify(new $config['notification_class']($item));
                }

                $results[] = array_merge($item, ['notification_sent' => (bool) $notifiable]);
            } catch (Throwable $e) {
                return NodeOutput::ports([
                    'main' => $results,
                    'error' => [array_merge($item, ['error' => $e->getMessage()])],
                ]);
            }
        }

        return NodeOutput::main($results);
    }
}
