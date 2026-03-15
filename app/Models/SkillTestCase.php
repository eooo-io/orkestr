<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SkillTestCase extends Model
{
    protected $fillable = [
        'skill_id',
        'name',
        'input',
        'expected_output',
        'assertion_type',
        'pass_threshold',
    ];

    protected function casts(): array
    {
        return [
            'pass_threshold' => 'float',
        ];
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }
}
