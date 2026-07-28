<?php

namespace App\Services\AdSync;

use App\Models\ClientAdAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaAdsFetcher
{
    private const API_VERSION = 'v20.0';

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
                'fields'       => 'id,name,status,adset_id,creative{id,thumbnail_url,video_id,object_story_spec,image_url}',
                'limit'        => 200,
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
     * Busca insights nos 3 níveis (campanha, conjunto, anúncio). Cada nível é
     * isolado num try/catch próprio: uma falha no nível `ad` (o mais sujeito a
     * limite de taxa, por ter muito mais linhas) não deve derrubar os dados de
     * campanha, que hoje são a base de tudo (orçamento, CPA, ROAS).
     */
    public function fetchInsights(ClientAdAccount $account, string $accessToken, string $date): array
    {
        $snapshots = [];

        foreach (['campaign', 'adset', 'ad'] as $level) {
            try {
                $snapshots = array_merge($snapshots, $this->fetchInsightsAtLevel($account, $accessToken, $date, $level));
            } catch (\Throwable $e) {
                Log::warning("MetaAdsFetcher::fetchInsights falhou no nível '{$level}' para conta {$account->id}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $snapshots;
    }

    private function fetchInsightsAtLevel(ClientAdAccount $account, string $accessToken, string $date, string $level): array
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
            $fields[] = 'adset_id';
            $fields[] = 'adset_name';
        }
        if ($level === 'ad') {
            $fields[] = 'ad_id';
            $fields[] = 'ad_name';
        }

        $rows = $this->fetchAllPages(
            "https://graph.facebook.com/" . self::API_VERSION . "/act_{$account->account_id}/insights",
            [
                'level'      => $level,
                'fields'     => implode(',', array_unique($fields)),
                'time_range' => json_encode(['since' => $date, 'until' => $date]),
                'limit'      => 500,
                'access_token' => $accessToken,
            ],
            "MetaAdsFetcher::fetchInsights[{$level}]",
            $account
        );

        return array_map(fn ($row) => [
            'entity_level'     => $level,
            'entity_id'        => $row[$idField],
            'entity_name'      => $row[$nameField] ?? $row['campaign_name'] ?? null,
            'parent_entity_id' => $parentIdField ? ($row[$parentIdField] ?? null) : null,
            'spend'            => (float) ($row['spend'] ?? 0),
            'revenue'          => $this->sumActions($row['action_values'] ?? [], ['purchase', 'omni_purchase']),
            'impressions'      => (int) ($row['impressions'] ?? 0),
            'clicks'           => (int) ($row['clicks'] ?? 0),
            'conversions'      => (int) round($this->sumActions($row['actions'] ?? [], ['purchase', 'omni_purchase', 'lead', 'offsite_conversion.fb_pixel_purchase'])),
            'reach'            => (int) ($row['reach'] ?? 0),
            'raw_data'         => $row,
        ], $rows);
    }

    private function sumActions(array $actions, array $types): float
    {
        return collect($actions)
            ->filter(fn ($a) => in_array($a['action_type'] ?? null, $types))
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
