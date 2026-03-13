<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExecutionStepResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'step_number' => $this->step_number,
            'phase' => $this->phase,
            'input' => $this->input,
            'output' => $this->output,
            'tool_calls' => $this->tool_calls,
            'token_usage' => $this->token_usage,
            'duration_ms' => $this->duration_ms,
            'status' => $this->status,
            'error' => $this->error,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
