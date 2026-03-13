<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkflowRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'workflow_id' => $this->workflow_id,
            'project_id' => $this->project_id,
            'status' => $this->status,
            'input' => $this->input,
            'context_snapshot' => $this->context_snapshot,
            'current_step_id' => $this->current_step_id,
            'error' => $this->error,
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'run_steps' => $this->whenLoaded('runSteps', fn () => $this->runSteps->map(fn ($rs) => [
                'id' => $rs->id,
                'uuid' => $rs->uuid,
                'workflow_step_id' => $rs->workflow_step_id,
                'execution_run_id' => $rs->execution_run_id,
                'status' => $rs->status,
                'input' => $rs->input,
                'output' => $rs->output,
                'started_at' => $rs->started_at?->toIso8601String(),
                'completed_at' => $rs->completed_at?->toIso8601String(),
            ])),
            'run_steps_count' => $this->whenCounted('runSteps'),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
