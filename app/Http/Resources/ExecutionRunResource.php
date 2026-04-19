<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExecutionRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'project_id' => $this->project_id,
            'agent_id' => $this->agent_id,
            'agent' => $this->whenLoaded('agent', fn () => [
                'id' => $this->agent->id,
                'name' => $this->agent->name,
                'slug' => $this->agent->slug,
                'icon' => $this->agent->icon,
            ]),
            'status' => $this->status,
            'input' => $this->input,
            'output' => $this->output,
            'config' => $this->config,
            'error' => $this->error,
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'total_tokens' => $this->total_tokens,
            'total_cost_microcents' => $this->total_cost_microcents,
            'total_duration_ms' => $this->total_duration_ms,
            'token_budget' => $this->token_budget,
            'cost_budget_microcents' => $this->cost_budget_microcents,
            'halt_reason' => $this->halt_reason,
            'halt_step_id' => $this->halt_step_id,
            'steps' => ExecutionStepResource::collection($this->whenLoaded('steps')),
            'steps_count' => $this->whenCounted('steps'),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
