<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectRoleAssignment extends Model
{
    public const ROLE_IC = 'ic';
    public const ROLE_DRI = 'dri';
    public const ROLE_COACH = 'coach';

    public const ROLES = [self::ROLE_IC, self::ROLE_DRI, self::ROLE_COACH];

    protected $fillable = [
        'project_id',
        'user_id',
        'role',
        'scope',
        'started_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->ended_at === null || $this->ended_at->isFuture();
    }

    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('ended_at')->orWhere('ended_at', '>', now());
        });
    }
}
