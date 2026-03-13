<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkflowStepResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'workflow_id' => $this->workflow_id,
            'agent_id' => $this->agent_id,
            'type' => $this->type,
            'name' => $this->name,
            'position_x' => $this->position_x,
            'position_y' => $this->position_y,
            'config' => $this->config,
            'sort_order' => $this->sort_order,

            // Helpers
            'is_agent' => $this->isAgent(),
            'is_checkpoint' => $this->isCheckpoint(),
            'is_condition' => $this->isCondition(),
            'is_terminal' => $this->isTerminal(),
            'requires_agent' => $this->requiresAgent(),

            // Relationships (when loaded)
            'agent' => new AgentResource($this->whenLoaded('agent')),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
