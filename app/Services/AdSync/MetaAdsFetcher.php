<?php

namespace App\Services\AdSync;

use App\Models\ClientAdAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaAdsFetcher
{
    private const API_VERSION = 'v20.0';

    // "Resultado" no Ads Manager depende do objetivo real do conjunto
    // (optimization_goal), não da campanha — uma campanha OUTCOME_LEADS pode
    // otimizar pra formulário, mensagem ou chamada dependendo do conjunto.
    // Mapa construído a partir de dado real (REPLIES confirmado contra a API
    // de uma conta com campanhas de mensagem) + convenções documentadas do
    // Meta pros demais goals. Goals fora daqui caem no fallback (ver
    // resolveConversions()) — nunca fica pior que a lista fixa antiga.
    private const OPTIMIZATION_GOAL_ACTION_TYPES = [
        'REPLIES'            => ['onsite_conversion.messaging_conversation_started_7d'],
        'LEAD_GENERATION'    => ['lead', 'onsite_conversion.lead_grouped'],
        'QUALITY_LEAD'       => ['lead', 'onsite_conversion.lead_grouped'],
        'QUALITY_CALL'       => ['onsite_conversion.call_confirm', 'click_to_call_call_confirm'],
        'LINK_CLICKS'        => ['link_click'],
        'LANDING_PAGE_VIEWS' => ['landing_page_view', 'omni_landing_page_view'],
        'APP_INSTALLS'       => ['app_install', 'omni_app_install', 'mobile_app_install'],
        'THRUPLAY'           => ['video_view'],
    ];

    // Conversão customizada (pixel/evento configurado só naquela conta) —
    // sem promoted_object (não vem via Insights) não dá pra saber o evento
    // exato, então soma por prefixo em vez de tipo exato. Menos preciso, mas
    // cobre a maioria dos casos sem precisar de uma chamada extra.
    private const PREFIX_MATCHED_GOALS = ['OFFSITE_CONVERSIONS', 'ONSITE_CONVERSIONS'];

    private const DEFAULT_CONVERSION_ACTION_TYPES = ['purchase', 'omni_purchase', 'lead', 'offsite_conversion.fb_pixel_purchase'];

    public function fetchCampaigns(ClientAdAccount $account, string $accessToken): array
    {
        $campaigns = $this->fetchCampaignsFlat($account, $accessToken);
        $adsets    = $this->fetchAdsetsFlat($account, $accessToken);
        $ads       = $this->fetchAdsFlat($account, $accessToken);

        $adsByAdset = collect($ads)->groupBy('adset_external_id');
        $adsetsByCampaign = collect($adsets)->groupBy('campaign_external_id');

        return array_map(function ($campaign) use ($adsetsByCampaign, $adsByAdset) {
            $campaign['adsets'] = $adsetsByCampaign->get($campaign['external_id'], collect())
                ->map(function ($adset) use ($adsByAdset) {
                    $adset['ads'] = $adsByAdset->get($adset['external_id'], collect())->values()->all();
                    return $adset;
                })
                ->values()
                ->all();

            return $campaign;
        }, $campaigns);
    }

    private function fetchCampaignsFlat(ClientAdAccount $account, string $accessToken): array
    {
        $rows = $this->fetchAllPages(
            "https://graph.facebook.com/" . self::API_VERSION . "/act_{$account->account_id}/campaigns",
            [
                'fields'       => 'id,name,status,objective,start_time,stop_time',
                'limit'        => 200,
                'access_token' => $accessToken,
            ],
            'MetaAdsFetcher::fetchCampaigns',
            $account
        );

        return array_map(fn ($c) => [
            'external_id' => $c['id'],
            'name'        => $c['name'],
            'status'      => strtolower($c['status'] ?? 'active'),
            'objective'   => $c['objective'] ?? null,
            'start_date'  => isset($c['start_time']) ? substr($c['start_time'], 0, 10) : null,
            'end_date'    => isset($c['stop_time']) ? substr($c['stop_time'], 0, 10) : null,
            'raw_data'    => $c,
        ], $rows);
    }

    private function fetchAdsetsFlat(ClientAdAccount $account, string $accessToken): array
    {
        $rows = $this->fetchAllPages(
            "https://graph.facebook.com/" . self::API_VERSION . "/act_{$account->account_id}/adsets",
            [
                'fields'       => 'id,name,status,campaign_id,daily_budget,lifetime_budget,targeting',
                'limit'        => 200,
                'access_token' => $accessToken,
            ],
            'MetaAdsFetcher::fetchAdsets',
            $account
        );

        return array_map(fn ($a) => [
            'external_id'          => $a['id'],
            'campaign_external_id' => $a['campaign_id'],
            'name'                 => $a['name'],
            'status'               => strtolower($a['status'] ?? 'active'),
            // Meta devolve orçamento em centavos da moeda da conta, igual balance/spend_cap.
            'daily_budget'         => isset($a['daily_budget']) ? ((float) $a['daily_budget']) / 100 : null,
            'lifetime_budget'      => isset($a['lifetime_budget']) ? ((float) $a['lifetime_budget']) / 100 : null,
            'targeting'            => $a['targeting'] ?? null,
            'raw_data'             => $a,
        ], $rows);
    }

    private function fetchAdsFlat(ClientAdAccount $account, string $accessToken): array
    {
        $rows = $this->fetchAllPages(
            "https://graph.facebook.com/" . self::API_VERSION . "/act_{$account->account_id}/ads",
            [
                // Só pede o subcampo de object_story_spec que realmente usa
                // (detectar carrossel) — pedir o objeto inteiro por anúncio
                // já disparou "Please reduce the amount of data" do Graph API
                // com limit=200. O limite de página também cai pra 50: essa
                // é a chamada mais pesada (expande creative por anúncio).
                'fields'       => 'id,name,status,adset_id,creative{id,thumbnail_url,video_id,object_story_spec{link_data{child_attachments}}}',
                'limit'        => 50,
                'access_token' => $accessToken,
            ],
            'MetaAdsFetcher::fetchAds',
            $account
        );

        return array_map(function ($a) {
            $creative = $a['creative'] ?? [];

            return [
                'external_id'       => $a['id'],
                'adset_external_id' => $a['adset_id'],
                'name'              => $a['name'],
                'status'            => strtolower($a['status'] ?? 'active'),
                'creative_type'     => $this->inferCreativeType($creative),
                // thumbnail_url é estável e gerado especificamente pra preview;
                // image_url pode expirar ou exigir permissão extra de leitura.
                'creative_url'      => $creative['thumbnail_url'] ?? null,
                'raw_data'          => $a,
            ];
        }, $rows);
    }

    private function inferCreativeType(array $creative): ?string
    {
        if (!empty($creative['video_id'])) {
            return 'video';
        }

        if (!empty($creative['object_story_spec']['link_data']['child_attachments'])) {
            return 'carousel';
        }

        if (!empty($creative)) {
            return 'image';
        }

        return null;
    }

    /**
     * Busca insights de 1 dia (usado pelo sync diário). Internamente já é
     * uma "faixa" de 1 dia só — mesma lógica de fetchInsightsRange().
     */
    public function fetchInsights(ClientAdAccount $account, string $accessToken, string $date): array
    {
        return $this->fetchInsightsForRange($account, $accessToken, $date, $date);
    }

    /**
     * Busca insights numa faixa de datas (usado pelo backfill) — uma
     * chamada por nível com `time_increment=1`, em vez de um loop de N
     * chamadas por dia. O Graph API já devolve 1 linha por entidade por dia
     * (com `date_start`), reaproveitando a paginação existente.
     */
    public function fetchInsightsRange(ClientAdAccount $account, string $accessToken, string $sinceDate, string $untilDate): array
    {
        return $this->fetchInsightsForRange($account, $accessToken, $sinceDate, $untilDate);
    }

    /**
     * Busca insights nos 3 níveis (campanha, conjunto, anúncio). Cada nível é
     * isolado num try/catch próprio: uma falha no nível `ad` (o mais sujeito a
     * limite de taxa, por ter muito mais linhas) não deve derrubar os dados de
     * campanha, que hoje são a base de tudo (orçamento, CPA, ROAS).
     */
    private function fetchInsightsForRange(ClientAdAccount $account, string $accessToken, string $since, string $until): array
    {
        $snapshots = [];

        foreach (['campaign', 'adset', 'ad'] as $level) {
            try {
                $snapshots = array_merge($snapshots, $this->fetchInsightsAtLevel($account, $accessToken, $since, $until, $level));
            } catch (\Throwable $e) {
                Log::warning("MetaAdsFetcher::fetchInsights falhou no nível '{$level}' para conta {$account->id}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $this->rollupCampaignConversions($snapshots);
    }

    private function fetchInsightsAtLevel(ClientAdAccount $account, string $accessToken, string $since, string $until, string $level): array
    {
        $idField = match ($level) {
            'adset' => 'adset_id',
            'ad'    => 'ad_id',
            default => 'campaign_id',
        };
        $nameField = str_replace('_id', '_name', $idField);
        $parentIdField = match ($level) {
            'adset' => 'campaign_id',
            'ad'    => 'adset_id',
            default => null,
        };

        $fields = ['campaign_id', 'campaign_name', 'spend', 'impressions', 'clicks', 'actions', 'action_values', 'reach'];
        if ($level !== 'campaign') {
            // optimization_goal só existe no nível conjunto (a campanha não
            // tem um goal próprio) — é o que permite resolver o action_type
            // certo por linha, sem precisar buscar o objeto do conjunto à
            // parte (ver resolveConversions()).
            $fields[] = 'adset_id';
            $fields[] = 'adset_name';
            $fields[] = 'optimization_goal';
        }
        if ($level === 'ad') {
            $fields[] = 'ad_id';
            $fields[] = 'ad_name';
        }

        $rows = $this->fetchAllPages(
            "https://graph.facebook.com/" . self::API_VERSION . "/act_{$account->account_id}/insights",
            [
                'level'          => $level,
                'fields'         => implode(',', array_unique($fields)),
                'time_range'     => json_encode(['since' => $since, 'until' => $until]),
                'time_increment' => 1,
                'limit'          => 500,
                'access_token'   => $accessToken,
            ],
            "MetaAdsFetcher::fetchInsights[{$level}]",
            $account
        );

        return array_map(fn ($row) => [
            'entity_level'     => $level,
            'entity_id'        => $row[$idField],
            'entity_name'      => $row[$nameField] ?? $row['campaign_name'] ?? null,
            'parent_entity_id' => $parentIdField ? ($row[$parentIdField] ?? null) : null,
            'snapshot_date'    => $row['date_start'] ?? $since,
            'spend'            => (float) ($row['spend'] ?? 0),
            'revenue'          => $this->sumActions($row['action_values'] ?? [], ['purchase', 'omni_purchase']),
            'impressions'      => (int) ($row['impressions'] ?? 0),
            'clicks'           => (int) ($row['clicks'] ?? 0),
            'conversions'      => (int) round($this->resolveConversions($row['actions'] ?? [], $row['optimization_goal'] ?? null)),
            'reach'            => (int) ($row['reach'] ?? 0),
            'raw_data'         => $row,
        ], $rows);
    }

    /**
     * A campanha não tem optimization_goal próprio (é conceito do conjunto)
     * — em vez de arriscar uma lista solta pro nível campanha, as
     * conversões/receita da linha de campanha são substituídas pela soma dos
     * conjuntos filhos do mesmo dia, já resolvidos corretamente por
     * optimization_goal. Só sobrescreve quando existe pelo menos 1 conjunto
     * filho naquele dia — sem isso mantém o valor original (fallback).
     */
    private function rollupCampaignConversions(array $snapshots): array
    {
        return collect($snapshots)
            ->groupBy('snapshot_date')
            ->flatMap(function ($dateSnapshots) {
                $adsetTotalsByCampaign = $dateSnapshots
                    ->where('entity_level', 'adset')
                    ->groupBy('parent_entity_id')
                    ->map(fn ($rows) => [
                        'conversions' => $rows->sum('conversions'),
                        'revenue'     => $rows->sum('revenue'),
                    ]);

                return $dateSnapshots->map(function ($row) use ($adsetTotalsByCampaign) {
                    if ($row['entity_level'] === 'campaign' && $adsetTotalsByCampaign->has($row['entity_id'])) {
                        $totals = $adsetTotalsByCampaign->get($row['entity_id']);
                        $row['conversions'] = $totals['conversions'];
                        $row['revenue'] = $totals['revenue'];
                    }

                    return $row;
                });
            })
            ->values()
            ->all();
    }

    private function sumActions(array $actions, array $types): float
    {
        return collect($actions)
            ->filter(fn ($a) => in_array($a['action_type'] ?? null, $types))
            ->sum(fn ($a) => (float) ($a['value'] ?? 0));
    }

    /**
     * Resolve quais action_type contam como "resultado" pra uma linha de
     * insight, com base no optimization_goal do conjunto (ver
     * OPTIMIZATION_GOAL_ACTION_TYPES). Goals de conversão customizada somam
     * por prefixo (não sabemos o evento exato sem promoted_object); goals
     * desconhecidos caem na lista fixa antiga como fallback conservador.
     */
    private function resolveConversions(array $actions, ?string $optimizationGoal): float
    {
        if ($optimizationGoal !== null && in_array($optimizationGoal, self::PREFIX_MATCHED_GOALS, true)) {
            return $this->sumActionsByPrefix($actions, ['purchase', 'omni_purchase'], ['offsite_conversion.', 'onsite_conversion.']);
        }

        $types = self::OPTIMIZATION_GOAL_ACTION_TYPES[$optimizationGoal] ?? null;

        if ($types === null) {
            if ($optimizationGoal !== null) {
                Log::info("MetaAdsFetcher: optimization_goal '{$optimizationGoal}' sem mapeamento de conversão — usando lista padrão");
            }
            $types = self::DEFAULT_CONVERSION_ACTION_TYPES;
        }

        return $this->sumActions($actions, $types);
    }

    private function sumActionsByPrefix(array $actions, array $exactTypes, array $prefixes): float
    {
        return collect($actions)
            ->filter(function ($a) use ($exactTypes, $prefixes) {
                $type = $a['action_type'] ?? '';

                if (in_array($type, $exactTypes, true)) {
                    return true;
                }

                foreach ($prefixes as $prefix) {
                    if (str_starts_with($type, $prefix)) {
                        return true;
                    }
                }

                return false;
            })
            ->sum(fn ($a) => (float) ($a['value'] ?? 0));
    }

    // O Meta devolve `balance`/`amount_spent`/`spend_cap` em centavos (menor
    // unidade da moeda da conta) — a conversão pra reais fica por conta de
    // quem chama (AdDataUpserter::upsertAccountBalance).
    public function fetchAccountBalance(ClientAdAccount $account, string $accessToken): array
    {
        $response = Http::timeout(15)->get("https://graph.facebook.com/" . self::API_VERSION . "/act_{$account->account_id}", [
            'fields'       => 'balance,amount_spent,spend_cap,funding_source_details',
            'access_token' => $accessToken,
        ]);

        if ($response->failed()) {
            throw new \RuntimeException(
                "MetaAdsFetcher::fetchAccountBalance falhou para conta {$account->id} - HTTP {$response->status()}: {$response->body()}"
            );
        }

        return $response->json() ?? [];
    }

    /**
     * Segue `paging.next` do Graph API até esgotar as páginas. Sem isso,
     * contas com mais de 200/500 adsets/ads (fácil de acontecer em conta
     * grande) tinham o restante truncado silenciosamente.
     */
    private function fetchAllPages(string $url, array $params, string $context, ClientAdAccount $account): array
    {
        $rows = [];
        $response = Http::timeout(15)->get($url, $params);

        while (true) {
            if ($response->failed()) {
                throw new \RuntimeException(
                    "{$context} falhou para conta {$account->id} - HTTP {$response->status()}: {$response->body()}"
                );
            }

            $rows = array_merge($rows, $response->json('data', []));
            $nextUrl = $response->json('paging.next');

            if (!$nextUrl) {
                break;
            }

            // paging.next já vem completo (inclusive access_token) — passar
            // params de novo faria o Guzzle sobrescrever a query string e
            // descartar o cursor, gerando um loop infinito na mesma página.
            $response = Http::timeout(15)->get($nextUrl);
        }

        return $rows;
    }
}
