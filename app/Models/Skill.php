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
        'summary',
        'model',
        'max_tokens',
        'tools',
        'includes',
        'body',
        'conditions',
        'template_variables',
        'category_id',
        'skill_type',
        'owner_id',
        'codeowners',
        'extends_skill_id',
        'override_sections',
        'tuned_for_model',
        'last_validated_model',
        'last_validated_at',
        'last_validated_eval_run_id',
    ];

    protected function casts(): array
    {
        return [
            'tools' => 'array',
            'includes' => 'array',
            'conditions' => 'array',
            'template_variables' => 'array',
            'codeowners' => 'array',
            'override_sections' => 'array',
            'max_tokens' => 'integer',
            'last_validated_at' => 'datetime',
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

    public function category(): BelongsTo
    {
        return $this->belongsTo(SkillCategory::class, 'category_id');
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

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function parentSkill(): BelongsTo
    {
        return $this->belongsTo(Skill::class, 'extends_skill_id');
    }

    public function childSkills(): HasMany
    {
        return $this->hasMany(Skill::class, 'extends_skill_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(SkillReview::class);
    }

    public function testCases(): HasMany
    {
        return $this->hasMany(SkillTestCase::class);
    }

    public function analytics(): HasMany
    {
        return $this->hasMany(SkillAnalytic::class);
    }

    public function gotchas(): HasMany
    {
        return $this->hasMany(SkillGotcha::class);
    }

    public function evalSuites(): HasMany
    {
        return $this->hasMany(SkillEvalSuite::class);
    }

    public function activeGotchas(): HasMany
    {
        return $this->hasMany(SkillGotcha::class)->whereNull('resolved_at');
    }
}
