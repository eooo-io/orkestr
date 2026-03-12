<?php

namespace App\Models\Concerns;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope('organization', function (Builder $builder) {
            if ($orgId = static::resolveOrganizationId()) {
                $builder->where($builder->getModel()->getTable() . '.organization_id', $orgId);
            }
        });

        static::creating(function ($model) {
            if (empty($model->organization_id)) {
                $model->organization_id = static::resolveOrganizationId();
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    protected static function resolveOrganizationId(): ?int
    {
        // Resolve from request context if available
        if (app()->bound('current_organization')) {
            return app('current_organization')?->id;
        }

        return null;
    }
}
