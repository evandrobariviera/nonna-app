<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Cidade onde a Nonna prospecta. Localizada no Google (viewport) pra varredura não sair dela. */
class CommerceCity extends Model
{
    use HasUuids, Tenantable;

    protected $connection = 'pgsql';

    protected $fillable = ['organization_id', 'name', 'state', 'google_place_id', 'google_label', 'viewport'];

    protected $casts = [
        'viewport' => 'array',
    ];

    public const UFS = [
        'AC' => 'Acre', 'AL' => 'Alagoas', 'AP' => 'Amapá', 'AM' => 'Amazonas', 'BA' => 'Bahia',
        'CE' => 'Ceará', 'DF' => 'Distrito Federal', 'ES' => 'Espírito Santo', 'GO' => 'Goiás',
        'MA' => 'Maranhão', 'MT' => 'Mato Grosso', 'MS' => 'Mato Grosso do Sul', 'MG' => 'Minas Gerais',
        'PA' => 'Pará', 'PB' => 'Paraíba', 'PR' => 'Paraná', 'PE' => 'Pernambuco', 'PI' => 'Piauí',
        'RJ' => 'Rio de Janeiro', 'RN' => 'Rio Grande do Norte', 'RS' => 'Rio Grande do Sul',
        'RO' => 'Rondônia', 'RR' => 'Roraima', 'SC' => 'Santa Catarina', 'SP' => 'São Paulo',
        'SE' => 'Sergipe', 'TO' => 'Tocantins',
    ];

    public function label(): string
    {
        return "{$this->name} · {$this->state}";
    }

    public function located(): bool
    {
        return ! empty($this->viewport['low']) && ! empty($this->viewport['high']);
    }

    /** Localiza no Google e guarda os limites (lança RuntimeException com mensagem pra tela). */
    public function locate(\App\Services\Commerce\GooglePlaces $places): void
    {
        $this->fill($places->locateCity($this->name, $this->state))->save();
    }
}
