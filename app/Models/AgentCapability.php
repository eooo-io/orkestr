<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentCapability extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'agent_id',
        'project_id',
        'capability',
        'proficiency',
        'avg_duration_ms',
        'avg_cost_microcents',
        'success_rate',
        'task_count',
        'last_used_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'proficiency' => 'decimal:2',
            'success_rate' => 'decimal:2',
            'avg_duration_ms' => 'integer',
            'avg_cost_microcents' => 'integer',
            'task_count' => 'integer',
            'last_used_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    // --- Relationships ---

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    // --- Scopes ---

    public function scopeForCapability($query, string $name)
    {
        return $query->where('capability', $name);
    }
}
