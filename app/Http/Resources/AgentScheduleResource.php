<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AgentScheduleResource extends JsonResource
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
            'name' => $this->name,
            'trigger_type' => $this->trigger_type,
            'cron_expression' => $this->cron_expression,
            'timezone' => $this->timezone,
            'webhook_token' => $this->webhook_token,
            'webhook_secret' => $this->when($this->webhook_secret !== null, '********'),
            'webhook_url' => $this->when(
                $this->trigger_type === 'webhook' && $this->webhook_token,
                fn () => url("/api/webhooks/schedule/{$this->webhook_token}")
            ),
            'event_name' => $this->event_name,
            'event_filters' => $this->event_filters,
            'input_template' => $this->input_template,
            'execution_config' => $this->execution_config,
            'is_enabled' => $this->is_enabled,
            'last_run_at' => $this->last_run_at?->toIso8601String(),
            'next_run_at' => $this->next_run_at?->toIso8601String(),
            'run_count' => $this->run_count,
            'failure_count' => $this->failure_count,
            'max_retries' => $this->max_retries,
            'last_error' => $this->last_error,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
