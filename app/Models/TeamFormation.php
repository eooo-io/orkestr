<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TeamFormation extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'uuid',
        'project_id',
        'name',
        'objective',
        'formation_strategy',
        'agent_ids',
        'status',
        'formed_by_agent_id',
        'formed_by_user_id',
        'performance_score',
        'created_at',
        'disbanded_at',
    ];

    protected function casts(): array
    {
        return [
            'agent_ids' => 'array',
            'performance_score' => 'decimal:2',
            'created_at' => 'datetime',
            'disbanded_at' => 'datetime',
        ];
    }

    protected $attributes = [
        'formation_strategy' => 'capability_match',
        'status' => 'forming',
    ];

    protected static function booted(): void
    {
        static::creating(function (TeamFormation $formation) {
            if (empty($formation->uuid)) {
                $formation->uuid = (string) Str::uuid();
            }
            if (empty($formation->created_at)) {
                $formation->created_at = now();
            }
        });
    }

    // --- Relationships ---

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function formedByAgent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'formed_by_agent_id');
    }

    public function formedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'formed_by_user_id');
    }

    // --- Scopes ---

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['forming', 'active']);
    }
}
