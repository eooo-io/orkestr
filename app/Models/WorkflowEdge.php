<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowEdge extends Model
{
    protected $fillable = [
        'workflow_id',
        'source_step_id',
        'target_step_id',
        'condition_expression',
        'label',
        'priority',
    ];

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
        ];
    }

    // --- Relationships ---

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function sourceStep(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class, 'source_step_id');
    }

    public function targetStep(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class, 'target_step_id');
    }

    // --- Helpers ---

    public function hasCondition(): bool
    {
        return ! empty($this->condition_expression);
    }
}
