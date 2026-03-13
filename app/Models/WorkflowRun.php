<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class WorkflowRun extends Model
{
    protected $fillable = [
        'uuid',
        'workflow_id',
        'project_id',
        'status',
        'input',
        'context_snapshot',
        'current_step_id',
        'started_at',
        'completed_at',
        'error',
        'created_by',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'input' => 'array',
            'context_snapshot' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (WorkflowRun $run) {
            if (empty($run->uuid)) {
                $run->uuid = (string) Str::uuid();
            }
        });

        static::deleting(function (WorkflowRun $run) {
            $run->runSteps()->delete();
        });
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function runSteps(): HasMany
    {
        return $this->hasMany(WorkflowRunStep::class);
    }

    public function currentStep(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class, 'current_step_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Status helpers

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    public function isWaitingCheckpoint(): bool
    {
        return $this->status === 'waiting_checkpoint';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isFinished(): bool
    {
        return in_array($this->status, ['completed', 'failed', 'cancelled']);
    }

    public function markRunning(): void
    {
        $this->update(['status' => 'running', 'started_at' => now()]);
    }

    public function markWaitingCheckpoint(int $stepId): void
    {
        $this->update(['status' => 'waiting_checkpoint', 'current_step_id' => $stepId]);
    }

    public function markCompleted(array $contextSnapshot = null): void
    {
        $this->update([
            'status' => 'completed',
            'context_snapshot' => $contextSnapshot,
            'completed_at' => now(),
        ]);
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'error' => $error,
            'completed_at' => now(),
        ]);
    }

    public function markCancelled(): void
    {
        $this->update(['status' => 'cancelled', 'completed_at' => now()]);
    }
}
