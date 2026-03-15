<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CustomEndpoint extends Model
{
    protected $fillable = [
        'organization_id',
        'name',
        'slug',
        'base_url',
        'api_key',
        'models',
        'enabled',
        'health_status',
        'last_health_check',
        'avg_latency_ms',
    ];

    protected function casts(): array
    {
        return [
            'models' => 'array',
            'enabled' => 'boolean',
            'api_key' => 'encrypted',
            'last_health_check' => 'datetime',
            'avg_latency_ms' => 'float',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CustomEndpoint $ep) {
            if (empty($ep->slug)) {
                $ep->slug = Str::slug($ep->name);
            }
        });
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }
}
