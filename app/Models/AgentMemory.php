<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AgentMemory extends Model
{
    public const TYPES = ['conversation', 'working', 'long_term'];

    protected $fillable = [
        'uuid',
        'agent_id',
        'project_id',
        'type',
        'key',
        'content',
        'embedding',
        'metadata',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'metadata' => 'array',
            'embedding' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AgentMemory $memory) {
            if (empty($memory->uuid)) {
                $memory->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Resolve the database connection name.
     *
     * Uses `knowledge` connection if available (PostgreSQL + pgvector),
     * otherwise falls back to the default connection (for tests / dev without PG).
     */
    public function getConnectionName(): ?string
    {
        return static::resolveConnectionName();
    }

    /**
     * Determine which connection name to use.
     */
    public static function resolveConnectionName(): ?string
    {
        static $resolved = null;

        if ($resolved !== null) {
            return $resolved ?: null;
        }

        // If running tests or knowledge connection is not configured, use default
        if (app()->runningUnitTests()) {
            $resolved = '';

            return null;
        }

        try {
            $config = config('database.connections.knowledge');
            if (! $config || empty($config['host'])) {
                $resolved = '';

                return null;
            }

            DB::connection('knowledge')->getPdo();
            $resolved = 'knowledge';

            return 'knowledge';
        } catch (\Throwable $e) {
            Log::debug("Knowledge DB not available, using default: {$e->getMessage()}");
            $resolved = '';

            return null;
        }
    }

    /**
     * Check if pgvector is available on the current connection.
     */
    public static function hasPgVector(): bool
    {
        if (static::resolveConnectionName() !== 'knowledge') {
            return false;
        }

        try {
            $result = DB::connection('knowledge')
                ->select("SELECT 1 FROM pg_extension WHERE extname = 'vector' LIMIT 1");

            return ! empty($result);
        } catch (\Throwable) {
            return false;
        }
    }

    // --- Relationships ---

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    // --- Scopes ---

    public function scopeForAgent($query, int $agentId, int $projectId)
    {
        return $query->where('agent_id', $agentId)->where('project_id', $projectId);
    }

    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    // --- Helpers ---

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
