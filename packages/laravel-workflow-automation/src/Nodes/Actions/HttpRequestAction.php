<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Nodes\Actions;

use Aftandilmmd\WorkflowAutomation\Attributes\AsWorkflowNode;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeInput;
use Aftandilmmd\WorkflowAutomation\DTOs\NodeOutput;
use Aftandilmmd\WorkflowAutomation\Enums\NodeType;
use Aftandilmmd\WorkflowAutomation\Nodes\BaseNode;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Throwable;

#[AsWorkflowNode(key: 'http_request', type: NodeType::Action, label: 'HTTP Request')]
final class HttpRequestAction extends BaseNode
{
    public static function configSchema(): array
    {
        return [
            ['key' => 'credential_id', 'type' => 'credential', 'label' => 'Authentication', 'required' => false, 'credential_types' => ['bearer_token', 'basic_auth', 'header_auth', 'api_key']],
            ['key' => 'url', 'type' => 'string', 'label' => 'URL', 'required' => true, 'supports_expression' => true],
            ['key' => 'method', 'type' => 'select', 'label' => 'Method', 'required' => true, 'options' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE']],
            ['key' => 'headers', 'type' => 'keyvalue', 'label' => 'Headers', 'required' => false, 'supports_expression' => true],
            ['key' => 'body', 'type' => 'json', 'label' => 'Body (JSON)', 'required' => false, 'supports_expression' => true],
            ['key' => 'timeout', 'type' => 'integer', 'label' => 'Timeout (seconds)', 'required' => false],
            ['key' => 'include_response', 'type' => 'boolean', 'label' => 'Include response in output', 'required' => false],
        ];
    }

    public static function outputSchema(): array
    {
        return [
            'main' => [
                ['key' => 'http_response.status', 'type' => 'integer', 'label' => 'HTTP Status Code'],
                ['key' => 'http_response.body', 'type' => 'mixed', 'label' => 'Response Body'],
                ['key' => 'http_response.headers', 'type' => 'object', 'label' => 'Response Headers'],
            ],
        ];
    }

    public function execute(NodeInput $input, array $config): NodeOutput
    {
        $results = [];

        foreach ($input->items as $item) {
            try {
                $http = Http::timeout($config['timeout'] ?? 30);

                $headers = $config['headers'] ?? [];

                if (! empty($config['_credential'])) {
                    $headers = array_merge($headers, $this->resolveCredentialHeaders($config['_credential']));
                }

                if (! empty($headers)) {
                    $http = $http->withHeaders($headers);
                }

                $response = match (mb_strtoupper($config['method'])) {
                    'GET' => $http->get($config['url']),
                    'POST' => $http->post($config['url'], $config['body'] ?? []),
                    'PUT' => $http->put($config['url'], $config['body'] ?? []),
                    'PATCH' => $http->patch($config['url'], $config['body'] ?? []),
                    'DELETE' => $http->delete($config['url']),
                    default => throw new InvalidArgumentException("Unsupported HTTP method: {$config['method']}"),
                };

                $resultItem = $item;

                if ($config['include_response'] ?? false) {
                    $resultItem['http_response'] = [
                        'status' => $response->status(),
                        'body' => $response->json() ?? $response->body(),
                        'headers' => $response->headers(),
                    ];
                }

                $results[] = $resultItem;
            } catch (Throwable $e) {
                return NodeOutput::ports([
                    'main' => $results,
                    'error' => [array_merge($item, ['error' => $e->getMessage()])],
                ]);
            }
        }

        return NodeOutput::main($results);
    }

    private function resolveCredentialHeaders(array $credential): array
    {
        if (isset($credential['token'])) {
            return ['Authorization' => 'Bearer '.$credential['token']];
        }

        if (isset($credential['username'], $credential['password'])) {
            return ['Authorization' => 'Basic '.base64_encode($credential['username'].':'.$credential['password'])];
        }

        if (isset($credential['header_name'], $credential['header_value'])) {
            return [$credential['header_name'] => $credential['header_value']];
        }

        if (isset($credential['api_key'])) {
            return ['Authorization' => 'Bearer '.$credential['api_key']];
        }

        return [];
    }
}
