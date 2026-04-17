<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Nodes\Transformers;

use Aftandilmmd\WorkflowAutomation\Attributes\AsWorkflowNode;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeInput;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeOutput;
use Aftandilmmd\WorkflowAutomation\Enums\NodeType;
use Aftandilmmd\WorkflowAutomation\Nodes\BaseNode;
use Throwable;

#[AsWorkflowNode(key: 'parse_data', type: NodeType::Transformer, label: 'Parse Data')]
final class ParseDataTransformer extends BaseNode
{
    public static function configSchema(): array
    {
        return [
            ['key' => 'source_field', 'type' => 'string', 'label' => 'Source field', 'required' => true, 'supports_expression' => true],
            ['key' => 'format', 'type' => 'select', 'label' => 'Format', 'required' => true, 'options' => ['json', 'csv', 'key_value']],
            ['key' => 'target_field', 'type' => 'string', 'label' => 'Target field', 'required' => true],
        ];
    }

    public function execute(NodeInput $input, array $config): NodeOutput
    {
        $results = [];

        foreach ($input->items as $item) {
            try {
                $raw = data_get($item, $config['source_field'], '');
                $parsed = $this->parse((string) $raw, $config['format']);

                $results[] = array_merge($item, [$config['target_field'] => $parsed]);
            } catch (Throwable $e) {
                return NodeOutput::ports([
                    'main' => $results,
                    'error' => [array_merge($item, ['error' => $e->getMessage()])],
                ]);
            }
        }

        return NodeOutput::main($results);
    }

    private function parse(string $raw, string $format): mixed
    {
        return match ($format) {
            'json' => json_decode($raw, true, 512, JSON_THROW_ON_ERROR),
            'csv' => $this->parseCsv($raw),
            'key_value' => $this->parseKeyValue($raw),
            default => $raw,
        };
    }

    private function parseCsv(string $raw): array
    {
        $lines = str_getcsv($raw, "\n");
        if (empty($lines)) {
            return [];
        }

        $headers = str_getcsv(array_shift($lines));
        $result = [];

        foreach ($lines as $line) {
            $values = str_getcsv($line);
            if (count($values) === count($headers)) {
                $result[] = array_combine($headers, $values);
            }
        }

        return $result;
    }

    private function parseKeyValue(string $raw): array
    {
        parse_str($raw, $result);

        return $result;
    }
}
