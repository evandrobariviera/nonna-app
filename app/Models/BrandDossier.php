<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BrandDossier extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $fillable = [
        'client_id', 'version', 'title', 'fase', 'data_kickoff',
        'estrategista_id', 'redator_id',
        // Zona 1 — Roteiro
        'roteiro_pesquisa_previa', 'roteiro_kickoff_negocio', 'roteiro_kickoff_mercado',
        'roteiro_kickoff_cliente', 'roteiro_kickoff_comunicacao', 'roteiro_pesquisa_notas',
        // Bloco 01
        'bloco01_visao_geral', 'bloco01_historico', 'bloco01_pontos_fortes',
        'bloco01_pontos_atencao', 'bloco01_objetivos', 'bloco01_contexto_mercado',
        'bloco01_como_compra', 'bloco01_gera_confianca', 'bloco01_espaco_disponivel',
        'bloco01_comunicacao_atual', 'bloco01_percepcao_atual', 'bloco01_gap_percepcao',
        // Bloco 02
        'bloco02_problema', 'bloco02_problema_justificativa', 'bloco02_oportunidade',
        'bloco02_insight', 'bloco02_insight_sustentacao', 'bloco02_objetivos',
        // Bloco 03
        'bloco03_proposito', 'bloco03_visao', 'bloco03_missao', 'bloco03_valores',
        'bloco03_posicionamento_formula', 'bloco03_posicionamento_texto',
        'bloco03_promessa', 'bloco03_razoes', 'bloco03_personalidade',
        'bloco03_arquetipo', 'bloco03_essencia', 'bloco03_essencia_descricao',
        // Bloco 04
        'bloco04_tom_voz', 'bloco04_exemplos_tom', 'bloco04_territorio_visual',
        'bloco04_palavras_prioritarias', 'bloco04_palavras_secundarias',
        'bloco04_palavras_evitar', 'bloco04_frases_ancora',
        'bloco04_mandatorios', 'bloco04_restricoes', 'bloco04_teste_final',
        // Meta
        'created_by', 'approved_by', 'approved_at',
    ];

    protected $casts = [
        'version'                        => 'integer',
        'data_kickoff'                   => 'date',
        'approved_at'                    => 'datetime',
        'roteiro_pesquisa_previa'        => 'array',
        'roteiro_kickoff_negocio'        => 'array',
        'roteiro_kickoff_mercado'        => 'array',
        'roteiro_kickoff_cliente'        => 'array',
        'roteiro_kickoff_comunicacao'    => 'array',
        'bloco02_objetivos'              => 'array',
        'bloco03_valores'                => 'array',
        'bloco03_posicionamento_formula' => 'array',
        'bloco03_arquetipo'              => 'array',
        'bloco04_exemplos_tom'           => 'array',
        'bloco04_territorio_visual'      => 'array',
    ];

    public static array $fases = [
        'pesquisa_previa'      => ['label' => 'Pesquisa Prévia',      'color' => '#60a5fa', 'step' => 1],
        'kickoff'              => ['label' => 'Kickoff',              'color' => '#fbbf24', 'step' => 2],
        'pesquisa_estrategica' => ['label' => 'Pesquisa Estratégica', 'color' => '#d63eaa', 'step' => 3],
        'redacao'              => ['label' => 'Redação',              'color' => '#7b3fe4', 'step' => 4],
        'aprovado'             => ['label' => 'Aprovado',             'color' => '#34d399', 'step' => 5],
    ];

    public static array $fasesSequence = [
        'pesquisa_previa', 'kickoff', 'pesquisa_estrategica', 'redacao', 'aprovado',
    ];

    public function faseLabel(): string
    {
        return self::$fases[$this->fase]['label'] ?? $this->fase;
    }

    public function faseColor(): string
    {
        return self::$fases[$this->fase]['color'] ?? '#666';
    }

    public function faseStep(): int
    {
        return self::$fases[$this->fase]['step'] ?? 1;
    }

    public function proximaFase(): ?string
    {
        $idx = array_search($this->fase, self::$fasesSequence);
        return self::$fasesSequence[$idx + 1] ?? null;
    }

    public function isAprovado(): bool
    {
        return $this->fase === 'aprovado';
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function competitors(): HasMany
    {
        return $this->hasMany(DossierCompetitor::class, 'dossier_id')->orderBy('position');
    }

    public function personas(): HasMany
    {
        return $this->hasMany(DossierPersona::class, 'dossier_id')->orderBy('position');
    }

    public function estrategista(): BelongsTo
    {
        return $this->belongsTo(User::class, 'estrategista_id');
    }

    public function redator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'redator_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
