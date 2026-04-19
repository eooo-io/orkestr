<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Laravel\Cashier\Billable;

class Organization extends Model
{
    use Billable;

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'description',
        'plan',
        'trial_ends_at',
        'subscription_ends_at',
        'plan_limits',
        'stripe_id',
        'pm_type',
        'pm_last_four',
        'max_agent_turns_per_run',
        'default_run_token_budget',
        'default_run_cost_budget_usd',
    ];

    protected function casts(): array
    {
        return [
            'plan_limits' => 'array',
            'trial_ends_at' => 'datetime',
            'subscription_ends_at' => 'datetime',
            'max_agent_turns_per_run' => 'integer',
            'default_run_token_budget' => 'integer',
            'default_run_cost_budget_usd' => 'decimal:4',
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

    public function invitations(): HasMany
    {
        return $this->hasMany(OrganizationInvitation::class);
    }

    public function contentPolicies(): HasMany
    {
        return $this->hasMany(ContentPolicy::class);
    }

    public function ssoProviders(): HasMany
    {
        return $this->hasMany(SsoProvider::class);
    }

    public function guardrailPolicies(): HasMany
    {
        return $this->hasMany(GuardrailPolicy::class);
    }

    public function guardrailViolations(): HasMany
    {
        return $this->hasMany(GuardrailViolation::class);
    }

    public function vaultSecrets(): HasMany
    {
        return $this->hasMany(VaultSecret::class);
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

    public function usageRecords(): HasMany
    {
        return $this->hasMany(UsageRecord::class);
    }

    public function currentMonthTokenUsage(): int
    {
        return (int) $this->usageRecords()
            ->where('type', 'llm_tokens')
            ->where('recorded_at', '>=', now()->startOfMonth())
            ->sum('quantity');
    }

    public function recordUsage(string $type, int $quantity, ?string $model = null, ?int $userId = null, array $metadata = []): UsageRecord
    {
        return $this->usageRecords()->create([
            'user_id' => $userId,
            'type' => $type,
            'quantity' => $quantity,
            'model' => $model,
            'metadata' => $metadata ?: null,
            'recorded_at' => now(),
        ]);
    }

    public function tokenBudgetRemaining(): int
    {
        $plan = Plan::findBySlug($this->plan);
        $included = $plan?->included_tokens_monthly ?? 0;
        $used = $this->currentMonthTokenUsage();

        return max(0, $included - $used);
    }
}
