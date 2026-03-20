<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentVersion extends Model
{
    protected $fillable = [
        'agent_id',
        'version_number',
        'config_snapshot',
        'skill_snapshot',
        'mcp_snapshot',
        'a2a_snapshot',
        'created_by',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'config_snapshot' => 'array',
            'skill_snapshot' => 'array',
            'mcp_snapshot' => 'array',
            'a2a_snapshot' => 'array',
        ];
    }

    // --- Relationships ---

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
