<?php

namespace App\Services\AdSync;

use App\Models\ClientAdAccount;
use Illuminate\Support\Facades\Http;

class MetaAdsFetcher
{
    private const API_VERSION = 'v20.0';

    public function fetchCampaigns(ClientAdAccount $account, string $accessToken): array
    {
        $response = Http::timeout(15)->get("https://graph.facebook.com/" . self::API_VERSION . "/act_{$account->account_id}/campaigns", [
            'fields'       => 'id,name,status,objective,start_time,stop_time',
            'limit'        => 200,
            'access_token' => $accessToken,
        ]);

        if ($response->failed()) {
            throw new \RuntimeException(
                "MetaAdsFetcher::fetchCampaigns falhou para conta {$account->id} - HTTP {$response->status()}: {$response->body()}"
            );
        }

        $campaigns = $response->json('data', []);

        return array_map(fn ($c) => [
            'external_id' => $c['id'],
            'name'        => $c['name'],
            'status'      => strtolower($c['status'] ?? 'active'),
            'objective'   => $c['objective'] ?? null,
            'start_date'  => isset($c['start_time']) ? substr($c['start_time'], 0, 10) : null,
            'end_date'    => isset($c['stop_time']) ? substr($c['stop_time'], 0, 10) : null,
            'raw_data'    => $c,
        ], $campaigns);
    }

    public function fetchInsights(ClientAdAccount $account, string $accessToken, string $date): array
    {
        $response = Http::timeout(15)->get("https://graph.facebook.com/" . self::API_VERSION . "/act_{$account->account_id}/insights", [
            'level'      => 'campaign',
            'fields'     => 'campaign_id,campaign_name,spend,impressions,clicks,actions,action_values,reach',
            'time_range' => json_encode(['since' => $date, 'until' => $date]),
            'limit'      => 500,
            'access_token' => $accessToken,
        ]);

        if ($response->failed()) {
            throw new \RuntimeException(
                "MetaAdsFetcher::fetchInsights falhou para conta {$account->id} - HTTP {$response->status()}: {$response->body()}"
            );
        }

        $rows = $response->json('data', []);

        return array_map(fn ($row) => [
            'entity_level' => 'campaign',
            'entity_id'    => $row['campaign_id'],
            'entity_name'  => $row['campaign_name'],
            'spend'        => (float) ($row['spend'] ?? 0),
            'revenue'      => $this->sumActions($row['action_values'] ?? [], ['purchase', 'omni_purchase']),
            'impressions'  => (int) ($row['impressions'] ?? 0),
            'clicks'       => (int) ($row['clicks'] ?? 0),
            'conversions'  => (int) round($this->sumActions($row['actions'] ?? [], ['purchase', 'omni_purchase', 'lead', 'offsite_conversion.fb_pixel_purchase'])),
            'reach'        => (int) ($row['reach'] ?? 0),
            'raw_data'     => $row,
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
}
