<?php

namespace App\Services\Tracking;

use App\Http\Controllers\GoogleOAuthController;
use App\Models\OrganizationIntegration;
use App\Services\AdSync\GoogleAdsFetcher;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Lê os números do site do cliente com o mesmo login Google da agência:
 * - GA4 (Analytics Data API): visitas, pessoas e conversões por canal;
 * - Search Console: cliques e impressões no Google, buscas e páginas que mais trazem gente.
 *
 * Só leitura. Funciona quando o Google foi conectado com as permissões
 * analytics.readonly e webmasters.readonly (ver GoogleOAuthController::SCOPES).
 * Tudo fica em cache por 6 horas: os números do Google não mudam a cada minuto
 * e cada consulta conta na cota diária.
 */
class GoogleSiteReports
{
    public const GA4 = 'https://www.googleapis.com/auth/analytics.readonly';
    public const GSC = 'https://www.googleapis.com/auth/webmasters.readonly';

    private const TTL = 21600;

    public function __construct(private GoogleAdsFetcher $fetcher) {}

    /** O login do Google tem essa permissão? */
    public function can(string $scope): bool
    {
        $integration = $this->integration();
        if (!$integration) {
            return false;
        }
        $granted = $integration->credentials['granted_scopes'] ?? null;
        if (is_array($granted)) {
            return in_array($scope, $granted, true);
        }

        // Conexão antiga (antes de guardarmos as permissões): pergunta ao Google.
        $scopes = Cache::remember("google_token_scopes:{$integration->id}", 3600, function () use ($integration) {
            $token = $this->fetcher->ensureFreshToken($integration);

            return $token ? explode(' ', (string) Http::timeout(10)->get('https://oauth2.googleapis.com/tokeninfo', ['access_token' => $token])->json('scope')) : [];
        });

        return in_array($scope, $scopes, true);
    }

    /** @return list<array{id: string, name: string, account: string}> propriedades GA4 que o login alcança */
    public function ga4Properties(): array
    {
        if (!$this->can(self::GA4)) {
            return [];
        }

        return Cache::remember('google_ga4_properties', 3600, function () {
            $out = [];
            $page = null;
            do {
                $res = $this->http()->get('https://analyticsadmin.googleapis.com/v1beta/accountSummaries', array_filter(['pageSize' => 200, 'pageToken' => $page]));
                if ($res->failed()) {
                    break;
                }
                foreach ($res->json('accountSummaries') ?? [] as $acc) {
                    foreach ($acc['propertySummaries'] ?? [] as $p) {
                        $out[] = ['id' => str_replace('properties/', '', $p['property']), 'name' => $p['displayName'], 'account' => $acc['displayName'] ?? ''];
                    }
                }
                $page = $res->json('nextPageToken');
            } while ($page);

            return $out;
        });
    }

    /** @return list<string> sites do Search Console que o login alcança */
    public function searchConsoleSites(): array
    {
        if (!$this->can(self::GSC)) {
            return [];
        }

        return Cache::remember('google_gsc_sites', 3600, fn () => collect($this->http()->get('https://www.googleapis.com/webmasters/v3/sites')->json('siteEntry') ?? [])
            ->reject(fn ($s) => ($s['permissionLevel'] ?? '') === 'siteUnverifiedUser')
            ->pluck('siteUrl')->sort()->values()->all());
    }

    /**
     * Resumo do GA4 nos últimos N dias.
     *
     * @return array{ok: bool, error?: string, totals?: array, channels?: list<array>, pages?: list<array>}
     */
    public function ga4Summary(string $propertyId, int $days = 28): array
    {
        if (!$this->can(self::GA4)) {
            return ['ok' => false, 'error' => 'O login do Google ainda não tem permissão de ler o Analytics. Reconecte o Google em Configurações → Integrações.'];
        }

        return Cache::remember("ga4_summary:{$propertyId}:{$days}", self::TTL, function () use ($propertyId, $days) {
            $range = [['startDate' => "{$days}daysAgo", 'endDate' => 'yesterday']];
            $url = "https://analyticsdata.googleapis.com/v1beta/properties/{$propertyId}:batchRunReports";
            $metrics = [['name' => 'sessions'], ['name' => 'totalUsers'], ['name' => 'keyEvents'], ['name' => 'engagementRate']];

            $res = $this->http()->post($url, ['requests' => [
                ['dateRanges' => $range, 'metrics' => $metrics],
                ['dateRanges' => $range, 'metrics' => $metrics, 'dimensions' => [['name' => 'sessionDefaultChannelGroup']], 'orderBys' => [['metric' => ['metricName' => 'sessions'], 'desc' => true]], 'limit' => 8],
                ['dateRanges' => $range, 'metrics' => [['name' => 'sessions'], ['name' => 'keyEvents']], 'dimensions' => [['name' => 'landingPage']], 'orderBys' => [['metric' => ['metricName' => 'sessions'], 'desc' => true]], 'limit' => 10],
            ]]);
            if ($res->failed()) {
                return ['ok' => false, 'error' => $this->error($res->json(), $res->status())];
            }

            [$total, $byChannel, $byPage] = array_pad($res->json('reports') ?? [], 3, []);
            $row = fn ($r) => array_map(fn ($m) => (float) $m['value'], $r['metricValues'] ?? []);
            $t = $row(($total['rows'] ?? [[]])[0] ?? []);

            return [
                'ok'       => true,
                'totals'   => ['sessions' => (int) ($t[0] ?? 0), 'users' => (int) ($t[1] ?? 0), 'key_events' => (int) ($t[2] ?? 0), 'engagement' => round(($t[3] ?? 0) * 100)],
                'channels' => collect($byChannel['rows'] ?? [])->map(fn ($r) => ['name' => $r['dimensionValues'][0]['value'], 'sessions' => (int) $row($r)[0], 'key_events' => (int) $row($r)[2]])->all(),
                'pages'    => collect($byPage['rows'] ?? [])->map(fn ($r) => ['page' => $r['dimensionValues'][0]['value'], 'sessions' => (int) $row($r)[0], 'key_events' => (int) $row($r)[1]])->all(),
            ];
        });
    }

    /**
     * Resumo do Search Console nos últimos N dias (o Google leva ~2 dias para fechar os números).
     *
     * @return array{ok: bool, error?: string, totals?: array, queries?: list<array>, pages?: list<array>}
     */
    public function searchConsoleSummary(string $site, int $days = 28): array
    {
        if (!$this->can(self::GSC)) {
            return ['ok' => false, 'error' => 'O login do Google ainda não tem permissão de ler o Search Console. Reconecte o Google em Configurações → Integrações.'];
        }

        return Cache::remember('gsc_summary:' . md5($site) . ":{$days}", self::TTL, function () use ($site, $days) {
            $url = 'https://www.googleapis.com/webmasters/v3/sites/' . rawurlencode($site) . '/searchAnalytics/query';
            $base = ['startDate' => now()->subDays($days + 2)->toDateString(), 'endDate' => now()->subDays(2)->toDateString()];

            $total = $this->http()->post($url, $base);
            if ($total->failed()) {
                return ['ok' => false, 'error' => $this->error($total->json(), $total->status())];
            }
            $queries = $this->http()->post($url, $base + ['dimensions' => ['query'], 'rowLimit' => 10])->json('rows') ?? [];
            $pages = $this->http()->post($url, $base + ['dimensions' => ['page'], 'rowLimit' => 10])->json('rows') ?? [];
            $t = ($total->json('rows') ?? [[]])[0] ?? [];
            $map = fn ($r) => ['key' => $r['keys'][0], 'clicks' => (int) $r['clicks'], 'impressions' => (int) $r['impressions'], 'position' => round($r['position'], 1)];

            return [
                'ok'      => true,
                'totals'  => ['clicks' => (int) ($t['clicks'] ?? 0), 'impressions' => (int) ($t['impressions'] ?? 0), 'ctr' => round(($t['ctr'] ?? 0) * 100, 1), 'position' => round($t['position'] ?? 0, 1)],
                'queries' => array_map($map, $queries),
                'pages'   => array_map($map, $pages),
            ];
        });
    }

    private function integration(): ?OrganizationIntegration
    {
        return OrganizationIntegration::where('provider', 'google')->where('status', 'connected')->first();
    }

    private function http()
    {
        $token = $this->fetcher->ensureFreshToken($this->integration());

        return Http::timeout(30)->withToken((string) $token)->acceptJson();
    }

    private function error(?array $json, int $status): string
    {
        $msg = $json['error']['message'] ?? "erro {$status}";
        if ($status === 403 && str_contains($msg, 'has not been used')) {
            return 'A API ainda não foi ativada no projeto do Google Cloud da agência: ' . $msg;
        }
        if ($status === 403) {
            return 'O login do Google não tem acesso a esta propriedade/site. Peça ao cliente para dar acesso de leitura ao e-mail da agência.';
        }

        return $msg;
    }
}
