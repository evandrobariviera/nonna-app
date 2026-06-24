<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DossierCompetitor extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $fillable = [
        'dossier_id', 'position', 'nome',
        'o_que_comunica', 'como_se_posiciona', 'o_que_nao_ocupa',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    public function dossier(): BelongsTo
    {
        return $this->belongsTo(BrandDossier::class, 'dossier_id');
    }
}
