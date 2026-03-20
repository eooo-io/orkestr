<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentPermission extends Model
{
    protected $fillable = [
        'agent_id',
        'permission_type',
        'permission_target',
        'allowed',
    ];

    protected function casts(): array
    {
        return [
            'allowed' => 'boolean',
        ];
    }

    // --- Relationships ---

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    // --- Helpers ---

    public static function validTypes(): array
    {
        return ['tool', 'data_source', 'agent_delegate', 'mcp_server'];
    }
}
