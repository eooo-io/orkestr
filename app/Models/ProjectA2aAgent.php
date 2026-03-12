<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectA2aAgent extends Model
{
    protected $table = 'project_a2a_agents';

    protected $fillable = [
        'project_id',
        'name',
        'url',
        'description',
        'skills',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'skills' => 'array',
            'enabled' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
