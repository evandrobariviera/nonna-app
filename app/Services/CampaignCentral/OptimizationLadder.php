<?php

namespace App\Services\CampaignCentral;

use App\Models\AdCampaign;
use App\Models\CampaignLog;
use App\Services\Leads\LeadFunnel;
use Illuminate\Support\Facades\DB;

/**
 * A escada das 5 perguntas: toda otimização segue a mesma ordem e para na
 * primeira resposta "não". Cada degrau traz os números que ajudam a
 * responder; quem responde é o gestor. O diário guarda as respostas.
 */
class OptimizationLadder
{
    /** [pergunta, o que olhar, quem decide se for "não"] */
    public static array $steps = [
        'rodando'    => ['Está rodando?', 'Gastou, os anúncios estão aprovados, tem saldo.', 'gestor'],
        'olhando'    => ['As pessoas estão olhando?', 'Cliques, custo por clique e taxa de clique.', 'gestor'],
        'atendido'   => ['Quem clicou está sendo atendido?', 'Tempo de resposta no WhatsApp.', 'estrategia'],
        'presta'     => ['O contato presta?', 'Quantos leads a régua marcou como bons.', 'estrategia'],
        'negocio'    => ['Vira negócio?', 'Vendas marcadas e retorno sobre o investimento.', 'estrategia'],
    ];

    /** Números de cada degrau, prontos para a tela. */
    public static function facts(AdCampaign $campaign): array
    {
        $campaign->loadMissing('adAccount.client');
        $client = $campaign->adAccount->client;
        $db = DB::connection('pgsql');

        $daily = $db->table('ad_daily_snapshots')
            ->where('client_ad_account_id', $campaign->client_ad_account_id)
            ->where('entity_level', 'campaign')
            ->where('entity_id', $campaign->external_id)
            ->where('snapshot_date', '>=', now()->subDays(14)->toDateString())
            ->selectRaw('snapshot_date, spend, clicks, impressions')
            ->orderBy('snapshot_date')
            ->get();

        $last7 = $daily->filter(fn ($r) => $r->snapshot_date >= now()->subDays(7)->toDateString());
        $prev7 = $daily->filter(fn ($r) => $r->snapshot_date < now()->subDays(7)->toDateString());
        $last3 = $daily->filter(fn ($r) => $r->snapshot_date >= now()->subDays(3)->toDateString());

        $ctr = fn ($rows) => $rows->sum('impressions') > 0 ? round($rows->sum('clicks') / $rows->sum('impressions') * 100, 2) : null;
        $cpc = fn ($rows) => $rows->sum('clicks') > 0 ? round($rows->sum('spend') / $rows->sum('clicks'), 2) : null;

        $problemAds = $db->table('ad_ads as ad')
            ->join('ad_adsets as st', 'st.id', '=', 'ad.ad_adset_id')
            ->where('st.ad_campaign_id', $campaign->id)
            ->whereRaw("ad.raw_data->>'effective_status' IN ('DISAPPROVED', 'WITH_ISSUES')")
            ->count();

        $account = $campaign->adAccount;
        $diagnostic = $client?->currentServiceDiagnostic();
        $funnel = LeadFunnel::forCampaign($campaign, now()->subDays(30), now())['campaign'];

        return [
            'rodando' => array_filter([
                'Gasto nos últimos 3 dias' => self::money($last3->sum('spend')),
                'Dias sem gasto entre os 3 últimos' => (string) (3 - $last3->where('spend', '>', 0)->count()),
                'Anúncios reprovados ou com problema' => (string) $problemAds,
                'Saldo na conta' => $account->balance !== null ? self::money((float) $account->balance) : null,
            ], fn ($v) => $v !== null),
            'olhando' => array_filter([
                'Taxa de clique (7 dias)' => self::pct($ctr($last7)) . self::versus($ctr($last7), $ctr($prev7), '%'),
                'Custo por clique (7 dias)' => self::money($cpc($last7)) . self::versus($cpc($last7), $cpc($prev7), 'R$'),
                'Cliques (7 dias)' => number_format($last7->sum('clicks'), 0, ',', '.'),
            ]),
            'atendido' => $diagnostic ? array_filter([
                'Primeira resposta, em média' => $diagnostic->avg_first_response_minutes !== null ? $diagnostic->avg_first_response_minutes . ' min' : null,
                'Nota do atendimento' => $diagnostic->service_score !== null ? $diagnostic->service_score . ' de 100' : null,
                'Período analisado' => $diagnostic->period_start?->format('d/m') . ' a ' . $diagnostic->period_end?->format('d/m'),
            ]) : ['Atendimento' => 'Este cliente ainda não tem diagnóstico de atendimento publicado.'],
            'presta' => [
                'Leads (30 dias)' => (string) $funnel['leads'],
                'Avaliados pela régua' => (string) $funnel['evaluated'],
                'Bons' => $funnel['evaluated'] ? $funnel['good'] . ' (' . $funnel['good_pct'] . '%)' : 'nenhum avaliado ainda',
                'Custo por lead bom' => self::money($funnel['cost_per_good']),
            ],
            'negocio' => array_filter([
                'Vendas marcadas (30 dias)' => (string) $funnel['sold'],
                'Valor vendido' => $funnel['sold_value'] > 0 ? self::money($funnel['sold_value']) : null,
                'Cada R$ 1 investido voltou' => $funnel['return'] !== null ? self::money($funnel['return']) : null,
                'Retorno estimado' => $funnel['return_estimated'] !== null ? self::money($funnel['return_estimated']) . ' (estimativa)' : null,
            ], fn ($v) => $v !== null),
        ];
    }

    /**
     * Registra a otimização no diário e zera o relógio do ritmo da campanha.
     *
     * @param array<string, bool> $answers degrau => sim/não, só até o primeiro "não"
     */
    public static function record(AdCampaign $campaign, array $answers, string $comment, int $userId): CampaignLog
    {
        $stop = collect($answers)->search(false);
        $stopIndex = $stop === false ? null : array_search($stop, array_keys(self::$steps), true);

        return DB::connection('pgsql')->transaction(function () use ($campaign, $answers, $comment, $userId, $stop, $stopIndex) {
            $log = CampaignLog::create([
                'organization_id'      => $campaign->organization_id,
                'client_ad_account_id' => $campaign->client_ad_account_id,
                'entity_level'         => 'campaign',
                'entity_id'            => $campaign->external_id,
                'entity_name'          => $campaign->name,
                'platform'             => $campaign->platform,
                'logged_by'            => $userId,
                'type'                 => 'otimizacao',
                'description'          => $comment,
                'metadata'             => [
                    'escada' => [
                        // Lista (não mapa): o jsonb reordena chaves de objeto,
                        // e a ordem dos degraus é o que importa aqui.
                        'answers'    => collect($answers)->map(fn ($yes, $step) => ['step' => $step, 'yes' => $yes])->values()->all(),
                        'stopped_at' => $stop === false ? null : $stop,
                        'label'      => $stop === false
                            ? 'Passou pelos 5 degraus'
                            : 'Parou no degrau ' . ($stopIndex + 1) . ': ' . self::$steps[$stop][0],
                    ],
                ],
            ]);

            $campaign->update(['last_optimized_at' => now()]);

            return $log;
        });
    }

    private static function money(?float $v): string
    {
        return $v === null ? '—' : 'R$ ' . number_format($v, 2, ',', '.');
    }

    private static function pct(?float $v): string
    {
        return $v === null ? '—' : number_format($v, 2, ',', '.') . '%';
    }

    /** " (antes: X)" quando há a semana anterior para comparar. */
    private static function versus(?float $now, ?float $before, string $unit): string
    {
        if ($now === null || $before === null) {
            return '';
        }

        $fmt = $unit === '%' ? self::pct($before) : self::money($before);

        return ' · na semana anterior: ' . $fmt;
    }
}
