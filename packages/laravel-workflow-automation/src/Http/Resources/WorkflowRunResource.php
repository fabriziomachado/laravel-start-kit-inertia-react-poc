<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class WorkflowRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workflow_id' => $this->workflow_id,
            'status' => $this->status?->value,
            'trigger_node_id' => $this->trigger_node_id,
            'initial_payload' => $this->initial_payload,
            'error_message' => $this->error_message,
            'duration_ms' => $this->started_at && $this->finished_at
                ? (int) $this->started_at->diffInMilliseconds($this->finished_at)
                : null,
            'started_at' => $this->started_at?->toISOString(),
            'finished_at' => $this->finished_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'node_runs' => WorkflowNodeRunResource::collection($this->whenLoaded('nodeRuns')),
        ];
    }
}
