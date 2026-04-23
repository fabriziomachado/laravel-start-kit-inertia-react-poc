<?php

declare(strict_types=1);

namespace App\Services\Workflow;

use Illuminate\Support\Facades\Http;
use RuntimeException;

final class OpenAiFieldExtractor implements AiFieldExtractor
{
    public function isAvailable(): bool
    {
        return filled(config('services.openai.api_key'));
    }

    public function extract(array $fields, string $freeText): array
    {
        if (! $this->isAvailable()) {
            return [];
        }

        $schema = [];
        foreach ($fields as $field) {
            if (! is_array($field) || ! isset($field['key'])) {
                continue;
            }
            $schema[] = [
                'key' => (string) $field['key'],
                'label' => (string) ($field['label'] ?? ''),
                'type' => (string) ($field['type'] ?? 'string'),
                'required' => (bool) ($field['required'] ?? false),
            ];
        }

        $model = (string) config('services.openai.model', 'gpt-4o-mini');

        $response = Http::withToken((string) config('services.openai.api_key'))
            ->timeout((int) config('services.openai.timeout', 60))
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Extrai valores de campos de formulário a partir de texto livre. Responde APENAS com um objeto JSON: chaves são os field keys pedidos, valores string/number/boolean conforme o tipo. Omite chaves que não conseguires inferir com confiança.',
                    ],
                    [
                        'role' => 'user',
                        'content' => json_encode([
                            'fields' => $schema,
                            'text' => $freeText,
                        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    ],
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('OpenAI extract falhou: '.$response->body());
        }

        /** @var array<string, mixed> $json */
        $json = $response->json();
        $content = $json['choices'][0]['message']['content'] ?? null;
        if (! is_string($content)) {
            throw new RuntimeException('Resposta OpenAI inválida.');
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        $out = [];
        foreach ($schema as $row) {
            $key = $row['key'];
            if (! array_key_exists($key, $decoded)) {
                continue;
            }
            $out[$key] = $decoded[$key];
        }

        return $out;
    }
}
