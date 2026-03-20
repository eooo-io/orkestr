<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class VaultSecret extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'name',
        'slug',
        'description',
        'type',
        'encrypted_value',
        'metadata',
        'rotated_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'rotated_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (VaultSecret $secret) {
            if (empty($secret->slug)) {
                $secret->slug = Str::slug($secret->name);
            }
        });
    }

    // --- Relationships ---

    public function accessGrants(): HasMany
    {
        return $this->hasMany(VaultAccessGrant::class, 'secret_id');
    }

    public function auditEntries(): HasMany
    {
        return $this->hasMany(VaultAuditEntry::class, 'secret_id');
    }

    // --- Encryption ---

    public function setValueAttribute(string $value): void
    {
        $this->attributes['encrypted_value'] = Crypt::encryptString($value);
    }

    public function getDecryptedValue(): string
    {
        return Crypt::decryptString($this->encrypted_value);
    }

    // --- Scopes ---

    public function scopeExpiringSoon(Builder $query): Builder
    {
        return $query->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays(7))
            ->where('expires_at', '>', now());
    }

    // --- Helpers ---

    public static function validTypes(): array
    {
        return ['api_key', 'oauth_token', 'password', 'certificate', 'ssh_key', 'custom'];
    }
}
