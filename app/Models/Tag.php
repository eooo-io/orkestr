<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    protected $fillable = [
        'name',
        'color',
    ];

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'skill_tag');
    }
}
