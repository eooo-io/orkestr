<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SkillEvalRun extends Model
{
    protected $fillable = [
        'eval_suite_id', 'model', 'mode', 'status',
        'overall_score', 'results', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'results' => 'array',
            'overall_score' => 'decimal:2',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function suite(): BelongsTo
    {
        return $this->belongsTo(SkillEvalSuite::class, 'eval_suite_id');
    }
}
