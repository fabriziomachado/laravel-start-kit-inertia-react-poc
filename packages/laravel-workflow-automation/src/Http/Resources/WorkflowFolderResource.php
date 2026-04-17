<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class WorkflowFolderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $attrs = $this->resource->getAttributes();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'parent_id' => $this->parent_id,
            'children' => self::collection($this->whenLoaded('children')),
            'workflows_count' => $this->when(
                array_key_exists('workflows_count', $attrs),
                fn (): int => (int) $attrs['workflows_count'],
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
