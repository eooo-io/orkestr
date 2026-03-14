<?php

namespace App\Models;

use Cron\CronExpression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class AgentSchedule extends Model
{
    protected $fillable = [
        'uuid',
        'project_id',
        'agent_id',
        'name',
        'trigger_type',
        'cron_expression',
        'timezone',
        'webhook_token',
        'webhook_secret',
        'event_name',
        'event_filters',
        'input_template',
        'execution_config',
        'is_enabled',
        'last_run_at',
        'next_run_at',
        'run_count',
        'failure_count',
        'max_retries',
        'last_error',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'event_filters' => 'array',
            'input_template' => 'array',
            'execution_config' => 'array',
            'is_enabled' => 'boolean',
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
            'run_count' => 'integer',
            'failure_count' => 'integer',
            'max_retries' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AgentSchedule $schedule) {
            if (empty($schedule->uuid)) {
                $schedule->uuid = (string) Str::uuid();
            }

            // Auto-generate webhook token for webhook triggers
            if ($schedule->trigger_type === 'webhook' && empty($schedule->webhook_token)) {
                $schedule->webhook_token = Str::random(48);
            }

            // Compute initial next_run_at for cron triggers
            if ($schedule->trigger_type === 'cron' && $schedule->cron_expression && empty($schedule->next_run_at)) {
                $schedule->next_run_at = $schedule->computeNextRun();
            }
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function executionRuns(): HasMany
    {
        return $this->hasMany(ExecutionRun::class, 'schedule_id');
    }

    // --- Scopes ---

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }

    public function scopeCron(Builder $query): Builder
    {
        return $query->where('trigger_type', 'cron');
    }

    public function scopeWebhook(Builder $query): Builder
    {
        return $query->where('trigger_type', 'webhook');
    }

    public function scopeEvent(Builder $query): Builder
    {
        return $query->where('trigger_type', 'event');
    }

    public function scopeDue(Builder $query): Builder
    {
        return $query->where('next_run_at', '<=', now());
    }

    // --- Methods ---

    /**
     * Check if this cron schedule is due to run.
     */
    public function isDue(): bool
    {
        if ($this->trigger_type !== 'cron' || ! $this->next_run_at) {
            return false;
        }

        return $this->next_run_at->lte(now());
    }

    /**
     * Compute the next run time based on the cron expression.
     */
    public function computeNextRun(): ?Carbon
    {
        if (! $this->cron_expression) {
            return null;
        }

        try {
            $cron = new CronExpression($this->cron_expression);
            $nextRun = $cron->getNextRunDate('now', 0, false, $this->timezone ?? 'UTC');

            return Carbon::instance($nextRun);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Increment run count and update last_run_at.
     */
    public function incrementRunCount(): void
    {
        $this->increment('run_count');
        $this->update(['last_run_at' => now()]);
    }

    /**
     * Record a successful run.
     */
    public function recordSuccess(): void
    {
        $this->update([
            'last_run_at' => now(),
            'last_error' => null,
            'failure_count' => 0,
        ]);
        $this->increment('run_count');
    }

    /**
     * Record a failed run and optionally disable after max retries.
     */
    public function recordFailure(string $error): void
    {
        $this->increment('failure_count');
        $this->increment('run_count');
        $this->update([
            'last_run_at' => now(),
            'last_error' => $error,
        ]);

        // Disable after exceeding max retries (if max_retries > 0)
        if ($this->max_retries > 0 && $this->failure_count >= $this->max_retries) {
            $this->update(['is_enabled' => false]);
        }
    }
}
