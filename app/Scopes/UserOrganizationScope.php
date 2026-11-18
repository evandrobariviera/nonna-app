<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Usuário não tem organization_id (é ligado por organization_users, pode estar em
 * várias). Com uma organização atual, só aparecem os membros dela — sem isso, toda
 * lista de pessoas (responsável, executor, filtros) mostrava as de todas.
 *
 * O login e a sessão leem o usuário antes de SetTenant definir a organização,
 * então não são afetados.
 */
class UserOrganizationScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (!app()->has('currentOrganization')) {
            return;
        }

        $table = $model->getTable();
        $builder->whereExists(fn ($q) => $q->selectRaw('1')
            ->from('organization_users')
            ->whereColumn('organization_users.user_id', "{$table}.id")
            ->where('organization_users.organization_id', app('currentOrganization')->id));
    }
}
