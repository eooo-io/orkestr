<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class RoutingRule extends Model
{
    protected $fillable = [
        'uuid',
        'project_id',
        'name',
        'description',
        'conditions',
        'target_strategy',
        'target_agents',
        'sla_config',
        'priority',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'target_agents' => 'array',
            'sla_config' => 'array',
            'enabled' => 'boolean',
            'priority' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $rule) {
            if (empty($rule->uuid)) {
                $rule->uuid = (string) Str::uuid();
            }
        });
    }

    // --- Relationships ---

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    // --- Scopes ---

    public function scopeActive($query)
    {
        return $query->where('enabled', true);
    }
}
