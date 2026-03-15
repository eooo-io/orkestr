<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class GuardrailViolation extends Model
{
    protected $fillable = [
        'uuid',
        'organization_id',
        'project_id',
        'agent_id',
        'execution_run_id',
        'guard_type',
        'severity',
        'rule_name',
        'message',
        'context',
        'action_taken',
        'dismissed_by',
        'dismissed_at',
        'dismissal_reason',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'dismissed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (GuardrailViolation $violation) {
            if (empty($violation->uuid)) {
                $violation->uuid = (string) Str::uuid();
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function dismissedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dismissed_by');
    }

    public function scopeForGuardType($query, string $type)
    {
        return $query->where('guard_type', $type);
    }

    public function scopeForSeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    public function scopeUndismissed($query)
    {
        return $query->whereNull('dismissed_at');
    }
}
