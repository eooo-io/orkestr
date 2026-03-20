<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class EventTopic extends Model
{
    protected $fillable = [
        'organization_id',
        'name',
        'slug',
        'description',
        'schema',
        'retention_hours',
    ];

    protected function casts(): array
    {
        return [
            'schema' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (EventTopic $topic) {
            if (empty($topic->slug)) {
                $topic->slug = Str::slug($topic->name);
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(EventSubscription::class, 'topic_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(EventLog::class, 'topic_id')->orderByDesc('published_at');
    }
}
