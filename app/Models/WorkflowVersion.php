<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowVersion extends Model
{
    protected $fillable = [
        'workflow_id',
        'version_number',
        'snapshot',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'snapshot' => 'array',
        ];
    }

    // --- Relationships ---

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }
}
