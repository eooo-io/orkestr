<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NegotiationLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'task_id',
        'team_formation_id',
        'agent_id',
        'action',
        'details',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (NegotiationLog $log) {
            if (empty($log->created_at)) {
                $log->created_at = now();
            }
        });
    }

    // --- Relationships ---

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }
}
