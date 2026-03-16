<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DelegationConfig extends Model
{
    protected $fillable = [
        'project_id',
        'source_agent_id',
        'target_agent_id',
        'target_a2a_agent_id',
        'trigger_condition',
        'pass_conversation_history',
        'pass_agent_memory',
        'pass_available_tools',
        'custom_context',
        'return_behavior',
    ];

    protected function casts(): array
    {
        return [
            'pass_conversation_history' => 'boolean',
            'pass_agent_memory' => 'boolean',
            'pass_available_tools' => 'boolean',
            'custom_context' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function sourceAgent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'source_agent_id');
    }

    public function targetAgent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'target_agent_id');
    }
}
