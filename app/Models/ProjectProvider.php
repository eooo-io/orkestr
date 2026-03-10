<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectProvider extends Model
{
    protected $fillable = [
        'project_id',
        'provider_slug',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
