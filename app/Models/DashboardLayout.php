<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DashboardLayout extends Model
{
    protected $fillable = [
        'uuid',
        'organization_id',
        'user_id',
        'name',
        'layout',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'layout' => 'array',
            'is_default' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (DashboardLayout $dashboard) {
            if (empty($dashboard->uuid)) {
                $dashboard->uuid = (string) Str::uuid();
            }
        });
    }

    // --- Relationships ---

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
