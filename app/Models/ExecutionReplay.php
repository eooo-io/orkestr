<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ExecutionReplay extends Model
{
    protected $fillable = [
        'uuid',
        'project_id',
        'agent_id',
        'name',
        'status',
        'total_steps',
        'total_tokens',
        'total_cost_microcents',
        'total_duration_ms',
        'metadata',
        'started_at',
        'completed_at',
    ];

    protected $attributes = [
        'status' => 'running',
        'total_steps' => 0,
        'total_tokens' => 0,
        'total_cost_microcents' => 0,
        'total_duration_ms' => 0,
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'total_steps' => 'integer',
            'total_tokens' => 'integer',
            'total_cost_microcents' => 'integer',
            'total_duration_ms' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ExecutionReplay $replay) {
            if (empty($replay->uuid)) {
                $replay->uuid = (string) Str::uuid();
            }
        });

        static::deleting(function (ExecutionReplay $replay) {
            $replay->steps()->delete();
        });
    }

    // --- Relationships ---

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ExecutionReplayStep::class)->orderBy('step_number');
    }
}
