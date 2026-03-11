<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpenClawConfig extends Model
{
    protected $fillable = [
        'project_id',
        'soul_content',
        'tools',
        'a2a_agents',
    ];

    protected function casts(): array
    {
        return [
            'tools' => 'array',
            'a2a_agents' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
