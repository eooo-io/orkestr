<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Agent extends Model
{
    protected $fillable = [
        // Identity
        'uuid',
        'name',
        'slug',
        'role',
        'description',
        'base_instructions',
        'persona_prompt',
        'model',
        'fallback_models',
        'routing_strategy',
        'icon',
        'sort_order',

        // Goal
        'objective_template',
        'success_criteria',
        'max_iterations',
        'timeout_seconds',

        // Perception
        'input_schema',
        'memory_sources',
        'context_strategy',

        // Reasoning
        'planning_mode',
        'temperature',
        'system_prompt',

        // Observation
        'eval_criteria',
        'output_schema',
        'loop_condition',

        // Orchestration
        'parent_agent_id',
        'delegation_rules',
        'can_delegate',

        // Actions
        'custom_tools',

        // Meta
        'is_template',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'max_iterations' => 'integer',
            'timeout_seconds' => 'integer',
            'temperature' => 'decimal:2',
            'can_delegate' => 'boolean',
            'is_template' => 'boolean',

            // JSON columns
            'success_criteria' => 'array',
            'input_schema' => 'array',
            'memory_sources' => 'array',
            'eval_criteria' => 'array',
            'output_schema' => 'array',
            'delegation_rules' => 'array',
            'custom_tools' => 'array',
            'fallback_models' => 'array',
            'routing_strategy' => 'string',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Agent $agent) {
            if (empty($agent->uuid)) {
                $agent->uuid = (string) Str::uuid();
            }
            if (empty($agent->slug)) {
                $agent->slug = Str::slug($agent->name);
            }
            if ($agent->routing_strategy === null) {
                $agent->routing_strategy = 'default';
            }
        });
    }

    // --- Relationships ---

    public function projectAgents(): HasMany
    {
        return $this->hasMany(ProjectAgent::class);
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_agent')
            ->withPivot('custom_instructions', 'is_enabled')
            ->withTimestamps();
    }

    public function parentAgent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'parent_agent_id');
    }

    public function childAgents(): HasMany
    {
        return $this->hasMany(Agent::class, 'parent_agent_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function mcpServers(): BelongsToMany
    {
        return $this->belongsToMany(ProjectMcpServer::class, 'agent_mcp_server')
            ->withPivot('project_id', 'config_overrides')
            ->withTimestamps();
    }

    public function a2aAgents(): BelongsToMany
    {
        return $this->belongsToMany(ProjectA2aAgent::class, 'agent_a2a_agent')
            ->withPivot('project_id', 'config_overrides')
            ->withTimestamps();
    }

    // --- Scopes ---

    public function scopeTemplates($query)
    {
        return $query->where('is_template', true);
    }

    public function scopeDelegators($query)
    {
        return $query->where('can_delegate', true);
    }

    // --- Helpers ---

    /**
     * Get the effective system prompt (persona_prompt falls back to base_instructions).
     */
    public function getEffectiveSystemPrompt(): string
    {
        return $this->system_prompt
            ?? $this->persona_prompt
            ?? $this->base_instructions
            ?? '';
    }

    /**
     * Check if this agent has loop configuration defined.
     */
    public function hasLoopConfig(): bool
    {
        return $this->objective_template !== null
            || $this->max_iterations !== null
            || ($this->loop_condition !== null && $this->loop_condition !== 'goal_met');
    }
}
