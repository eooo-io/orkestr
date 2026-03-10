<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Agent extends Model
{
    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'role',
        'description',
        'base_instructions',
        'model',
        'icon',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Agent $agent) {
            if (empty($agent->uuid)) {
                $agent->uuid = (string) Str::uuid();
            }
            if (empty($agent->slug)) {
                $agent->slug = Str::slug($agent->name);
            }
        });
    }

    public function projectAgents(): HasMany
    {
        return $this->hasMany(ProjectAgent::class);
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_agent')
            ->withPivot('custom_instructions', 'is_enabled')
            ->withTimestamps();
    }
}
