<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ExecutionRun extends Model
{
    protected $fillable = [
        'uuid',
        'project_id',
        'agent_id',
        'workflow_run_id',
        'status',
        'input',
        'output',
        'config',
        'started_at',
        'completed_at',
        'error',
        'created_by',
        'total_tokens',
        'total_cost_microcents',
        'total_duration_ms',
        'model_used',
    ];

    protected $attributes = [
        'status' => 'pending',
        'total_tokens' => 0,
        'total_cost_microcents' => 0,
        'total_duration_ms' => 0,
    ];

    protected function casts(): array
    {
        return [
            'input' => 'array',
            'output' => 'array',
            'config' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'total_tokens' => 'integer',
            'total_cost_microcents' => 'integer',
            'total_duration_ms' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ExecutionRun $run) {
            if (empty($run->uuid)) {
                $run->uuid = (string) Str::uuid();
            }
        });

        static::deleting(function (ExecutionRun $run) {
            $run->steps()->delete();
        });
    }

    // --- Relationships ---

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ExecutionStep::class)->orderBy('step_number');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // --- Status helpers ---

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isFinished(): bool
    {
        return in_array($this->status, ['completed', 'failed', 'cancelled']);
    }

    // --- Lifecycle helpers ---

    public function markRunning(): void
    {
        $this->update([
            'status' => 'running',
            'started_at' => now(),
        ]);
    }

    public function markCompleted(array $output = null): void
    {
        $this->update([
            'status' => 'completed',
            'output' => $output,
            'completed_at' => now(),
            'total_duration_ms' => $this->started_at
                ? (int) (now()->diffInMilliseconds($this->started_at))
                : 0,
        ]);
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'error' => $error,
            'completed_at' => now(),
            'total_duration_ms' => $this->started_at
                ? (int) (now()->diffInMilliseconds($this->started_at))
                : 0,
        ]);
    }

    public function markCancelled(): void
    {
        $this->update([
            'status' => 'cancelled',
            'completed_at' => now(),
        ]);
    }

    /**
     * Accumulate token usage from a step.
     */
    public function addTokenUsage(array $usage): void
    {
        $tokens = ($usage['input_tokens'] ?? 0) + ($usage['output_tokens'] ?? 0);
        $this->increment('total_tokens', $tokens);
    }
}
