<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class WorkflowRunStep extends Model
{
    protected $fillable = [
        'uuid',
        'workflow_run_id',
        'workflow_step_id',
        'execution_run_id',
        'status',
        'input',
        'output',
        'started_at',
        'completed_at',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'input' => 'array',
            'output' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (WorkflowRunStep $step) {
            if (empty($step->uuid)) {
                $step->uuid = (string) Str::uuid();
            }
        });
    }

    public function workflowRun(): BelongsTo
    {
        return $this->belongsTo(WorkflowRun::class);
    }

    public function workflowStep(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class);
    }

    public function executionRun(): BelongsTo
    {
        return $this->belongsTo(ExecutionRun::class);
    }

    public function markRunning(): void
    {
        $this->update(['status' => 'running', 'started_at' => now()]);
    }

    public function markWaitingApproval(): void
    {
        $this->update(['status' => 'waiting_approval']);
    }

    public function markCompleted(array $output = null): void
    {
        $this->update([
            'status' => 'completed',
            'output' => $output,
            'completed_at' => now(),
        ]);
    }

    public function markFailed(string $error = null): void
    {
        $this->update([
            'status' => 'failed',
            'output' => $error ? ['error' => $error] : null,
            'completed_at' => now(),
        ]);
    }

    public function markSkipped(): void
    {
        $this->update(['status' => 'skipped', 'completed_at' => now()]);
    }
}
