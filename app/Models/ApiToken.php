<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ApiToken extends Model
{
    protected $fillable = [
        'user_id',
        'organization_id',
        'name',
        'token',
        'abilities',
        'last_used_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'abilities' => 'array',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Generate a new API token.
     */
    public static function createToken(User $user, string $name, array $abilities = ['*'], ?Organization $org = null, ?\DateTimeInterface $expiresAt = null): array
    {
        $plainToken = Str::random(48);

        $token = static::create([
            'user_id' => $user->id,
            'organization_id' => $org?->id,
            'name' => $name,
            'token' => hash('sha256', $plainToken),
            'abilities' => $abilities,
            'expires_at' => $expiresAt,
        ]);

        return [
            'token' => $token,
            'plain_token' => $plainToken,
        ];
    }

    /**
     * Find a token by its plain-text value.
     */
    public static function findByPlainToken(string $plainToken): ?static
    {
        return static::where('token', hash('sha256', $plainToken))->first();
    }

    /**
     * Check if the token has a specific ability.
     */
    public function hasAbility(string $ability): bool
    {
        $abilities = $this->abilities ?? ['*'];

        if (in_array('*', $abilities)) {
            return true;
        }

        // Check exact match or wildcard (e.g. "skills:*" matches "skills:read")
        foreach ($abilities as $a) {
            if ($a === $ability) {
                return true;
            }
            if (str_ends_with($a, ':*')) {
                $prefix = substr($a, 0, -1);
                if (str_starts_with($ability, $prefix)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if the token is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Mark the token as used.
     */
    public function markUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }
}
