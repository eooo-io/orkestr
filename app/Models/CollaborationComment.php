<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CollaborationComment extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'organization_id',
        'resource_type',
        'resource_id',
        'thread_id',
        'line_number',
        'body',
        'resolved_at',
        'resolved_by',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CollaborationComment $comment) {
            if (empty($comment->uuid)) {
                $comment->uuid = (string) Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(CollaborationComment::class, 'thread_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(CollaborationComment::class, 'thread_id');
    }

    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->whereNull('resolved_at');
    }

    public function scopeForResource(Builder $query, string $type, int $id): Builder
    {
        return $query->where('resource_type', $type)->where('resource_id', $id);
    }
}
