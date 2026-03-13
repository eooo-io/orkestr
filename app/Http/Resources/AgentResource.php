<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AgentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'role' => $this->role,
            'description' => $this->description,
            'icon' => $this->icon,
            'sort_order' => $this->sort_order,

            // Identity
            'base_instructions' => $this->base_instructions,
            'persona_prompt' => $this->persona_prompt,
            'model' => $this->model,

            // Goal
            'objective_template' => $this->objective_template,
            'success_criteria' => $this->success_criteria,
            'max_iterations' => $this->max_iterations,
            'timeout_seconds' => $this->timeout_seconds,

            // Perception
            'input_schema' => $this->input_schema,
            'memory_sources' => $this->memory_sources,
            'context_strategy' => $this->context_strategy,

            // Reasoning
            'planning_mode' => $this->planning_mode,
            'temperature' => $this->temperature,
            'system_prompt' => $this->system_prompt,

            // Observation
            'eval_criteria' => $this->eval_criteria,
            'output_schema' => $this->output_schema,
            'loop_condition' => $this->loop_condition,

            // Orchestration
            'parent_agent_id' => $this->parent_agent_id,
            'delegation_rules' => $this->delegation_rules,
            'can_delegate' => $this->can_delegate,

            // Actions
            'custom_tools' => $this->custom_tools,

            // Meta
            'is_template' => $this->is_template,
            'created_by' => $this->created_by,
            'has_loop_config' => $this->hasLoopConfig(),

            // Relationships (when loaded)
            'parent_agent' => new AgentResource($this->whenLoaded('parentAgent')),
            'child_agents' => AgentResource::collection($this->whenLoaded('childAgents')),
            'mcp_servers' => $this->whenLoaded('mcpServers'),
            'a2a_agents' => $this->whenLoaded('a2aAgents'),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
