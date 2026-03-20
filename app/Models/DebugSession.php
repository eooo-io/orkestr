<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DebugSession extends Model
{
    protected $fillable = [
        'uuid',
        'project_id',
        'execution_run_id',
        'created_by',
        'title',
        'status',
        'participants',
        'breakpoints',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'participants' => 'array',
            'breakpoints' => 'array',
            'ended_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (DebugSession $session) {
            if (empty($session->uuid)) {
                $session->uuid = (string) Str::uuid();
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function executionRun(): BelongsTo
    {
        return $this->belongsTo(ExecutionRun::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
