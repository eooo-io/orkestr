<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class WorkflowStep extends Model
{
    protected $fillable = [
        'uuid',
        'workflow_id',
        'agent_id',
        'type',
        'name',
        'position_x',
        'position_y',
        'config',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'position_x' => 'float',
            'position_y' => 'float',
            'sort_order' => 'integer',
            'config' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (WorkflowStep $step) {
            if (empty($step->uuid)) {
                $step->uuid = (string) Str::uuid();
            }
        });
    }

    // --- Constants ---

    public const TYPES = [
        'agent',
        'checkpoint',
        'condition',
        'parallel_split',
        'parallel_join',
        'start',
        'end',
    ];

    // --- Relationships ---

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function outgoingEdges(): HasMany
    {
        return $this->hasMany(WorkflowEdge::class, 'source_step_id');
    }

    public function incomingEdges(): HasMany
    {
        return $this->hasMany(WorkflowEdge::class, 'target_step_id');
    }

    // --- Helpers ---

    public function isAgent(): bool
    {
        return $this->type === 'agent';
    }

    public function isCheckpoint(): bool
    {
        return $this->type === 'checkpoint';
    }

    public function isCondition(): bool
    {
        return $this->type === 'condition';
    }

    public function isTerminal(): bool
    {
        return $this->type === 'end';
    }

    public function requiresAgent(): bool
    {
        return $this->type === 'agent';
    }
}
