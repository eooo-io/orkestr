<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Organization extends Model
{
    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'description',
        'plan',
        'trial_ends_at',
        'subscription_ends_at',
        'plan_limits',
    ];

    protected function casts(): array
    {
        return [
            'plan_limits' => 'array',
            'trial_ends_at' => 'datetime',
            'subscription_ends_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Organization $org) {
            if (empty($org->uuid)) {
                $org->uuid = (string) Str::uuid();
            }
            if (empty($org->slug)) {
                $org->slug = Str::slug($org->name);
            }
        });
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role', 'accepted_at')
            ->withTimestamps();
    }

    public function owner(): ?User
    {
        return $this->users()->wherePivot('role', 'owner')->first();
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }

    public function isOnPlan(string $plan): bool
    {
        return $this->plan === $plan;
    }

    public function isOnPaidPlan(): bool
    {
        return in_array($this->plan, ['pro', 'teams']);
    }

    public function hasActiveSubscription(): bool
    {
        if ($this->plan === 'free') {
            return true;
        }

        if ($this->trial_ends_at && $this->trial_ends_at->isFuture()) {
            return true;
        }

        return $this->subscription_ends_at && $this->subscription_ends_at->isFuture();
    }

    public function planLimit(string $key, int $default = 0): int
    {
        return $this->plan_limits[$key] ?? $default;
    }
}
