<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AgentProcess extends Model
{
    protected $fillable = [
        'uuid',
        'agent_id',
        'project_id',
        'status',
        'pid',
        'started_at',
        'last_heartbeat_at',
        'stopped_at',
        'state',
        'restart_policy',
        'max_restarts',
        'restart_count',
        'wake_conditions',
        'stop_reason',
    ];

    protected function casts(): array
    {
        return [
            'state' => 'array',
            'wake_conditions' => 'array',
            'started_at' => 'datetime',
            'last_heartbeat_at' => 'datetime',
            'stopped_at' => 'datetime',
            'max_restarts' => 'integer',
            'restart_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $process) {
            if (empty($process->uuid)) {
                $process->uuid = (string) Str::uuid();
            }
        });
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function isRunning(): bool
    {
        return in_array($this->status, ['starting', 'running', 'idle']);
    }

    public function isHealthy(): bool
    {
        if (! $this->isRunning()) {
            return false;
        }

        // Stale if no heartbeat in 60 seconds
        if ($this->last_heartbeat_at && $this->last_heartbeat_at->diffInSeconds(now()) > 60) {
            return false;
        }

        return true;
    }

    public function canRestart(): bool
    {
        if ($this->restart_policy === 'never') {
            return false;
        }

        if ($this->restart_policy === 'on_failure' && $this->status !== 'crashed') {
            return false;
        }

        return $this->restart_count < $this->max_restarts;
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['starting', 'running', 'idle']);
    }

    public function scopeForAgent($query, int $agentId, int $projectId)
    {
        return $query->where('agent_id', $agentId)->where('project_id', $projectId);
    }

    /**
     * Record a heartbeat.
     */
    public function heartbeat(array $stateUpdate = []): void
    {
        $data = ['last_heartbeat_at' => now()];

        if (! empty($stateUpdate)) {
            $data['state'] = array_merge($this->state ?? [], $stateUpdate);
        }

        $this->update($data);
    }

    /**
     * Transition to a new status.
     */
    public function transitionTo(string $status, ?string $reason = null): void
    {
        $data = ['status' => $status];

        if ($status === 'running' && ! $this->started_at) {
            $data['started_at'] = now();
        }

        if (in_array($status, ['stopped', 'crashed'])) {
            $data['stopped_at'] = now();
            if ($reason) {
                $data['stop_reason'] = $reason;
            }
        }

        if ($status === 'crashed') {
            $data['restart_count'] = $this->restart_count + 1;
        }

        $this->update($data);
    }
}
