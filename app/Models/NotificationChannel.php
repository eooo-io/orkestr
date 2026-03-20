<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class NotificationChannel extends Model
{
    protected $fillable = [
        'organization_id',
        'name',
        'slug',
        'type',
        'config',
        'enabled',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'enabled' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (NotificationChannel $channel) {
            if (empty($channel->slug)) {
                $slug = Str::slug($channel->name);

                if (static::where('organization_id', $channel->organization_id)->where('slug', $slug)->exists()) {
                    $slug .= '-' . Str::random(4);
                }

                $channel->slug = $slug;
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class, 'channel_id')->orderByDesc('created_at');
    }
}
