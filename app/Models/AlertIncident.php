<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AlertIncident extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'uuid',
        'alert_rule_id',
        'metric_value',
        'threshold_value',
        'status',
        'acknowledged_by',
        'acknowledged_at',
        'resolved_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metric_value' => 'decimal:4',
            'threshold_value' => 'decimal:4',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AlertIncident $incident) {
            if (empty($incident->uuid)) {
                $incident->uuid = (string) Str::uuid();
            }
            if (empty($incident->created_at)) {
                $incident->created_at = now();
            }
        });
    }

    // --- Relationships ---

    public function alertRule(): BelongsTo
    {
        return $this->belongsTo(AlertRule::class);
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    // --- Helpers ---

    public function isFiring(): bool
    {
        return $this->status === 'firing';
    }

    public function isAcknowledged(): bool
    {
        return $this->status === 'acknowledged';
    }

    public function isResolved(): bool
    {
        return $this->status === 'resolved';
    }
}
