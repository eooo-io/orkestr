<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SkillEvalPrompt extends Model
{
    protected $fillable = ['eval_suite_id', 'prompt', 'expected_behavior', 'grading_criteria', 'sort_order'];

    protected function casts(): array
    {
        return [
            'grading_criteria' => 'array',
        ];
    }

    public function suite(): BelongsTo
    {
        return $this->belongsTo(SkillEvalSuite::class, 'eval_suite_id');
    }
}
