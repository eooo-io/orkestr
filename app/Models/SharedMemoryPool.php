<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class SharedMemoryPool extends Model
{
    protected $fillable = [
        'uuid',
        'project_id',
        'name',
        'slug',
        'description',
        'access_policy',
        'retention_days',
    ];

    protected function casts(): array
    {
        return [
            'retention_days' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SharedMemoryPool $pool) {
            if (empty($pool->uuid)) {
                $pool->uuid = (string) Str::uuid();
            }
            if (empty($pool->slug)) {
                $pool->slug = Str::slug($pool->name);
            }
        });
    }

    // --- Relationships ---

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(Agent::class, 'shared_memory_pool_agent')
            ->withPivot('access_mode')
            ->withTimestamps();
    }

    public function entries(): HasMany
    {
        return $this->hasMany(SharedMemoryEntry::class, 'pool_id');
    }

    public function knowledgeGraphNodes(): HasMany
    {
        return $this->hasMany(KnowledgeGraphNode::class, 'pool_id');
    }
}
