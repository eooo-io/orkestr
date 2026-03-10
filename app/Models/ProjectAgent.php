<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProjectAgent extends Model
{
    protected $table = 'project_agent';

    protected $fillable = [
        'project_id',
        'agent_id',
        'custom_instructions',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'agent_skill', 'agent_id', 'skill_id', 'agent_id', 'id')
            ->wherePivot('project_id', $this->project_id);
    }
}
