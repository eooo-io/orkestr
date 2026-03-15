<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectMcpServer extends Model
{
    protected $fillable = [
        'project_id',
        'name',
        'transport',
        'command',
        'args',
        'url',
        'env',
        'headers',
        'enabled',
        'approval_status',
        'approved_at',
        'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'args' => 'array',
            'env' => 'array',
            'headers' => 'array',
            'enabled' => 'boolean',
            'approved_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
