<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class OrganizationScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (app()->has('currentOrganization')) {
            $builder->where(
                $model->getTable() . '.organization_id',
                app('currentOrganization')->id
            );
        }
    }
}
