<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CapabilityAdvertisement extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'agent_id',
        'project_id',
        'capabilities',
        'availability_status',
        'max_concurrent_tasks',
        'current_load',
        'advertised_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'capabilities' => 'array',
            'max_concurrent_tasks' => 'integer',
            'current_load' => 'integer',
            'advertised_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    protected $attributes = [
        'availability_status' => 'available',
        'max_concurrent_tasks' => 3,
        'current_load' => 0,
    ];

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

    public function scopeActive($query)
    {
        return $query->where('expires_at', '>', now())
            ->where('availability_status', '!=', 'offline');
    }
}
