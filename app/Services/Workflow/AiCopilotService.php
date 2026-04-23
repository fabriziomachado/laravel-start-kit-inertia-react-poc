<?php

declare(strict_types=1);

namespace App\Services\Workflow;

use Aftandilmmd\WorkflowAutomation\Models\WorkflowRun;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class AiCopilotService
{
    public function isAvailable(): bool
    {
        return filled(config('services.openai.api_key'));
    }

    public function answer(WorkflowRun $run, string $userMessage): string
    {
        if (! $this->isAvailable()) {
            throw new RuntimeException('Copiloto indisponível: configure OPENAI_API_KEY.');
        }

        $run->loadMissing('workflow');
        $workflowName = $run->workflow?->name ?? 'Fluxo';
        $context = $run->context;
        $contextJson = is_array($context)
            ? json_encode($context, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)
            : '{}';

        if (mb_strlen($contextJson) > 6000) {
            $contextJson = mb_substr($contextJson, 0, 6000).'…';
        }

        $model = (string) config('services.openai.model', 'gpt-4o-mini');

        $response = Http::withToken((string) config('services.openai.api_key'))
            ->timeout((int) config('services.openai.timeout', 60))
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'És um assistente que explica o estado de um processo de workflow ao utilizador. '
                            .'Não inventes dados fora do contexto JSON. Respostas curtas em português de Portugal.',
                    ],
                    [
                        'role' => 'user',
                        'content' => "Nome do fluxo: {$workflowName}\nContexto (JSON):\n{$contextJson}\n\nPergunta: {$userMessage}",
                    ],
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('OpenAI copilot falhou: '.$response->body());
        }

        /** @var array<string, mixed> $json */
        $json = $response->json();
        $content = $json['choices'][0]['message']['content'] ?? null;
        if (! is_string($content)) {
            throw new RuntimeException('Resposta OpenAI inválida.');
        }

        return mb_trim($content);
    }
}
