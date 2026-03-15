<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class LicenseKey extends Model
{
    protected $fillable = [
        'uuid',
        'organization_id',
        'key',
        'tier',
        'status',
        'max_users',
        'max_agents',
        'features',
        'licensee_name',
        'licensee_email',
        'activated_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'activated_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (LicenseKey $license) {
            if (empty($license->uuid)) {
                $license->uuid = (string) Str::uuid();
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && ! $this->isExpired();
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isEnterprise(): bool
    {
        return $this->tier === 'enterprise';
    }

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? []);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeForTier(Builder $query, string $tier): Builder
    {
        return $query->where('tier', $tier);
    }
}
