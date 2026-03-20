<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class FederationDelegation extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'peer_id',
        'local_agent_id',
        'remote_agent_slug',
        'direction',
        'status',
        'input',
        'output',
        'cost_microcents',
        'duration_ms',
        'created_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'input' => 'array',
            'output' => 'array',
            'completed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (FederationDelegation $delegation) {
            if (empty($delegation->uuid)) {
                $delegation->uuid = (string) Str::uuid();
            }
            if (empty($delegation->created_at)) {
                $delegation->created_at = now();
            }
        });
    }

    // --- Relationships ---

    public function peer(): BelongsTo
    {
        return $this->belongsTo(FederationPeer::class, 'peer_id');
    }

    public function localAgent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'local_agent_id');
    }
}
