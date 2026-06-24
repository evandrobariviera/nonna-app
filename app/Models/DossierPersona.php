<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DossierPersona extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $fillable = [
        'dossier_id', 'position', 'tipo',
        'nome_ficticio', 'idade_genero', 'cargo_setor', 'renda_contexto',
        'como_se_informa', 'o_que_valida_compra', 'dores_principais',
        'o_que_motiva', 'o_que_nunca_diria', 'insight_persona',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    public static array $tipos = [
        'principal'  => 'Principal',
        'secundaria' => 'Secundária',
    ];

    public function dossier(): BelongsTo
    {
        return $this->belongsTo(BrandDossier::class, 'dossier_id');
    }
}
