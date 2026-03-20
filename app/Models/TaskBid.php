<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TaskBid extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'uuid',
        'task_id',
        'agent_id',
        'project_id',
        'bid_score',
        'estimated_duration_ms',
        'estimated_cost_microcents',
        'confidence',
        'reasoning',
        'status',
        'expires_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'bid_score' => 'decimal:2',
            'confidence' => 'decimal:2',
            'estimated_duration_ms' => 'integer',
            'estimated_cost_microcents' => 'integer',
            'expires_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    protected $attributes = [
        'status' => 'pending',
    ];

    protected static function booted(): void
    {
        static::creating(function (TaskBid $bid) {
            if (empty($bid->uuid)) {
                $bid->uuid = (string) Str::uuid();
            }
            if (empty($bid->created_at)) {
                $bid->created_at = now();
            }
        });
    }

    // --- Relationships ---

    public function task(): BelongsTo
    {
        return $this->belongsTo(AgentTask::class, 'task_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    // --- Scopes ---

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'pending')
            ->where('expires_at', '<', now());
    }
}
