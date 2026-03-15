<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExecutionReplayStep extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'execution_replay_id',
        'step_number',
        'type',
        'input',
        'output',
        'model',
        'tokens_used',
        'cost_microcents',
        'duration_ms',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'input' => 'array',
            'output' => 'array',
            'metadata' => 'array',
            'tokens_used' => 'integer',
            'cost_microcents' => 'integer',
            'duration_ms' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function replay(): BelongsTo
    {
        return $this->belongsTo(ExecutionReplay::class, 'execution_replay_id');
    }
}
