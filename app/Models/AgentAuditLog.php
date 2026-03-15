<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AgentAuditLog extends Model
{
    protected $fillable = [
        'uuid',
        'request_id',
        'organization_id',
        'project_id',
        'agent_id',
        'skill_id',
        'user_id',
        'user_email',
        'event',
        'severity',
        'description',
        'metadata',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AgentAuditLog $log) {
            if (empty($log->uuid)) {
                $log->uuid = (string) Str::uuid();
            }

            if (empty($log->user_email) && ! empty($log->user_id)) {
                $user = Auth::user();
                if ($user && $user->id === $log->user_id) {
                    $log->user_email = $user->email;
                } else {
                    $log->user_email = User::find($log->user_id)?->email;
                }
            }
        });
    }

    // --- Relationships ---

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

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // --- Scopes ---

    public function scopeForEvent($query, string $event)
    {
        return $query->where('event', $event);
    }

    public function scopeForAgent($query, int $agentId)
    {
        return $query->where('agent_id', $agentId);
    }

    public function scopeForSkill($query, int $skillId)
    {
        return $query->where('skill_id', $skillId);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForOrganization($query, int $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeForSeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    public function scopeForRequestId($query, string $requestId)
    {
        return $query->where('request_id', $requestId);
    }

    public function scopeInDateRange($query, ?string $from = null, ?string $to = null)
    {
        if ($from) {
            $query->where('created_at', '>=', $from);
        }
        if ($to) {
            $query->where('created_at', '<=', $to);
        }

        return $query;
    }
}
