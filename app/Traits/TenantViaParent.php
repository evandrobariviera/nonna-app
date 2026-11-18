<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

/**
 * Para tabelas filhas sem organization_id (rodada de aprovação, comentário, anexo,
 * histórico de status…): com uma organização atual, só aparece o registro cujo pai
 * é dela. O pai já é filtrado (Tenantable ou outro TenantViaParent), então o
 * whereHas herda o filtro em cadeia.
 *
 * Sem organização atual (jobs, comandos, link público de aprovação) nada muda.
 */
trait TenantViaParent
{
    protected static function bootTenantViaParent(): void
    {
        static::addGlobalScope('tenant_via_parent', function (Builder $builder) {
            if (app()->has('currentOrganization')) {
                $builder->whereHas($builder->getModel()->tenantParentRelation());
            }
        });
    }

    /** Nome da relação BelongsTo com o pai que carrega a organização. */
    abstract public function tenantParentRelation(): string;
}
