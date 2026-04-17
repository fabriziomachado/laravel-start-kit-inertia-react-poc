<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Nodes\Actions;

use Aftandilmmd\WorkflowAutomation\Attributes\AsWorkflowNode;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeInput;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeOutput;
use Aftandilmmd\WorkflowAutomation\Enums\NodeType;
use Aftandilmmd\WorkflowAutomation\Nodes\BaseNode;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Throwable;

#[AsWorkflowNode(key: 'send_mail', type: NodeType::Action, label: 'Send Mail')]
final class SendMailAction extends BaseNode
{
    public static function configSchema(): array
    {
        return [
            ['key' => 'send_mode', 'type' => 'select', 'label' => 'Send Mode', 'required' => true, 'options' => ['inline', 'mailable']],

            // Inline mode fields
            ['key' => 'to', 'type' => 'string', 'label' => 'To (comma-separated)', 'required' => true, 'supports_expression' => true, 'show_when' => ['key' => 'send_mode', 'value' => 'inline']],
            ['key' => 'cc', 'type' => 'string', 'label' => 'CC (comma-separated)', 'required' => false, 'supports_expression' => true, 'show_when' => ['key' => 'send_mode', 'value' => 'inline']],
            ['key' => 'bcc', 'type' => 'string', 'label' => 'BCC (comma-separated)', 'required' => false, 'supports_expression' => true, 'show_when' => ['key' => 'send_mode', 'value' => 'inline']],
            ['key' => 'reply_to', 'type' => 'string', 'label' => 'Reply-To', 'required' => false, 'supports_expression' => true, 'show_when' => ['key' => 'send_mode', 'value' => 'inline']],
            ['key' => 'subject', 'type' => 'string', 'label' => 'Subject', 'required' => true, 'supports_expression' => true, 'show_when' => ['key' => 'send_mode', 'value' => 'inline']],
            ['key' => 'body', 'type' => 'textarea', 'label' => 'Body', 'required' => true, 'supports_expression' => true, 'show_when' => ['key' => 'send_mode', 'value' => 'inline']],
            ['key' => 'from', 'type' => 'string', 'label' => 'From', 'required' => false, 'show_when' => ['key' => 'send_mode', 'value' => 'inline']],
            ['key' => 'is_html', 'type' => 'boolean', 'label' => 'HTML?', 'required' => false, 'show_when' => ['key' => 'send_mode', 'value' => 'inline']],
            ['key' => 'attachments', 'type' => 'keyvalue', 'label' => 'Attachments (name => path)', 'required' => false, 'supports_expression' => true, 'show_when' => ['key' => 'send_mode', 'value' => 'inline']],

            // Mailable mode fields
            ['key' => 'mailable_class', 'type' => 'string', 'label' => 'Mailable Class (FQN)', 'required' => true, 'show_when' => ['key' => 'send_mode', 'value' => 'mailable']],
            ['key' => 'mailable_to', 'type' => 'string', 'label' => 'To (comma-separated)', 'required' => true, 'supports_expression' => true, 'show_when' => ['key' => 'send_mode', 'value' => 'mailable']],
        ];
    }

    public static function outputSchema(): array
    {
        return [
            'main' => [
                ['key' => 'mail_sent', 'type' => 'boolean', 'label' => 'Mail Sent'],
            ],
        ];
    }

    public function execute(NodeInput $input, array $config): NodeOutput
    {
        $results = [];
        $mode = $config['send_mode'] ?? 'inline';

        foreach ($input->items as $item) {
            try {
                if ($mode === 'mailable') {
                    $this->sendViaMailable($config, $item);
                } else {
                    $this->sendInline($config);
                }

                $results[] = array_merge($item, ['mail_sent' => true]);
            } catch (Throwable $e) {
                return NodeOutput::ports([
                    'main' => $results,
                    'error' => [array_merge($item, ['error' => $e->getMessage()])],
                ]);
            }
        }

        return NodeOutput::main($results);
    }

    private function sendInline(array $config): void
    {
        $callback = function ($message) use ($config) {
            $message->to($this->parseEmails($config['to'] ?? ''))
                ->subject($config['subject'] ?? '');

            if (! empty($config['from'])) {
                $message->from($config['from']);
            }

            if (! empty($config['cc'])) {
                foreach ($this->parseEmails($config['cc']) as $cc) {
                    $message->cc($cc);
                }
            }

            if (! empty($config['bcc'])) {
                foreach ($this->parseEmails($config['bcc']) as $bcc) {
                    $message->bcc($bcc);
                }
            }

            if (! empty($config['reply_to'])) {
                $message->replyTo($config['reply_to']);
            }

            if (! empty($config['attachments'])) {
                foreach ($config['attachments'] as $name => $path) {
                    $message->attach($path, ['as' => $name]);
                }
            }
        };

        if (! empty($config['is_html'])) {
            Mail::html($config['body'] ?? '', $callback);
        } else {
            Mail::raw($config['body'] ?? '', $callback);
        }
    }

    private function sendViaMailable(array $config, array $item): void
    {
        $mailableClass = $config['mailable_class']
            ?? throw new InvalidArgumentException('mailable_class is required in mailable mode');

        if (! class_exists($mailableClass)) {
            throw new InvalidArgumentException("Mailable class not found: {$mailableClass}");
        }

        $to = $this->parseEmails($config['mailable_to'] ?? '');

        if (empty($to)) {
            throw new InvalidArgumentException('mailable_to is required in mailable mode');
        }

        Mail::to($to)->send(new $mailableClass($item));
    }

    private function parseEmails(string $value): array
    {
        if (empty($value)) {
            return [];
        }

        return array_filter(array_map('trim', explode(',', $value)));
    }
}
