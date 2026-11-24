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

    /** Começo do título das pendências automáticas ("Conferir a campanha — X", "Fechar o projeto — X"). */
    public const PENDENCY_PREFIXES = ['Conferir a', 'Conferir o', 'Fechar a', 'Fechar o'];

    public static function pendencyTitle(Project $project, bool $closing): string
    {
        $what = $project->type === 'campanha' ? 'a campanha' : 'o projeto';

        return mb_substr(($closing ? 'Fechar ' : 'Conferir ') . $what . ' — ' . $project->title, 0, 500);
    }

    /**
     * As conferências da campanha/projeto em texto curto, para o agente ler (etapa E.5): coluna do
     * documento, rascunho do próximo planejamento e "outras campanhas do cliente". Mais recente
     * primeiro; null se não houver nenhuma.
     */
    public static function historyText(string $projectId, int $limit = 4): ?string
    {
        $rows = self::withoutGlobalScopes()->where('project_id', $projectId)
            ->orderByDesc('checked_on')->orderByDesc('created_at')->limit($limit)->get();
        if ($rows->isEmpty()) {
            return null;
        }
        $lights = ['verde' => 'no ritmo', 'amarelo' => 'abaixo do ritmo', 'vermelho' => 'bem abaixo do ritmo'];

        return $rows->map(function (self $c) use ($lights) {
            $lines = ['- ' . $c->checked_on->format('d/m/Y') . ($c->is_closing ? ' (FECHAMENTO)' : '') . ': '
                . (self::SITUACOES[$c->situacao]['label'] ?? $c->situacao)];
            foreach ($c->metas ?? [] as $m) {
                $lines[] = '  · ' . $m['nome'] . ($m['atual'] ? ": está em {$m['atual']}" : '') . ($m['meta'] ? " (queremos {$m['meta']})" : '')
                    . (isset($lights[$m['semaforo'] ?? '']) ? ', ' . $lights[$m['semaforo']] : '')
                    . ($m['avaliacao'] ? ' — coordenador: ' . mb_strtolower(self::AVALIACOES[$m['avaliacao']] ?? '') : '')
                    . ($m['comentario'] ? " ({$m['comentario']})" : '');
            }
            foreach (collect($c->cuidados ?? [])->where('aconteceu', 'sim') as $a) {
                $lines[] = "  · Sinal de alerta aconteceu: {$a['se']}" . ($a['combinado'] ? ' — fizemos o combinado: ' . mb_strtolower(self::COMBINADO[$a['combinado']] ?? '') : '');
            }
            if ($c->travou) {
                $lines[] = "  · Travou: {$c->travou}" . ($c->depende ? ' (' . mb_strtolower(self::DEPENDE[$c->depende] ?? '') . ')' : '');
            }
            $lines[] = "  · Próximo passo: {$c->proximo_passo}";

            return implode("\n", $lines);
        })->implode("\n");
    }

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
