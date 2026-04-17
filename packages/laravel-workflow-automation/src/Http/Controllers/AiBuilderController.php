<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Http\Controllers;

use Aftandilmmd\WorkflowAutomation\Http\Requests\AiBuildRequest;
use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use Aftandilmmd\WorkflowAutomation\Registry\NodeRegistry;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use InvalidArgumentException;
use Throwable;

final class AiBuilderController extends Controller
{
    public function __construct(
        protected NodeRegistry $registry,
    ) {}

    public function build(AiBuildRequest $request, int $workflow)
    {
        if (! config('workflow-automation.ai_builder.enabled', true)) {
            return response()->json(['message' => 'AI Builder is disabled.'], 403);
        }

        if (! interface_exists(\Laravel\Ai\Contracts\Agent::class)) {
            return response()->json([
                'message' => 'The AI Builder requires the laravel/ai package. Install it with: composer require laravel/ai',
            ], 500);
        }

        if (! class_exists(\Laravel\Mcp\Server\Tool::class)) {
            return response()->json([
                'message' => 'The AI Builder requires the laravel/mcp package. Install it with: composer require laravel/mcp',
            ], 500);
        }

        try {
            $workflow = Workflow::findOrFail($workflow);

            /** @var \Aftandilmmd\WorkflowAutomation\AiBuilder\WorkflowBuilderAgent $agent */
            $agentClass = 'Aftandilmmd\\WorkflowAutomation\\AiBuilder\\WorkflowBuilderAgent';
            $agent = new $agentClass($workflow, $this->registry);

            $provider = $request->validated('provider', config('workflow-automation.ai_builder.default_provider'));
            $model = $request->validated('model', config('workflow-automation.ai_builder.default_model'));

            $args = [];

            if ($provider) {
                $args['provider'] = $this->resolveProvider($provider);
            }

            if ($model) {
                $args['model'] = $model;
            }

            // #region agent log
            $this->agentDebugLog('H1-H4', 'AiBuilderController.php:pre_stream', 'ai_build_stream_start', [
                'workflow_id' => $workflow->id,
                'provider_request' => $provider,
                'model' => $model,
                'openai_key_configured' => filled(config('ai.providers.openai.key')),
                'prompt_length' => mb_strlen($request->validated('prompt')),
            ]);
            // #endregion

            $streamable = $agent->stream($request->validated('prompt'), ...$args);

            return response()->stream(function () use ($streamable, $provider, $model, $workflow) {
                try {
                    foreach ($streamable as $event) {
                        echo 'data: '.json_encode($event->toArray())."\n\n";
                        ob_flush();
                        flush();
                    }
                    echo "data: [DONE]\n\n";
                    ob_flush();
                    flush();
                } catch (Throwable $e) {
                    // #region agent log
                    $httpData = [
                        'exception' => $e::class,
                        'message' => $e->getMessage(),
                        'provider_request' => $provider,
                        'model' => $model,
                        'workflow_id' => $workflow->id,
                    ];
                    if ($e instanceof RequestException && $e->response !== null) {
                        $httpData['http_status'] = $e->response->status();
                        $httpData['response_body_preview'] = mb_substr($e->response->body(), 0, 800);
                    }
                    $this->agentDebugLog('H5', 'AiBuilderController.php:stream_catch', 'ai_build_stream_exception', $httpData);
                    // #endregion

                    $error = json_encode([
                        'type' => 'error',
                        'message' => $e->getMessage().(config('app.debug') ? ' in '.$e->getFile().':'.$e->getLine() : ''),
                    ]);
                    echo "data: {$error}\n\n";
                    echo "data: [DONE]\n\n";
                    ob_flush();
                    flush();
                }
            }, 200, [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'Connection' => 'keep-alive',
                'X-Accel-Buffering' => 'no',
            ]);
        } catch (Throwable $e) {
            // #region agent log
            $httpData = [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ];
            if ($e instanceof RequestException && $e->response !== null) {
                $httpData['http_status'] = $e->response->status();
                $httpData['response_body_preview'] = mb_substr($e->response->body(), 0, 800);
            }
            $prev = $e->getPrevious();
            if ($prev instanceof RequestException && $prev->response !== null) {
                $httpData['previous_http_status'] = $prev->response->status();
                $httpData['previous_response_body_preview'] = mb_substr($prev->response->body(), 0, 800);
            }
            $this->agentDebugLog('H0', 'AiBuilderController.php:outer_catch', 'ai_build_outer_exception', $httpData);
            // #endregion

            $message = $e->getMessage();

            if (config('app.debug')) {
                $message .= ' in '.$e->getFile().':'.$e->getLine();
            }

            return response()->json(['message' => $message], 500);
        }
    }

    public function status(Request $request): JsonResponse
    {
        $provider = $request->query('provider')
            ?: config('workflow-automation.ai_builder.default_provider', 'openai');

        $envKeyMap = [
            'openai' => 'OPENAI_API_KEY',
            'anthropic' => 'ANTHROPIC_API_KEY',
            'gemini' => 'GEMINI_API_KEY',
            'groq' => 'GROQ_API_KEY',
            'mistral' => 'MISTRAL_API_KEY',
            'deepseek' => 'DEEPSEEK_API_KEY',
            'ollama' => null,
            'xai' => 'XAI_API_KEY',
            'cohere' => 'COHERE_API_KEY',
            'elevenlabs' => 'ELEVENLABS_API_KEY',
            'jina' => 'JINA_API_KEY',
            'voyageai' => 'VOYAGEAI_API_KEY',
        ];

        $key = mb_strtolower($provider);
        $envKey = $envKeyMap[$key] ?? null;

        $hasKey = $envKey === null || ! empty(env($envKey));

        return response()->json([
            'provider' => $provider,
            'has_api_key' => $hasKey,
        ]);
    }

    protected function resolveProvider(string $provider): mixed
    {
        $map = [
            'openai' => \Laravel\Ai\Enums\Lab::OpenAI,
            'anthropic' => \Laravel\Ai\Enums\Lab::Anthropic,
            'gemini' => \Laravel\Ai\Enums\Lab::Gemini,
            'groq' => \Laravel\Ai\Enums\Lab::Groq,
            'mistral' => \Laravel\Ai\Enums\Lab::Mistral,
            'deepseek' => \Laravel\Ai\Enums\Lab::DeepSeek,
            'ollama' => \Laravel\Ai\Enums\Lab::Ollama,
            'xai' => \Laravel\Ai\Enums\Lab::xAI,
            'cohere' => \Laravel\Ai\Enums\Lab::Cohere,
        ];

        $key = mb_strtolower($provider);

        if (! isset($map[$key])) {
            throw new InvalidArgumentException(
                "Unknown AI provider: {$provider}. Supported: ".implode(', ', array_keys($map))
            );
        }

        return $map[$key];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function agentDebugLog(string $hypothesisId, string $location, string $message, array $data = []): void
    {
        $payload = json_encode([
            'sessionId' => '8e0977',
            'timestamp' => (int) (microtime(true) * 1000),
            'hypothesisId' => $hypothesisId,
            'location' => $location,
            'message' => $message,
            'data' => $data,
        ], JSON_UNESCAPED_UNICODE);

        if ($payload === false) {
            return;
        }

        $path = base_path('.cursor/debug-8e0977.log');
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($path, $payload."\n", FILE_APPEND | LOCK_EX);
    }
}
