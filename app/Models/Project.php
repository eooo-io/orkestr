<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Project extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'uuid',
        'name',
        'description',
        'path',
        'default_model',
        'monthly_budget_usd',
        'environment',
        'icon',
        'color',
        'synced_at',
        'git_auto_commit',
        'canvas_layout',
        'organization_id',
    ];

    protected function casts(): array
    {
        return [
            'synced_at' => 'datetime',
            'git_auto_commit' => 'boolean',
            'monthly_budget_usd' => 'decimal:2',
            'canvas_layout' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Project $project) {
            if (empty($project->uuid)) {
                $project->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Resolve the project path for the current environment.
     * Translates host paths to container paths when running in Docker.
     */
    protected function resolvedPath(): Attribute
    {
        return Attribute::get(function () {
            $hostBase = env('PROJECTS_HOST_PATH');
            $containerBase = env('PROJECTS_BASE_PATH', '/projects');

            if ($hostBase && str_starts_with($this->path, $hostBase)) {
                return $containerBase . substr($this->path, strlen($hostBase));
            }

            return $this->path;
        });
    }

    public function providers(): HasMany
    {
        return $this->hasMany(ProjectProvider::class);
    }

    public function skills(): HasMany
    {
        return $this->hasMany(Skill::class);
    }

    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(Agent::class, 'project_agent')
            ->withPivot('custom_instructions', 'is_enabled')
            ->withTimestamps();
    }

    public function projectAgents(): HasMany
    {
        return $this->hasMany(ProjectAgent::class);
    }

    public function skillVariables(): HasMany
    {
        return $this->hasMany(SkillVariable::class);
    }

    public function webhooks(): HasMany
    {
        return $this->hasMany(Webhook::class);
    }

    public function repositories(): HasMany
    {
        return $this->hasMany(ProjectRepository::class);
    }

    public function repository(string $provider = 'github'): ?ProjectRepository
    {
        return $this->repositories()->where('provider', $provider)->first();
    }

    public function openclawConfig(): HasOne
    {
        return $this->hasOne(OpenClawConfig::class);
    }

    public function mcpServers(): HasMany
    {
        return $this->hasMany(ProjectMcpServer::class);
    }

    public function a2aAgents(): HasMany
    {
        return $this->hasMany(ProjectA2aAgent::class);
    }

    public function workflows(): HasMany
    {
        return $this->hasMany(Workflow::class);
    }

    public function executionRuns(): HasMany
    {
        return $this->hasMany(ExecutionRun::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(AgentSchedule::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(AgentTask::class);
    }
}
