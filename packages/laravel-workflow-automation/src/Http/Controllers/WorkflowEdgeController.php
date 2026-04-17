<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Http\Controllers;

use Aftandilmmd\WorkflowAutomation\Http\Requests\StoreEdgeRequest;
use Aftandilmmd\WorkflowAutomation\Http\Resources\WorkflowEdgeResource;
use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowEdge;
use Aftandilmmd\WorkflowAutomation\Services\WorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

final class WorkflowEdgeController extends Controller
{
    public function __construct(
        private readonly WorkflowService $service,
    ) {}

    public function store(StoreEdgeRequest $request, Workflow $workflow): WorkflowEdgeResource
    {
        $edge = $this->service->connect(
            source: $request->validated('source_node_id'),
            target: $request->validated('target_node_id'),
            sourcePort: $request->validated('source_port', 'main'),
            targetPort: $request->validated('target_port', 'main'),
        );

        return new WorkflowEdgeResource($edge);
    }

    public function destroy(Workflow $workflow, WorkflowEdge $edge): JsonResponse
    {
        $this->service->removeEdge($edge->id);

        return response()->json(['message' => 'Edge deleted.'], 200);
    }
}
