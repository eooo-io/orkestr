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
        'persona',
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

        // Autonomy & Permissions
        'autonomy_level',
        'budget_limit_usd',
        'daily_budget_limit_usd',
        'run_token_budget',
        'run_cost_budget_usd',
        'allowed_tools',
        'blocked_tools',
        'data_access_scope',
        'document_access',
        'knowledge_access',

        // Notifications
        'notify_on_success',
        'notify_on_failure',

        // Memory
        'memory_enabled',
        'auto_remember',
        'memory_recall_limit',

        // Meta
        'is_template',
        'created_by',
        'owner_user_id',
        'reputation_score',
        'reputation_last_computed_at',
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
            'memory_enabled' => 'boolean',
            'auto_remember' => 'boolean',
            'memory_recall_limit' => 'integer',
            'notify_on_success' => 'boolean',
            'notify_on_failure' => 'boolean',

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
            'autonomy_level' => 'string',
            'budget_limit_usd' => 'decimal:4',
            'daily_budget_limit_usd' => 'decimal:4',
            'run_token_budget' => 'integer',
            'run_cost_budget_usd' => 'decimal:4',
            'reputation_score' => 'decimal:2',
            'reputation_last_computed_at' => 'datetime',
            'allowed_tools' => 'array',
            'blocked_tools' => 'array',
            'data_access_scope' => 'array',
            'document_access' => 'boolean',
            'knowledge_access' => 'boolean',
            'persona' => 'array',
        ];
    }

    protected $attributes = [
        'autonomy_level' => 'semi_autonomous',
        'base_instructions' => '',
    ];

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
            if ($agent->autonomy_level === null) {
                $agent->autonomy_level = 'semi_autonomous';
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

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
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

    public function schedules(): HasMany
    {
        return $this->hasMany(AgentSchedule::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AgentAuditLog::class);
    }

    public function executionRuns(): HasMany
    {
        return $this->hasMany(ExecutionRun::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(AgentTask::class);
    }

    public function dataSources(): BelongsToMany
    {
        return $this->belongsToMany(DataSource::class, 'agent_data_source')
            ->withPivot('project_id', 'access_mode')
            ->withTimestamps();
    }

    public function identities(): HasMany
    {
        return $this->hasMany(AgentIdentity::class);
    }

    public function resourceQuotas(): HasMany
    {
        return $this->hasMany(AgentResourceQuota::class);
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(AgentPermission::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(AgentVersion::class);
    }

    public function healthChecks(): HasMany
    {
        return $this->hasMany(AgentHealthCheck::class);
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

    // --- Persona Helpers ---

    public function personaName(): ?string
    {
        return $this->persona['name'] ?? null;
    }

    public function personaAvatar(): ?string
    {
        return $this->persona['avatar'] ?? null;
    }

    public function personaAliases(): array
    {
        return $this->persona['aliases'] ?? [];
    }

    public function personaPersonality(): ?string
    {
        return $this->persona['personality'] ?? null;
    }

    public function personaBio(): ?string
    {
        return $this->persona['bio'] ?? null;
    }

    /**
     * Get the display name — persona name if set, otherwise agent name.
     */
    public function displayName(): string
    {
        return $this->personaName() ?: $this->name;
    }

    /**
     * Build the persona context to prepend to the system prompt.
     */
    public function personaContext(): ?string
    {
        $parts = [];

        if ($name = $this->personaName()) {
            $parts[] = "You are {$name}.";
        }

        if ($bio = $this->personaBio()) {
            $parts[] = $bio;
        }

        if ($personality = $this->personaPersonality()) {
            $parts[] = "Communicate in a {$personality} style.";
        }

        $aliases = $this->personaAliases();
        if (! empty($aliases)) {
            $parts[] = 'You also respond to: ' . implode(', ', $aliases) . '.';
        }

        return ! empty($parts) ? implode(' ', $parts) : null;
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
