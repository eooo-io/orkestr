<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SkillEvalRun extends Model
{
    protected $fillable = [
        'eval_suite_id', 'skill_version_id', 'baseline_run_id',
        'model', 'mode', 'status',
        'overall_score', 'delta_score',
        'results', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'results' => 'array',
            'overall_score' => 'decimal:2',
            'delta_score' => 'decimal:2',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function suite(): BelongsTo
    {
        return $this->belongsTo(SkillEvalSuite::class, 'eval_suite_id');
    }

    public function skillVersion(): BelongsTo
    {
        return $this->belongsTo(SkillVersion::class, 'skill_version_id');
    }

    public function baselineRun(): BelongsTo
    {
        return $this->belongsTo(self::class, 'baseline_run_id');
    }
}
