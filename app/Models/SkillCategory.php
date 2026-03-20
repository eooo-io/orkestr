<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SkillCategory extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'description',
        'icon',
        'color',
        'sort_order',
    ];

    public function skills(): HasMany
    {
        return $this->hasMany(Skill::class, 'category_id');
    }

    public function librarySkills(): HasMany
    {
        return $this->hasMany(LibrarySkill::class, 'category_id');
    }
}
