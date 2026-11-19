<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Documento padrão da agência (Mapa dos Documentos, na Central de Ajuda). Documento padrão
 * = ferramenta da operação preenchida a cada cliente (HTML/PDF com estrutura fixa); o
 * manual de cada um é um capítulo comum da Ajuda, ligado por `help_article_slug`.
 */
class DocumentStandard extends Model
{
    use HasUuids, Tenantable;

    protected $connection = 'pgsql';

    protected $fillable = [
        'organization_id',
        'slug',
        'name',
        'stage',
        'position',
        'status',
        'summary',
        'when_made',
        'made_by',
        'reviewed_by',
        'inputs',
        'next_step',
        'template_html',
        'help_article_slug',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    /** Etapas do mapa, na ordem em que o trabalho acontece. */
    public static array $stages = [
        'comercial'      => ['label' => 'Comercial',      'description' => 'Da primeira conversa ao contrato assinado.'],
        'entrada'        => ['label' => 'Entrada',        'description' => 'Do contrato ao kickoff: conhecer o cliente a fundo.'],
        'planejamento'   => ['label' => 'Planejamento',   'description' => 'O que vamos fazer no ciclo e como vamos medir.'],
        'execucao'       => ['label' => 'Execução',       'description' => 'Campanhas, projetos e as peças que saem deles.'],
        'acompanhamento' => ['label' => 'Acompanhamento', 'description' => 'Reuniões, resultados e a opinião do cliente.'],
    ];

    /** `a_decidir` = candidato: aparece no mapa para avaliação, mas fica fora da conta de andamento. */
    // `bg` = fundo levemente tingido do cartão no mapa (rgba, funciona no tema claro e escuro).
    public static array $statuses = [
        'a_decidir'     => ['label' => 'A decidir',     'color' => 'var(--blue)',   'bg' => 'rgba(46, 144, 250, 0.05)'],
        'na_fila'       => ['label' => 'Na fila',       'color' => 'var(--muted)',  'bg' => ''],
        'em_construcao' => ['label' => 'Em construção', 'color' => 'var(--orange)', 'bg' => 'rgba(238, 121, 25, 0.07)'],
        'fechado'       => ['label' => 'Fechado',       'color' => 'var(--green)',  'bg' => 'rgba(16, 185, 129, 0.09)'],
    ];

    public function stageLabel(): string
    {
        return self::$stages[$this->stage]['label'] ?? $this->stage;
    }

    public function statusLabel(): string
    {
        return self::$statuses[$this->status]['label'] ?? $this->status;
    }

    public function statusColor(): string
    {
        return self::$statuses[$this->status]['color'] ?? 'var(--muted)';
    }

    public function statusBackground(): string
    {
        return self::$statuses[$this->status]['bg'] ?? '';
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** Todos os documentos na ordem do mapa (etapa, depois posição). */
    public static function inMapOrder()
    {
        $order = array_keys(self::$stages);

        return static::query()->orderBy('position')->get()
            ->sortBy(fn (self $d) => [array_search($d->stage, $order, true) === false ? 99 : array_search($d->stage, $order, true), $d->position])
            ->values();
    }
}
