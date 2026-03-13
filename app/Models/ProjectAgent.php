<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProjectAgent extends Model
{
    protected $table = 'project_agent';

    protected $fillable = [
        'project_id',
        'agent_id',
        'custom_instructions',
        'is_enabled',

        // Override columns
        'objective_override',
        'success_criteria_override',
        'max_iterations_override',
        'timeout_override',
        'model_override',
        'temperature_override',
        'context_strategy_override',
        'planning_mode_override',
        'custom_tools_override',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'max_iterations_override' => 'integer',
            'timeout_override' => 'integer',
            'temperature_override' => 'decimal:2',
            'success_criteria_override' => 'array',
            'custom_tools_override' => 'array',
        ];
    }

    /**
     * Resolve an agent field with project-level override taking precedence.
     */
    public function resolve(string $field): mixed
    {
        $override = $this->getAttribute("{$field}_override");

        return $override ?? $this->agent->getAttribute($field);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'agent_skill', 'agent_id', 'skill_id', 'agent_id', 'id')
            ->wherePivot('project_id', $this->project_id);
    }
}
