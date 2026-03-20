<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PluginHook extends Model
{
    protected $fillable = [
        'plugin_id',
        'hook_name',
        'handler',
        'priority',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }

    // --- Relationships ---

    public function plugin(): BelongsTo
    {
        return $this->belongsTo(Plugin::class);
    }

    // --- Scopes ---

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }
}
