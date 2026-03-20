<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class MarketplaceAgent extends Model
{
    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'description',
        'category',
        'tags',
        'agent_config',
        'skills_config',
        'workflow_config',
        'wiring_config',
        'author',
        'author_url',
        'source',
        'version',
        'downloads',
        'upvotes',
        'downvotes',
        'screenshots',
        'readme',
        'published_by',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'agent_config' => 'array',
            'skills_config' => 'array',
            'workflow_config' => 'array',
            'wiring_config' => 'array',
            'screenshots' => 'array',
            'downloads' => 'integer',
            'upvotes' => 'integer',
            'downvotes' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (MarketplaceAgent $agent) {
            if (empty($agent->uuid)) {
                $agent->uuid = (string) Str::uuid();
            }
            if (empty($agent->slug)) {
                $agent->slug = Str::slug($agent->name);
            }
        });
    }

    // --- Relationships ---

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    // --- Scopes ---

    public function scopeSearch($query, ?string $term)
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function ($qb) use ($term) {
            $qb->where('name', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%");
        });
    }

    // --- Accessors ---

    public function getRatingScoreAttribute(): int
    {
        return $this->upvotes - $this->downvotes;
    }
}
