<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Workflow extends Model
{
    protected $attributes = [
        'trigger_type' => 'manual',
        'status' => 'draft',
    ];

    protected $fillable = [
        'uuid',
        'project_id',
        'name',
        'slug',
        'description',
        'trigger_type',
        'trigger_config',
        'entry_step_id',
        'status',
        'context_schema',
        'termination_policy',
        'config',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'trigger_config' => 'array',
            'context_schema' => 'array',
            'termination_policy' => 'array',
            'config' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Workflow $workflow) {
            if (empty($workflow->uuid)) {
                $workflow->uuid = (string) Str::uuid();
            }
            if (empty($workflow->slug)) {
                $workflow->slug = Str::slug($workflow->name);
            }
        });

        static::deleting(function (Workflow $workflow) {
            $workflow->edges()->delete();
            $workflow->steps()->delete();
            $workflow->versions()->delete();
        });
    }

    // --- Relationships ---

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function entryStep(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class, 'entry_step_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(WorkflowStep::class)->orderBy('sort_order');
    }

    public function edges(): HasMany
    {
        return $this->hasMany(WorkflowEdge::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(WorkflowVersion::class)->orderByDesc('version_number');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // --- Scopes ---

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    // --- Helpers ---

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function nextVersionNumber(): int
    {
        return ($this->versions()->max('version_number') ?? 0) + 1;
    }

    public function snapshot(): array
    {
        return [
            'workflow' => $this->only([
                'name', 'slug', 'description', 'trigger_type', 'trigger_config',
                'status', 'context_schema', 'termination_policy', 'config',
            ]),
            'steps' => $this->steps->map(fn (WorkflowStep $step) => $step->only([
                'uuid', 'agent_id', 'type', 'name', 'position_x', 'position_y', 'config', 'sort_order',
            ]))->toArray(),
            'edges' => $this->edges->map(fn (WorkflowEdge $edge) => $edge->only([
                'source_step_id', 'target_step_id', 'condition_expression', 'label', 'priority',
            ]))->toArray(),
        ];
    }
}
