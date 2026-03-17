<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DataSource extends Model
{
    protected $fillable = [
        'project_id',
        'name',
        'type',
        'connection_config',
        'access_mode',
        'enabled',
        'health_status',
        'last_health_check',
    ];

    protected function casts(): array
    {
        return [
            'connection_config' => 'encrypted:array',
            'enabled' => 'boolean',
            'last_health_check' => 'datetime',
        ];
    }

    // --- Relationships ---

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(Agent::class, 'agent_data_source')
            ->withPivot('project_id', 'access_mode')
            ->withTimestamps();
    }

    // --- Scopes ---

    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    // --- Helpers ---

    public function isReadOnly(): bool
    {
        return $this->access_mode === 'read_only';
    }

    public function maskedConfig(): array
    {
        $config = $this->connection_config ?? [];
        $masked = [];

        foreach ($config as $key => $value) {
            if (in_array($key, ['password', 'secret', 'access_key', 'secret_key', 'api_key', 'token'])) {
                $masked[$key] = str_repeat('*', min(strlen((string) $value), 20));
            } else {
                $masked[$key] = $value;
            }
        }

        return $masked;
    }

    /**
     * Valid data source types.
     */
    public static function validTypes(): array
    {
        return ['postgres', 'mysql', 'minio', 's3', 'filesystem', 'redis'];
    }
}
