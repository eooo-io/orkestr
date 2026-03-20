<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AgentIdentity extends Model
{
    protected $fillable = [
        'agent_id',
        'name',
        'token_hash',
        'scopes',
        'rate_limit_per_minute',
        'rate_limit_per_hour',
        'allowed_ips',
        'expires_at',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'allowed_ips' => 'array',
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    // --- Relationships ---

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    // --- Token Generation ---

    /**
     * Generate a new token pair.
     *
     * @return array{0: string, 1: string} [plain_token, hashed_token]
     */
    public static function generateToken(): array
    {
        $plainToken = Str::random(64);
        $hashedToken = hash('sha256', $plainToken);

        return [$plainToken, $hashedToken];
    }

    // --- Helpers ---

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    // --- Scopes ---

    public function scopeActive(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }
}
