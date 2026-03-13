<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AgentConversation extends Model
{
    protected $fillable = [
        'uuid',
        'agent_id',
        'project_id',
        'execution_run_id',
        'messages',
        'summary',
        'token_count',
    ];

    protected function casts(): array
    {
        return [
            'messages' => 'array',
            'token_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AgentConversation $conv) {
            if (empty($conv->uuid)) {
                $conv->uuid = (string) Str::uuid();
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

    public function executionRun(): BelongsTo
    {
        return $this->belongsTo(ExecutionRun::class);
    }
}
