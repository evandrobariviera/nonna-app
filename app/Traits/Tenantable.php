<?php

namespace App\Traits;

use App\Scopes\OrganizationScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

trait Tenantable
{
    protected static function bootTenantable(): void
    {
        static::addGlobalScope(new OrganizationScope());

        static::creating(function (Model $model) {
            if (!empty($model->organization_id)) {
                return;
            }

            // Sem organização atual (job, comando, webhook), herda do registro pai —
            // senão o registro nasce sem organização e some das telas.
            $model->organization_id = app()->has('currentOrganization')
                ? app('currentOrganization')->id
                : $model->organizationIdFromParent();
        });
    }

    /**
     * Organização herdada do pai. Padrão: o cliente (client_id). Models cujo pai
     * é outro (ex.: oportunidade de lead → lead) sobrescrevem tenantParent().
     */
    public function organizationIdFromParent(): ?string
    {
        [$table, $column] = $this->tenantParent();
        $id = $this->getAttribute($column);

        return $id ? DB::connection('pgsql')->table($table)->where('id', $id)->value('organization_id') : null;
    }

    /** @return array{0: string, 1: string} tabela do pai, coluna que aponta pra ele */
    protected function tenantParent(): array
    {
        return ['clients', 'client_id'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Organization::class);
    }
}
