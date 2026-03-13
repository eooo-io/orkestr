<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ExecutionStep extends Model
{
    public const PHASES = ['perceive', 'reason', 'act', 'observe'];

    protected $fillable = [
        'uuid',
        'execution_run_id',
        'step_number',
        'phase',
        'input',
        'output',
        'tool_calls',
        'token_usage',
        'duration_ms',
        'status',
        'error',
    ];

    protected $attributes = [
        'status' => 'pending',
        'duration_ms' => 0,
    ];

    protected function casts(): array
    {
        return [
            'input' => 'array',
            'output' => 'array',
            'tool_calls' => 'array',
            'token_usage' => 'array',
            'duration_ms' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ExecutionStep $step) {
            if (empty($step->uuid)) {
                $step->uuid = (string) Str::uuid();
            }
        });
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(ExecutionRun::class, 'execution_run_id');
    }

    // --- Phase helpers ---

    public function isPerceive(): bool
    {
        return $this->phase === 'perceive';
    }

    public function isReason(): bool
    {
        return $this->phase === 'reason';
    }

    public function isAct(): bool
    {
        return $this->phase === 'act';
    }

    public function isObserve(): bool
    {
        return $this->phase === 'observe';
    }

    // --- Status helpers ---

    public function markRunning(): void
    {
        $this->update(['status' => 'running']);
    }

    public function markCompleted(array $output = null, int $durationMs = 0): void
    {
        $this->update([
            'status' => 'completed',
            'output' => $output,
            'duration_ms' => $durationMs,
        ]);
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'error' => $error,
        ]);
    }
}
