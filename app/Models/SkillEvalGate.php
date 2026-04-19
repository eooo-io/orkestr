<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SkillEvalGate extends Model
{
    protected $fillable = [
        'skill_id',
        'enabled',
        'required_suite_ids',
        'fail_threshold_delta',
        'auto_run_on_save',
        'block_sync',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'required_suite_ids' => 'array',
            'fail_threshold_delta' => 'decimal:2',
            'auto_run_on_save' => 'boolean',
            'block_sync' => 'boolean',
        ];
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }
}
