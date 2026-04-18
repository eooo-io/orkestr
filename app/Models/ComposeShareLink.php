<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ComposeShareLink extends Model
{
    protected $fillable = [
        'uuid',
        'project_id',
        'agent_id',
        'model',
        'depth',
        'created_by',
        'expires_at',
        'last_accessed_at',
        'access_count',
        'is_snapshot',
        'snapshot_payload',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_accessed_at' => 'datetime',
            'is_snapshot' => 'boolean',
            'snapshot_payload' => 'array',
            'access_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ComposeShareLink $link) {
            if (empty($link->uuid)) {
                $link->uuid = (string) Str::uuid();
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
