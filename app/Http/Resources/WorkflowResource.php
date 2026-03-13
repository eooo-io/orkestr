<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkflowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'project_id' => $this->project_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'trigger_type' => $this->trigger_type,
            'trigger_config' => $this->trigger_config,
            'entry_step_id' => $this->entry_step_id,
            'status' => $this->status,
            'context_schema' => $this->context_schema,
            'termination_policy' => $this->termination_policy,
            'config' => $this->config,
            'created_by' => $this->created_by,

            'is_active' => $this->isActive(),
            'is_draft' => $this->isDraft(),

            // Counts
            'step_count' => $this->whenCounted('steps', $this->steps_count ?? null),
            'edge_count' => $this->whenCounted('edges', $this->edges_count ?? null),

            // Relationships (when loaded)
            'steps' => WorkflowStepResource::collection($this->whenLoaded('steps')),
            'edges' => WorkflowEdgeResource::collection($this->whenLoaded('edges')),
            'entry_step' => new WorkflowStepResource($this->whenLoaded('entryStep')),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
