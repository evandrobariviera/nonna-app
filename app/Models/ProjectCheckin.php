<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Conferência da campanha/projeto pelo coordenador (etapa E, 24/11). Ver
 * .claude/docs/acompanhamento-campanhas.md e App\Services\Projects\CheckinSystem.
 */
class ProjectCheckin extends Model
{
    use HasUuids, Tenantable;

    protected $connection = 'pgsql';

    protected $fillable = [
        'organization_id', 'project_id', 'user_id', 'checked_on', 'situacao',
        'metas', 'cuidados', 'travou', 'depende', 'proximo_passo', 'sistema', 'is_closing',
    ];

    protected $casts = [
        'checked_on' => 'date',
        'metas'      => 'array',
        'cuidados'   => 'array',
        'sistema'    => 'array',
        'is_closing' => 'boolean',
    ];

    public const SITUACOES = [
        'no_rumo' => ['label' => 'No rumo',      'color' => '#16a34a'],
        'atencao' => ['label' => 'Atenção',      'color' => '#EE7919'],
        'fora'    => ['label' => 'Fora do rumo', 'color' => '#dc2626'],
    ];

    public const AVALIACOES = [
        'vai_bater'     => 'Vai bater',
        'em_risco'      => 'Em risco',
        'nao_vai_bater' => 'Não vai bater',
    ];

    public const COMBINADO = [
        'sim'       => 'Sim',
        'andamento' => 'Em andamento',
        'nao'       => 'Não',
    ];

    public const DEPENDE = ['equipe' => 'Da equipe', 'cliente' => 'Do cliente'];

    protected function tenantParent(): array
    {
        return ['projects', 'project_id'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
