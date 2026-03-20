<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoutingDecision extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'task_id',
        'selected_agent_id',
        'strategy_used',
        'candidates',
        'reasoning',
        'sla_met',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'candidates' => 'array',
            'sla_met' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    // --- Relationships ---

    public function task(): BelongsTo
    {
        return $this->belongsTo(AgentTask::class, 'task_id');
    }

    public function selectedAgent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'selected_agent_id');
    }
}
