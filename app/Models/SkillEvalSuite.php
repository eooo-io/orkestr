<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SkillEvalSuite extends Model
{
    protected $fillable = ['skill_id', 'name', 'description', 'scorer'];

    protected $attributes = [
        'scorer' => 'keyword',
    ];

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }

    public function prompts(): HasMany
    {
        return $this->hasMany(SkillEvalPrompt::class, 'eval_suite_id')->orderBy('sort_order');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(SkillEvalRun::class, 'eval_suite_id')->orderByDesc('created_at');
    }
}
