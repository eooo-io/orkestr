<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgentTask extends Model
{
    protected $fillable = [
        'project_id',
        'agent_id',
        'parent_task_id',
        'title',
        'description',
        'priority',
        'status',
        'input_data',
        'output_data',
        'execution_id',
        'assigned_by_user_id',
        'assigned_by_agent_id',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'input_data' => 'array',
            'output_data' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected $attributes = [
        'priority' => 'medium',
        'status' => 'pending',
    ];

    // --- Relationships ---

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function parentTask(): BelongsTo
    {
        return $this->belongsTo(AgentTask::class, 'parent_task_id');
    }

    public function childTasks(): HasMany
    {
        return $this->hasMany(AgentTask::class, 'parent_task_id');
    }

    public function execution(): BelongsTo
    {
        return $this->belongsTo(ExecutionRun::class, 'execution_id');
    }

    public function assignedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }

    public function assignedByAgent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'assigned_by_agent_id');
    }

    // --- Scopes ---

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeForAgent($query, int $agentId)
    {
        return $query->where('agent_id', $agentId);
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
}
