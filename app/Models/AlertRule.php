<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AlertRule extends Model
{
    protected $fillable = [
        'uuid',
        'organization_id',
        'name',
        'metric_slug',
        'condition',
        'threshold',
        'window_minutes',
        'cooldown_minutes',
        'notification_channel_id',
        'severity',
        'enabled',
        'last_triggered_at',
    ];

    protected function casts(): array
    {
        return [
            'threshold' => 'decimal:4',
            'enabled' => 'boolean',
            'last_triggered_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AlertRule $rule) {
            if (empty($rule->uuid)) {
                $rule->uuid = (string) Str::uuid();
            }
        });
    }

    // --- Relationships ---

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function notificationChannel(): BelongsTo
    {
        return $this->belongsTo(NotificationChannel::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(AlertIncident::class)->orderByDesc('created_at');
    }

    // --- Scopes ---

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }
}
