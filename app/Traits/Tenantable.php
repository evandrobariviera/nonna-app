<?php

namespace App\Traits;

use App\Scopes\OrganizationScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait Tenantable
{
    protected static function bootTenantable(): void
    {
        static::addGlobalScope(new OrganizationScope());

        static::creating(function (Model $model) {
            if (empty($model->organization_id) && app()->has('currentOrganization')) {
                $model->organization_id = app('currentOrganization')->id;
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Organization::class);
    }
}
