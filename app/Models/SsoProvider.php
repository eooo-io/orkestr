<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SsoProvider extends Model
{
    protected $fillable = [
        'uuid',
        'organization_id',
        'type',
        'name',
        'entity_id',
        'metadata_url',
        'sso_url',
        'slo_url',
        'certificate',
        'client_id',
        'client_secret',
        'claim_mapping',
        'allowed_domains',
        'auto_provision',
        'default_role',
        'is_active',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'claim_mapping' => 'array',
            'allowed_domains' => 'array',
            'auto_provision' => 'boolean',
            'is_active' => 'boolean',
            'client_secret' => 'encrypted',
            'last_used_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SsoProvider $provider) {
            if (empty($provider->uuid)) {
                $provider->uuid = (string) Str::uuid();
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForOrganization($query, int $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    public function isSaml(): bool
    {
        return $this->type === 'saml';
    }

    public function isOidc(): bool
    {
        return $this->type === 'oidc';
    }

    /**
     * Get the default claim mapping for this provider type.
     */
    public function getEffectiveClaimMapping(): array
    {
        $defaults = match ($this->type) {
            'saml' => [
                'email' => 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress',
                'name' => 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/name',
                'first_name' => 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/givenname',
                'last_name' => 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/surname',
            ],
            'oidc' => [
                'email' => 'email',
                'name' => 'name',
                'first_name' => 'given_name',
                'last_name' => 'family_name',
            ],
            default => [],
        };

        return array_merge($defaults, $this->claim_mapping ?? []);
    }

    /**
     * Check if an email domain is allowed.
     */
    public function isDomainAllowed(string $email): bool
    {
        $domains = $this->allowed_domains;
        if (empty($domains)) {
            return true; // No restrictions
        }

        $emailDomain = strtolower(substr($email, strrpos($email, '@') + 1));

        return in_array($emailDomain, array_map('strtolower', $domains));
    }

    /**
     * Get the callback URL for this SSO provider.
     */
    public function callbackUrl(): string
    {
        $base = rtrim(config('app.url'), '/');

        return match ($this->type) {
            'saml' => "{$base}/auth/saml/{$this->uuid}/acs",
            'oidc' => "{$base}/auth/oidc/{$this->uuid}/callback",
            default => '',
        };
    }
}
