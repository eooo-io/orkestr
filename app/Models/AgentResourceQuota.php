<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentResourceQuota extends Model
{
    protected $fillable = [
        'agent_id',
        'project_id',
        'max_tokens_per_day',
        'max_cost_per_day',
        'max_concurrent_executions',
        'max_execution_duration_seconds',
        'max_mcp_connections',
        'allowed_domains',
    ];

    protected function casts(): array
    {
        return [
            'allowed_domains' => 'array',
            'max_cost_per_day' => 'decimal:4',
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
}
