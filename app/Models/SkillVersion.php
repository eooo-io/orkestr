<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SkillVersion extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'skill_id',
        'version_number',
        'frontmatter',
        'body',
        'tuned_for_model',
        'note',
        'saved_at',
    ];

    protected function casts(): array
    {
        return [
            'frontmatter' => 'array',
            'saved_at' => 'datetime',
        ];
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }
}
