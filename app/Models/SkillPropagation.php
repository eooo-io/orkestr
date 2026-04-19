<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SkillPropagation extends Model
{
    public const STATUS_SUGGESTED = 'suggested';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_DISMISSED = 'dismissed';
    public const STATUS_MODIFIED = 'modified';

    protected $fillable = [
        'source_skill_id',
        'target_project_id',
        'target_agent_id',
        'status',
        'modified_skill_id',
        'suggestion_score',
        'suggested_at',
        'resolved_at',
        'resolved_by',
    ];

    protected function casts(): array
    {
        return [
            'suggestion_score' => 'decimal:2',
            'suggested_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function sourceSkill(): BelongsTo
    {
        return $this->belongsTo(Skill::class, 'source_skill_id');
    }

    public function targetProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'target_project_id');
    }

    public function targetAgent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'target_agent_id');
    }

    public function modifiedSkill(): BelongsTo
    {
        return $this->belongsTo(Skill::class, 'modified_skill_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_SUGGESTED);
    }
}
