<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Mcp\Tools;

use Aftandilmmd\WorkflowAutomation\Services\WorkflowService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Name('rate_limit_status')]
#[Title('Rate Limit Status')]
#[Description('Get the current concurrency / rate-limit status for a workflow. Shows global and per-workflow limits, active runs, and whether the workflow can run right now.')]
final class RateLimitStatusTool extends Tool
{
    public function __construct(
        protected WorkflowService $service,
    ) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'workflow_id' => $schema->integer()->required()->description('The workflow ID'),
        ];
    }

    public function handle(Request $request): Response
    {
        $status = $this->service->rateLimitStatus($request->get('workflow_id'));

        return Response::json($status);
    }
}
