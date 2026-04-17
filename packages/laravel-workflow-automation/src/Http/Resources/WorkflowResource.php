<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class WorkflowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'run_async' => $this->run_async,
            'settings' => $this->settings,
            'created_via' => $this->created_via?->value,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'folder_id' => $this->folder_id,
            'folder' => new WorkflowFolderResource($this->whenLoaded('folder')),
            'tags' => WorkflowTagResource::collection($this->whenLoaded('tags')),
            'nodes' => WorkflowNodeResource::collection($this->whenLoaded('nodes')),
            'edges' => WorkflowEdgeResource::collection($this->whenLoaded('edges')),
        ];
    }
}
