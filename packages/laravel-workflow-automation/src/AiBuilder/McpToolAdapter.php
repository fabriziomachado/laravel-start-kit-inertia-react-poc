<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\AiBuilder;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Tool as AiTool;
use Laravel\Ai\Tools\Request as AiRequest;
use Laravel\Mcp\Request as McpRequest;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool as McpTool;
use Stringable;
use Throwable;

abstract class McpToolAdapter implements AiTool
{
    public function __construct(
        protected McpTool $mcpTool,
    ) {}

    public function name(): string
    {
        return $this->mcpTool->name();
    }

    public function description(): string
    {
        return $this->mcpTool->description();
    }

    public function schema(JsonSchema $schema): array
    {
        return $this->mcpTool->schema($schema);
    }

    public function handle(AiRequest $request): string
    {
        $toolName = $this->name();
        $args = $request->all();

        Log::debug("[AiBuilder] Calling tool: {$toolName}", ['args' => $args]);

        try {
            $mcpRequest = new McpRequest($args);
            $result = $this->mcpTool->handle($mcpRequest);
            $output = $this->resultToString($result);

            Log::debug("[AiBuilder] Tool result: {$toolName}", ['output' => mb_substr($output, 0, 500)]);

            return $output;
        } catch (Throwable $e) {
            $error = "Tool {$toolName} failed: {$e->getMessage()}";
            Log::error("[AiBuilder] {$error}", ['exception' => $e]);

            return json_encode(['error' => $error]);
        }
    }

    private function resultToString(mixed $result): string
    {
        // Response::structured() returns ResponseFactory
        if ($result instanceof ResponseFactory) {
            $structured = $result->getStructuredContent();
            if ($structured) {
                return json_encode($structured, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }

            return $result->responses()
                ->map(fn (Response $r) => (string) $r->content())
                ->implode("\n");
        }

        // Response::text() / Response::error() returns Response
        if ($result instanceof Response) {
            return (string) $result->content();
        }

        if ($result instanceof Stringable) {
            return (string) $result;
        }

        return is_string($result) ? $result : json_encode($result);
    }
}
