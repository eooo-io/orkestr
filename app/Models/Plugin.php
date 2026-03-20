<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Plugin extends Model
{
    protected $fillable = [
        'uuid',
        'organization_id',
        'name',
        'slug',
        'description',
        'version',
        'author',
        'type',
        'manifest',
        'entry_point',
        'config',
        'enabled',
        'installed_at',
    ];

    protected function casts(): array
    {
        return [
            'manifest' => 'array',
            'config' => 'array',
            'enabled' => 'boolean',
            'installed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Plugin $plugin) {
            if (empty($plugin->uuid)) {
                $plugin->uuid = (string) Str::uuid();
            }
            if (empty($plugin->slug)) {
                $plugin->slug = Str::slug($plugin->name);
            }
        });
    }

    // --- Relationships ---

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function hooks(): HasMany
    {
        return $this->hasMany(PluginHook::class);
    }

    // --- Scopes ---

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }
}
