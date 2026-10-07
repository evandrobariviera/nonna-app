<?php

namespace App\Services\Tasks;

use App\Models\Organization;
use App\Models\Task;
use App\Models\TaskFormat;
use App\Models\TaskTypePoint;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Pontos de sprint — o "peso" de cada tarefa, pra carga deixar de ser só "quantas tarefas"
 * (um post estático e um vídeo institucional contavam igual). Por enquanto a métrica é
 * HÍBRIDA: as telas mostram pontos E quantidade lado a lado, até o time se adaptar
 * (decisão do usuário em 2026-10-06).
 *
 * Como uma tarefa ganha pontos (nessa ordem):
 *  1. Ajuste manual na tarefa (tasks.sprint_points_manual = true) — nunca é sobrescrito.
 *  2. Formato escolhido na tarefa (tasks.task_format_id) → pontos do formato.
 *  3. Formato DETECTADO pelo título: primeiro formato do tipo da tarefa (na ordem do
 *     catálogo) com alguma palavra-chave contida no título — por isso os mais específicos
 *     vêm antes ("Post Reels" tem que cair em Reels, não em Post).
 *  4. Ponto padrão do tipo (task_type_points); sem nada configurado, 1.
 *
 * tasks.sprint_points guarda o resultado (calculado no save pelo TaskObserver), pra telas
 * somarem direto no banco. Mudou o catálogo → "Recalcular" (recalculate()) reaplica em tudo
 * que não foi ajustado à mão.
 */
class SprintPoints
{
    // Catálogo inicial — montado a partir dos títulos reais das tarefas (out/2026). Escala
    // 1 ponto ≈ 15 min de trabalho (2026-10-07): calibrada pelo tempo útil em "Em Produção"
    // por formato e pela otimização de campanha (~12 min = 1 ponto). Landing/site são ponto
    // de partida (dados de web exageram) — o time ajusta em Configurações.
    public const DEFAULT_FORMATS = [
        'criacao' => [
            ['Logo / identidade visual',     24, 'logo, identidade, manual de marca, branding'],
            ['Apresentação / catálogo / e-book', 13, 'apresentacao, catalogo, ebook, e-book, revista'],
            ['Reels / vídeo curto',           5, 'reels, reel, tiktok, shorts'],
            ['Vídeo (produção / edição)',    24, 'video, edicao, motion, animacao, vinheta'],
            ['Captação / gravação',           6, 'captacao, gravacao, gravar, filmagem, fotos, fotografia'],
            ['Carrossel',                    16, 'carrossel, carrosel'],
            ['Banner / peça gráfica',         8, 'banner, convite, capa, folder, flyer, outdoor, panfleto, cartao, camiseta'],
            ['Roteiro / copy',                4, 'roteiro, copy, legenda, texto'],
            ['Post estático / story',         4, 'post, arte, story, stories, card, criativo'],
        ],
        'web' => [
            ['Ajuste / manutenção em site',  15, 'ajuste, ajustar, correcao, corrigir, manutencao, plugin, atualizacao, atualizar, otimizar, popup, formulario'],
            ['Bloco / seção de página',      20, 'bloco, secao'],
            ['Landing page',                 40, 'landing'],
            ['Desenvolvimento de página / site', 80, 'site, pagina, desenvolver, ecommerce, loja'],
        ],
    ];

    public const DEFAULT_TYPE_POINTS = [
        'criacao' => 8, 'web' => 12, 'trafego' => 8, 'setup' => 8, 'social' => 4, 'seo' => 8,
        'email' => 8, 'estrategia' => 4, 'administrativo' => 4, 'reunioes' => 4,
    ];

    /**
     * Ordem do fluxo da tarefa — "pontua em X" vale pra X ou qualquer status depois dele
     * (tarefa que pula de Produção direto pra Concluído pontua igual). Ajuste vem depois de
     * Revisão porque só existe depois de alguém revisar.
     */
    public const FLOW = ['backlog', 'em_producao', 'revisao_interna', 'ajuste_alteracao', 'aprovacao', 'despacho_agendamento', 'concluido'];

    // Status em que cada tipo pontua, quando a organização não configurou. Criação pontua ao
    // entregar pra Revisão Interna (fim do trabalho de quem executou); o resto, na conclusão.
    public const DEFAULT_SCORE_STATUS = ['criacao' => 'revisao_interna'];

    // Cada "Marcar otimização feita" numa campanha (campaign_logs type=otimizacao).
    public const DEFAULT_OPTIMIZATION_POINTS = 1;

    /** @var array<string, array{formats: Collection, types: array}> catálogo por organização */
    private array $cache = [];

    /**
     * Status que contam como "pontuou" pra cada tipo: o configurado e os depois dele no fluxo.
     *
     * @return array{byType: array<string, string[]>, default: string[]}
     */
    public static function scoreStatuses(string $organizationId): array
    {
        $configured = TaskTypePoint::withoutGlobalScopes()->where('organization_id', $organizationId)
            ->pluck('score_status', 'task_type')->all();

        $from = fn (string $status) => array_slice(self::FLOW, (int) array_search($status, self::FLOW, true));

        $byType = [];
        foreach (array_keys(Task::$types) as $type) {
            $status = $configured[$type] ?? self::DEFAULT_SCORE_STATUS[$type] ?? 'concluido';
            $byType[$type] = $from(in_array($status, self::FLOW, true) ? $status : 'concluido');
        }

        return ['byType' => $byType, 'default' => $from('concluido')];
    }

    public static function optimizationPoints(?Organization $organization): int
    {
        return (int) data_get($organization?->settings, 'sprint_points.optimization_points', self::DEFAULT_OPTIMIZATION_POINTS);
    }

    /** Cria o catálogo inicial de uma organização (só o que ainda não existe). */
    public static function seedDefaults(string $organizationId): void
    {
        foreach (self::DEFAULT_TYPE_POINTS as $type => $points) {
            TaskTypePoint::withoutGlobalScopes()->firstOrCreate(
                ['organization_id' => $organizationId, 'task_type' => $type],
                ['points' => $points] + (
                    // Banco novo: a migração de 06/10 roda antes da coluna existir (07/10).
                    \Illuminate\Support\Facades\Schema::connection('pgsql')->hasColumn('task_type_points', 'score_status')
                        ? ['score_status' => self::DEFAULT_SCORE_STATUS[$type] ?? 'concluido'] : []
                )
            );
        }

        if (TaskFormat::withoutGlobalScopes()->where('organization_id', $organizationId)->exists()) {
            return;
        }
        foreach (self::DEFAULT_FORMATS as $type => $formats) {
            foreach ($formats as $i => [$name, $points, $keywords]) {
                TaskFormat::withoutGlobalScopes()->create([
                    'organization_id' => $organizationId,
                    'task_type'       => $type,
                    'name'            => $name,
                    'points'          => $points,
                    'keywords'        => $keywords,
                    'position'        => $i,
                ]);
            }
        }
    }

    /** Pontos que a tarefa deveria ter pelo catálogo (ignora ajuste manual). */
    public function automaticPoints(Task $task): int
    {
        $catalog = $this->catalog($task->organization_id);

        $format = $task->task_format_id
            ? $catalog['formats']->firstWhere('id', $task->task_format_id)
            : $this->detectFormat($task);

        return $format?->points ?? $catalog['types'][$task->task_type] ?? 1;
    }

    /** Formato detectado pelo título (null se nenhuma palavra-chave do tipo bater). */
    public function detectFormat(Task $task): ?TaskFormat
    {
        if (! $task->task_type || ! $task->organization_id) {
            return null;
        }

        $title = Str::ascii(mb_strtolower((string) $task->title));

        return $this->catalog($task->organization_id)['formats']
            ->where('task_type', $task->task_type)
            ->first(function (TaskFormat $f) use ($title) {
                // Palavra-chave tem que começar uma palavra do título (\b só na frente: aceita
                // plural, "banners"/"videos") — senão "catalogo" batia em "logo" e "parte" em "arte".
                foreach ($f->keywordList() as $kw) {
                    if ($kw !== '' && preg_match('/\b' . preg_quote($kw, '/') . '/u', $title)) {
                        return true;
                    }
                }
                return false;
            });
    }

    /** Chamado no save da tarefa (TaskObserver::saving) — mantém sprint_points em dia. */
    public function apply(Task $task): void
    {
        if ($task->sprint_points_manual && $task->sprint_points !== null) {
            return;
        }

        if ($task->exists && ! $task->isDirty(['title', 'task_type', 'task_format_id', 'sprint_points_manual']) && $task->sprint_points !== null) {
            return;
        }

        $task->sprint_points = $this->automaticPoints($task);
    }

    /**
     * Reaplica o catálogo em todas as tarefas da organização que NÃO foram ajustadas à mão.
     * Update direto no banco (sem eventos), em lotes — devolve quantas mudaram.
     */
    public function recalculate(string $organizationId): int
    {
        $this->forget($organizationId);

        // Calcula tudo em memória e grava agrupado por valor (um UPDATE por pontuação, não
        // um por tarefa) — com milhares de tarefas, um UPDATE por linha levava minutos.
        $idsByPoints = [];
        Task::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('sprint_points_manual', false)
            ->select(['id', 'organization_id', 'title', 'task_type', 'task_format_id', 'sprint_points'])
            ->chunkById(1000, function ($tasks) use (&$idsByPoints) {
                foreach ($tasks as $task) {
                    $points = $this->automaticPoints($task);
                    if ($points !== $task->sprint_points) {
                        $idsByPoints[$points][] = $task->id;
                    }
                }
            });

        $changed = 0;
        foreach ($idsByPoints as $points => $ids) {
            foreach (array_chunk($ids, 1000) as $chunk) {
                $changed += Task::withoutGlobalScopes()->whereIn('id', $chunk)->update(['sprint_points' => $points]);
            }
        }

        return $changed;
    }

    public function forget(?string $organizationId = null): void
    {
        if ($organizationId) {
            unset($this->cache[$organizationId]);
        } else {
            $this->cache = [];
        }
    }

    private function catalog(?string $organizationId): array
    {
        if (! $organizationId) {
            return ['formats' => collect(), 'types' => []];
        }

        return $this->cache[$organizationId] ??= [
            'formats' => TaskFormat::withoutGlobalScopes()->where('organization_id', $organizationId)
                ->orderBy('task_type')->orderBy('position')->get(),
            'types'   => TaskTypePoint::withoutGlobalScopes()->where('organization_id', $organizationId)
                ->pluck('points', 'task_type')->all(),
        ];
    }
}
