<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class GuardrailPolicy extends Model
{
    protected $fillable = [
        'uuid',
        'organization_id',
        'name',
        'description',
        'scope',
        'scope_id',
        'budget_limits',
        'tool_restrictions',
        'output_rules',
        'access_rules',
        'approval_level',
        'priority',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'budget_limits' => 'array',
            'tool_restrictions' => 'array',
            'output_rules' => 'array',
            'access_rules' => 'array',
            'priority' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (GuardrailPolicy $policy) {
            if (empty($policy->uuid)) {
                $policy->uuid = (string) Str::uuid();
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForOrganization($query, int $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeForScope($query, string $scope, ?int $scopeId = null)
    {
        $query->where('scope', $scope);
        if ($scopeId !== null) {
            $query->where('scope_id', $scopeId);
        }

        return $query;
    }
}
