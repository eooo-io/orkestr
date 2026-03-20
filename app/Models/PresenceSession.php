<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PresenceSession extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'uuid',
        'user_id',
        'organization_id',
        'resource_type',
        'resource_id',
        'cursor_position',
        'selection',
        'color',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'cursor_position' => 'array',
            'selection' => 'array',
            'last_seen_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PresenceSession $session) {
            if (empty($session->uuid)) {
                $session->uuid = (string) Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Check if this presence session is stale (last seen > 15 seconds ago).
     */
    public function isStale(): bool
    {
        return $this->last_seen_at->diffInSeconds(now()) > 15;
    }
}
