<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SkillAnalytic extends Model
{
    protected $table = 'skill_analytics';

    protected $fillable = [
        'skill_id',
        'organization_id',
        'date',
        'test_runs',
        'pass_count',
        'fail_count',
        'avg_tokens',
        'avg_cost_microcents',
        'avg_latency_ms',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'test_runs' => 'integer',
            'pass_count' => 'integer',
            'fail_count' => 'integer',
            'avg_tokens' => 'float',
            'avg_cost_microcents' => 'float',
            'avg_latency_ms' => 'float',
        ];
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }
}
