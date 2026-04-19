<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SkillUpdateProposal extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_SUPERSEDED = 'superseded';

    protected $fillable = [
        'skill_id',
        'agent_id',
        'title',
        'rationale',
        'proposed_frontmatter',
        'proposed_body',
        'evidence_memory_ids',
        'pattern_key',
        'status',
        'resolved_by',
        'resolved_at',
        'suppress_until',
    ];

    protected function casts(): array
    {
        return [
            'proposed_frontmatter' => 'array',
            'evidence_memory_ids' => 'array',
            'resolved_at' => 'datetime',
            'suppress_until' => 'datetime',
        ];
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }
}
