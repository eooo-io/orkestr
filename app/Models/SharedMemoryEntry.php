<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SharedMemoryEntry extends Model
{
    protected $table = 'shared_memory_entries';

    protected $fillable = [
        'uuid',
        'pool_id',
        'contributed_by_agent_id',
        'key',
        'content',
        'embedding',
        'tags',
        'confidence',
        'metadata',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'tags' => 'array',
            'metadata' => 'array',
            'embedding' => 'array',
            'confidence' => 'decimal:2',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SharedMemoryEntry $entry) {
            if (empty($entry->uuid)) {
                $entry->uuid = (string) Str::uuid();
            }
        });
    }

    // --- Relationships ---

    public function pool(): BelongsTo
    {
        return $this->belongsTo(SharedMemoryPool::class, 'pool_id');
    }

    public function contributor(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'contributed_by_agent_id');
    }

    // --- Scopes ---

    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }

    public function scopeForPool($query, int $poolId)
    {
        return $query->where('pool_id', $poolId);
    }

    // --- Helpers ---

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
