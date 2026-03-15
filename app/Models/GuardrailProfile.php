<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class GuardrailProfile extends Model
{
    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'description',
        'is_system',
        'organization_id',
        'budget_limits',
        'tool_restrictions',
        'output_rules',
        'access_rules',
        'approval_level',
        'input_sanitization',
        'network_rules',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'budget_limits' => 'array',
            'tool_restrictions' => 'array',
            'output_rules' => 'array',
            'access_rules' => 'array',
            'input_sanitization' => 'array',
            'network_rules' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (GuardrailProfile $profile) {
            if (empty($profile->uuid)) {
                $profile->uuid = (string) Str::uuid();
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }

    /**
     * Convert this profile to a policy configuration array.
     */
    public function toPolicyConfig(): array
    {
        return [
            'budget_limits' => $this->budget_limits,
            'tool_restrictions' => $this->tool_restrictions,
            'output_rules' => $this->output_rules,
            'access_rules' => $this->access_rules,
            'approval_level' => $this->approval_level,
            'input_sanitization' => $this->input_sanitization,
            'network_rules' => $this->network_rules,
        ];
    }
}
