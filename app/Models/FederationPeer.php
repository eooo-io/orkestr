<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class FederationPeer extends Model
{
    protected $fillable = [
        'organization_id',
        'name',
        'base_url',
        'api_key_hash',
        'status',
        'capabilities',
        'last_heartbeat_at',
        'last_sync_at',
        'trust_level',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'capabilities' => 'array',
            'metadata' => 'array',
            'last_heartbeat_at' => 'datetime',
            'last_sync_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (FederationPeer $peer) {
            if (empty($peer->uuid)) {
                $peer->uuid = (string) Str::uuid();
            }
        });
    }

    // --- Relationships ---

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function delegations(): HasMany
    {
        return $this->hasMany(FederationDelegation::class, 'peer_id');
    }

    public function federatedIdentities(): HasMany
    {
        return $this->hasMany(FederatedIdentity::class, 'peer_id');
    }

    // --- Scopes ---

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
