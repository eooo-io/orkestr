<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentHealthCheck extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'agent_id',
        'project_id',
        'check_type',
        'status',
        'details',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'checked_at' => 'datetime',
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

    // --- Helpers ---

    public static function validCheckTypes(): array
    {
        return ['mcp_connectivity', 'skill_validity', 'model_availability', 'credential_access'];
    }

    public static function validStatuses(): array
    {
        return ['passed', 'failed', 'warning'];
    }
}
