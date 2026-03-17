<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentKnowledge extends Model
{
    protected $table = 'agent_knowledge';

    protected $fillable = [
        'agent_id',
        'project_id',
        'namespace',
        'key',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    /**
     * Use the 'knowledge' DB connection when available, fall back to default.
     */
    public function getConnectionName()
    {
        try {
            \DB::connection('knowledge')->getPdo();

            return 'knowledge';
        } catch (\Exception) {
            return config('database.default');
        }
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function scopeForAgent($query, int $agentId, int $projectId)
    {
        return $query->where('agent_id', $agentId)->where('project_id', $projectId);
    }

    public function scopeInNamespace($query, string $namespace)
    {
        return $query->where('namespace', $namespace);
    }
}
