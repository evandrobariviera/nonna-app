<?php

namespace App\Services\AdSync;

use App\Models\ClientAdAccount;
use App\Models\OrganizationIntegration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleAdsFetcher
{
    private const API_VERSION = 'v24';

    /**
     * Garante um access_token válido, renovando via refresh_token se estiver
     * vencido (ou perto de vencer). Atualiza as credenciais da integração.
     */
    public function ensureFreshToken(OrganizationIntegration $integration): ?string
    {
        $creds = $integration->credentials ?? [];

        $expiresAt = $creds['token_expires_at'] ?? null;
        $stillValid = $expiresAt && now()->addSeconds(60)->lt($expiresAt);

        if ($stillValid && !empty($creds['access_token'])) {
            return $creds['access_token'];
        }

        if (empty($creds['refresh_token']) || empty($creds['client_id']) || empty($creds['client_secret'])) {
            Log::warning('GoogleAdsFetcher::ensureFreshToken sem refresh_token/client_id/client_secret configurados', [
                'integration_id' => $integration->id,
            ]);
            return null;
        }

        $response = Http::timeout(15)->asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id'     => $creds['client_id'],
            'client_secret' => $creds['client_secret'],
            'refresh_token' => $creds['refresh_token'],
            'grant_type'    => 'refresh_token',
        ]);

        if ($response->failed()) {
            Log::warning('GoogleAdsFetcher::ensureFreshToken falhou ao renovar token', [
                'integration_id' => $integration->id,
                'status'         => $response->status(),
                'body'           => $response->body(),
            ]);
            return null;
        }

        $accessToken = $response->json('access_token');
        $expiresIn = (int) $response->json('expires_in', 3600);

        $creds['access_token'] = $accessToken;
        $creds['token_expires_at'] = now()->addSeconds($expiresIn)->toIso8601String();
        $integration->update(['credentials' => $creds]);

        return $accessToken;
    }

    public function fetchCampaignsAndMetrics(ClientAdAccount $account, string $accessToken, array $googleCredentials, string $date): array
    {
        $customerId = preg_replace('/-/', '', $account->account_id);
        $loginCustomerId = preg_replace('/-/', '', $googleCredentials['login_customer_id'] ?? ($googleCredentials['customer_id'] ?? ''));

        $query = "SELECT campaign.id, campaign.name, campaign.status, campaign.advertising_channel_type, "
            . "campaign.start_date, campaign.end_date, metrics.cost_micros, metrics.impressions, "
            . "metrics.clicks, metrics.conversions, metrics.conversions_value "
            . "FROM campaign WHERE segments.date = '{$date}'";

        $response = Http::timeout(15)->withHeaders([
            'Authorization'     => "Bearer {$accessToken}",
            'developer-token'   => $googleCredentials['developer_token'] ?? '',
            'login-customer-id' => $loginCustomerId,
        ])->post("https://googleads.googleapis.com/" . self::API_VERSION . "/customers/{$customerId}/googleAds:search", [
            'query' => $query,
        ]);

        if ($response->failed()) {
            throw new \RuntimeException(
                "GoogleAdsFetcher: falha ao buscar conta {$account->id} - HTTP {$response->status()}: {$response->body()}"
            );
        }

        $rows = $response->json('results', []);

        $campaigns = array_map(fn ($r) => [
            'external_id' => (string) $r['campaign']['id'],
            'name'        => $r['campaign']['name'],
            'status'      => strtolower($r['campaign']['status'] ?? 'enabled'),
            'objective'   => $r['campaign']['advertisingChannelType'] ?? null,
            'start_date'  => $r['campaign']['startDate'] ?? null,
            'end_date'    => $r['campaign']['endDate'] ?? null,
            'raw_data'    => $r['campaign'],
        ], $rows);

        $snapshots = array_map(fn ($r) => [
            'entity_level' => 'campaign',
            'entity_id'    => (string) $r['campaign']['id'],
            'entity_name'  => $r['campaign']['name'],
            'spend'        => ((int) ($r['metrics']['costMicros'] ?? 0)) / 1000000,
            'revenue'      => (float) ($r['metrics']['conversionsValue'] ?? 0),
            'impressions'  => (int) ($r['metrics']['impressions'] ?? 0),
            'clicks'       => (int) ($r['metrics']['clicks'] ?? 0),
            'conversions'  => (int) round((float) ($r['metrics']['conversions'] ?? 0)),
            'reach'        => 0,
            'raw_data'     => $r['metrics'],
        ], $rows);

        return ['campaigns' => $campaigns, 'snapshots' => $snapshots];
    }
}
