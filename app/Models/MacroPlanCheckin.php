<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Conferência das metas do planejamento (24/11). Ver App\Services\Projects\PlanCheckinSystem.
 * Situações, avaliações e "depende" são as mesmas da conferência de campanha (ProjectCheckin).
 */
class MacroPlanCheckin extends Model
{
    use HasUuids, Tenantable;

    protected $connection = 'pgsql';

    protected $fillable = [
        'organization_id', 'macro_plan_id', 'user_id', 'checked_on', 'moment', 'situacao',
        'metas', 'travou', 'depende', 'proximo_passo', 'sistema', 'is_closing',
    ];

    protected $casts = [
        'checked_on' => 'date',
        'metas'      => 'array',
        'sistema'    => 'array',
        'is_closing' => 'boolean',
    ];

    /** Conferir as metas do ciclo a cada N dias (decisão 24/11), além do meio e do fechamento. */
    public const DAYS = 30;

    public const MOMENTS = [
        'mensal'     => 'Conferência do mês',
        'meio'       => 'Revisão do meio do ciclo',
        'fechamento' => 'Fechamento do ciclo',
    ];

    public const RESULTADOS = ['atingiu' => 'Atingiu', 'parcial' => 'Parcial', 'nao_atingiu' => 'Não atingiu'];

    /** Começo do título das pendências automáticas do planejamento. */
    public const PENDENCY_PREFIXES = ['Conferir as metas do planejamento', 'Revisão do meio do planejamento', 'Fechar o planejamento'];

    public static function pendencyTitle(MacroPlan $plan, string $moment): string
    {
        $client = Client::withoutGlobalScopes()->whereKey($plan->client_id)->value('company_name') ?? '';
        $prefix = ['mensal' => self::PENDENCY_PREFIXES[0], 'meio' => self::PENDENCY_PREFIXES[1], 'fechamento' => self::PENDENCY_PREFIXES[2]][$moment];

        return mb_substr("{$prefix} — {$client}", 0, 500);
    }

    protected function tenantParent(): array
    {
        return ['macro_plans', 'macro_plan_id'];
    }

    public function macroPlan(): BelongsTo
    {
        return $this->belongsTo(MacroPlan::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Conferências das metas em texto curto para o agente (mais recente primeiro). */
    public static function historyText(string $planId, int $limit = 3): ?string
    {
        $rows = self::withoutGlobalScopes()->where('macro_plan_id', $planId)
            ->orderByDesc('checked_on')->orderByDesc('created_at')->limit($limit)->get();
        if ($rows->isEmpty()) {
            return null;
        }
        $lights = ['verde' => 'no ritmo', 'amarelo' => 'abaixo do ritmo', 'vermelho' => 'bem abaixo do ritmo'];

        return $rows->map(function (self $c) use ($lights) {
            $lines = ['- ' . $c->checked_on->format('d/m/Y') . ' (' . (self::MOMENTS[$c->moment] ?? $c->moment) . '): '
                . (ProjectCheckin::SITUACOES[$c->situacao]['label'] ?? $c->situacao)];
            foreach ($c->metas ?? [] as $m) {
                $lines[] = '  · Meta "' . ($m['nome'] ?? '') . '"' . (($m['atual'] ?? null) ? ": está em {$m['atual']}" : '') . (($m['meta'] ?? null) ? " (queremos {$m['meta']})" : '')
                    . (isset($lights[$m['semaforo'] ?? '']) ? ', ' . $lights[$m['semaforo']] : '')
                    . (($m['avaliacao'] ?? null) ? ' — ' . mb_strtolower(ProjectCheckin::AVALIACOES[$m['avaliacao']] ?? '') : '')
                    . (!empty($m['resultado']) ? ' — resultado final: ' . mb_strtolower(self::RESULTADOS[$m['resultado']] ?? '') : '')
                    . (($m['comentario'] ?? null) ? " ({$m['comentario']})" : '');
            }
            if ($c->travou) {
                $lines[] = "  · Travou: {$c->travou}" . ($c->depende ? ' (' . mb_strtolower(ProjectCheckin::DEPENDE[$c->depende] ?? '') . ')' : '');
            }
            $lines[] = "  · Próximo passo: {$c->proximo_passo}";

            return implode("\n", $lines);
        })->implode("\n");
    }
}
