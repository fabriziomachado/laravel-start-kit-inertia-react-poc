<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Http\Controllers;

use Aftandilmmd\WorkflowAutomation\Jobs\ExecuteWorkflowJob;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowNode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class WebhookController extends Controller
{
    public function handle(Request $request, string $uuid): JsonResponse
    {
        $node = WorkflowNode::query()
            ->where('node_key', 'webhook')
            ->whereJsonContains('config->path', $uuid)
            ->whereHas('workflow', fn ($q) => $q->where('is_active', true))
            ->first();

        if (! $node) {
            return response()->json(['message' => 'Webhook not found.'], 404);
        }

        $config = $node->config ?? [];
        $allowedMethod = mb_strtoupper($config['method'] ?? 'POST');

        if ($request->method() !== $allowedMethod) {
            return response()->json(['message' => 'Method not allowed.'], 405);
        }

        if (! $this->authenticate($request, $config)) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        ExecuteWorkflowJob::dispatch(
            workflowId: $node->workflow_id,
            payload: [$request->all()],
            triggerNodeId: $node->id,
        )->onQueue(config('workflow-automation.queue', 'default'));

        return response()->json(['message' => 'Webhook received.'], 202);
    }

    private function authenticate(Request $request, array $config): bool
    {
        $authType = $config['auth_type'] ?? 'none';
        $authValue = $config['auth_value'] ?? '';

        return match ($authType) {
            'none' => true,
            'bearer' => $request->bearerToken() === $authValue,
            'basic' => $request->header('Authorization') === 'Basic '.base64_encode($authValue),
            'header_key' => $request->header('X-Webhook-Key') === $authValue,
            default => false,
        };
    }
}
