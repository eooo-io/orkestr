<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Webhook extends Model
{
    protected $fillable = [
        'project_id',
        'event',
        'url',
        'secret',
        'is_active',
        'last_triggered_at',
        'last_status',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_triggered_at' => 'datetime',
            'last_status' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class)->orderByDesc('created_at');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForEvent(Builder $query, string $event): Builder
    {
        return $query->where('event', $event);
    }
}
