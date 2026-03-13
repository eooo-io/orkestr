<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkflowEdgeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workflow_id' => $this->workflow_id,
            'source_step_id' => $this->source_step_id,
            'target_step_id' => $this->target_step_id,
            'condition_expression' => $this->condition_expression,
            'label' => $this->label,
            'priority' => $this->priority,
            'has_condition' => $this->hasCondition(),

            // Relationships (when loaded)
            'source_step' => new WorkflowStepResource($this->whenLoaded('sourceStep')),
            'target_step' => new WorkflowStepResource($this->whenLoaded('targetStep')),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
