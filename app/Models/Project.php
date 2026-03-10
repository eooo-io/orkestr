<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Project extends Model
{
    protected $fillable = [
        'uuid',
        'name',
        'description',
        'path',
        'synced_at',
        'git_auto_commit',
    ];

    protected function casts(): array
    {
        return [
            'synced_at' => 'datetime',
            'git_auto_commit' => 'boolean',
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
}
