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
    ];

    protected function casts(): array
    {
        return [
            'args' => 'array',
            'env' => 'array',
            'headers' => 'array',
            'enabled' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
