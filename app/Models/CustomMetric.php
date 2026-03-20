<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CustomMetric extends Model
{
    protected $fillable = [
        'uuid',
        'organization_id',
        'name',
        'slug',
        'query_type',
        'query_config',
        'unit',
    ];

    protected function casts(): array
    {
        return [
            'query_config' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CustomMetric $metric) {
            if (empty($metric->uuid)) {
                $metric->uuid = (string) Str::uuid();
            }

            if (empty($metric->slug)) {
                $slug = Str::slug($metric->name);

                if (static::where('organization_id', $metric->organization_id)->where('slug', $slug)->exists()) {
                    $slug .= '-' . Str::random(4);
                }

                $metric->slug = $slug;
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
