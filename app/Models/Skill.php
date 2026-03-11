<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Skill extends Model
{
    protected $fillable = [
        'uuid',
        'project_id',
        'slug',
        'name',
        'description',
        'model',
        'max_tokens',
        'tools',
        'includes',
        'body',
        'conditions',
        'template_variables',
    ];

    protected function casts(): array
    {
        return [
            'tools' => 'array',
            'includes' => 'array',
            'conditions' => 'array',
            'template_variables' => 'array',
            'max_tokens' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Skill $skill) {
            if (empty($skill->uuid)) {
                $skill->uuid = (string) Str::uuid();
            }
            if (empty($skill->slug)) {
                $skill->slug = Str::slug($skill->name);
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(SkillVersion::class)->orderByDesc('version_number');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'skill_tag');
    }

    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(Agent::class, 'agent_skill')
            ->withPivot('project_id');
    }

    public function skillVariables(): HasMany
    {
        return $this->hasMany(SkillVariable::class);
    }
}
