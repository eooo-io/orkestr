<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ApprovalGate extends Model
{
    protected $fillable = [
        'uuid',
        'execution_run_id',
        'agent_id',
        'project_id',
        'type',
        'title',
        'description',
        'context',
        'status',
        'requested_at',
        'responded_at',
        'expires_at',
        'responded_by',
        'response_note',
        'auto_approve_after_minutes',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'requested_at' => 'datetime',
            'responded_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ApprovalGate $gate) {
            if (empty($gate->uuid)) {
                $gate->uuid = (string) Str::uuid();
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function executionRun(): BelongsTo
    {
        return $this->belongsTo(ExecutionRun::class);
    }

    public function respondedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', 'pending')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now());
    }
}
