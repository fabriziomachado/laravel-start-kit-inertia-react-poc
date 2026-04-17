<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Http\Controllers;

use Aftandilmmd\WorkflowAutomation\Http\Resources\WorkflowRunResource;
use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowRun;
use Aftandilmmd\WorkflowAutomation\Services\WorkflowService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

final class WorkflowRunController extends Controller
{
    public function __construct(
        private readonly WorkflowService $service,
    ) {}

    public function index(Request $request, Workflow $workflow): AnonymousResourceCollection
    {
        $runs = $workflow->runs()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return WorkflowRunResource::collection($runs);
    }

    public function show(WorkflowRun $run): WorkflowRunResource
    {
        $run->load('nodeRuns');

        return new WorkflowRunResource($run);
    }

    public function cancel(WorkflowRun $run): WorkflowRunResource
    {
        $run = $this->service->cancel($run);

        return new WorkflowRunResource($run);
    }

    public function resume(Request $request, WorkflowRun $run): WorkflowRunResource
    {
        $request->validate([
            'resume_token' => ['required', 'string'],
            'payload' => ['nullable', 'array'],
        ]);

        $run = $this->service->resume(
            run: $run,
            resumeToken: $request->input('resume_token'),
            payload: $request->input('payload', []),
        );

        return new WorkflowRunResource($run->load('nodeRuns'));
    }

    public function replay(WorkflowRun $run): WorkflowRunResource
    {
        $newRun = $this->service->replay($run);

        return new WorkflowRunResource($newRun->load('nodeRuns'));
    }

    public function retryFromFailure(WorkflowRun $run): WorkflowRunResource
    {
        $newRun = $this->service->retryFromFailure($run);

        return new WorkflowRunResource($newRun->load('nodeRuns'));
    }

    public function retryNode(Request $request, WorkflowRun $run): WorkflowRunResource
    {
        $request->validate([
            'node_id' => ['required', 'integer'],
        ]);

        $newRun = $this->service->retryNode($run, $request->integer('node_id'));

        return new WorkflowRunResource($newRun->load('nodeRuns'));
    }

    public function testNode(Request $request, Workflow $workflow): WorkflowRunResource
    {
        $request->validate([
            'node_id' => ['required', 'integer'],
            'payload' => ['nullable', 'array'],
        ]);

        $run = $this->service->testNode(
            $workflow,
            $request->integer('node_id'),
            $request->input('payload', []),
        );

        return new WorkflowRunResource($run->load('nodeRuns'));
    }
}
