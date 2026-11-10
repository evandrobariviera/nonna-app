<?php

namespace App\Services\AdSync;

use App\Models\ClientAdAccount;
use App\Models\OrganizationIntegration;
use Illuminate\Support\Facades\Http;

/**
 * Chamadas de leitura e escrita na Google Ads API pela conta gerente (MCC) da
 * Nonna. Usado pelo Controle no ar (CampaignControls) e pelos anúncios de
 * Pesquisa (GoogleSearchAdCreator). O sync de métricas segue no GoogleAdsFetcher.
 */
class GoogleAdsApi
{
    private ?array $auth = null;

    public function __construct(private GoogleAdsFetcher $fetcher) {}

    public static function cid(ClientAdAccount|string $account): string
    {
        return preg_replace('/\D/', '', $account instanceof ClientAdAccount ? $account->account_id : $account);
    }

    /** @return array<int, array> linhas do googleAds:search */
    public function search(string $cid, string $query): array
    {
        $res = $this->request()->post($this->url($cid, 'googleAds:search'), ['query' => $query]);
        if ($res->failed()) {
            throw new \RuntimeException('O Google não respondeu: ' . self::errorText($res->json() ?? [], $res->body()));
        }

        return $res->json('results') ?? [];
    }

    /**
     * @return array{ok: bool, results: array, error: ?string}
     */
    public function mutate(string $cid, string $service, array $operations, bool $validateOnly = false): array
    {
        $res = $this->request()->post($this->url($cid, "{$service}:mutate"), [
            'operations'   => $operations,
            'validateOnly' => $validateOnly,
        ]);

        return [
            'ok'      => $res->successful(),
            'results' => $res->json('results') ?? [],
            'error'   => $res->failed() ? self::errorText($res->json() ?? [], $res->body()) : null,
        ];
    }

    /**
     * Conversão importada (lead/venda vindo do App, pelo gclid). Erro parcial
     * do Google (ex.: clique antigo, gclid de outra conta) volta como erro.
     *
     * @return array{ok: bool, error: ?string}
     */
    public function uploadClickConversions(string $cid, array $conversions, bool $validateOnly = false): array
    {
        $res = $this->request()->post('https://googleads.googleapis.com/' . GoogleAdsFetcher::API_VERSION . "/customers/{$cid}:uploadClickConversions", [
            'conversions'    => array_map(fn ($c) => array_filter($c, fn ($v) => $v !== null), $conversions),
            'partialFailure' => true,
            'validateOnly'   => $validateOnly,
        ]);
        if ($res->failed()) {
            return ['ok' => false, 'error' => self::errorText($res->json() ?? [], $res->body())];
        }
        $partial = $res->json('partialFailureError');

        return $partial
            ? ['ok' => false, 'error' => self::errorText(['error' => $partial], json_encode($partial))]
            : ['ok' => true, 'error' => null];
    }

    /**
     * Cria a conversão "importada" que recebe os leads/vendas do App.
     *
     * @return array{ok: bool, resource: ?string, error: ?string}
     */
    public function createUploadConversionAction(string $cid, string $name, string $category, bool $validateOnly = false): array
    {
        $res = $this->mutate($cid, 'conversionActions', [[
            'create' => [
                'name'                          => $name,
                'type'                          => 'UPLOAD_CLICKS',
                'category'                      => $category, // SUBMIT_LEAD_FORM | PURCHASE | QUALIFIED_LEAD
                'status'                        => 'ENABLED',
                'countingType'                  => 'ONE_PER_CLICK',
                'clickThroughLookbackWindowDays' => 90,
                'valueSettings'                 => ['defaultValue' => 0, 'defaultCurrencyCode' => 'BRL', 'alwaysUseDefaultValue' => false],
            ],
        ]], $validateOnly);

        return ['ok' => $res['ok'], 'resource' => $res['results'][0]['resourceName'] ?? null, 'error' => $res['error']];
    }

    /**
     * Várias operações numa chamada só (googleAds:mutate), com nomes
     * temporários (ex.: assets/-1) ligando uma operação à outra. Tudo ou nada.
     *
     * @return array{ok: bool, results: array, error: ?string}
     */
    public function mutateMany(string $cid, array $mutateOperations, bool $validateOnly = false): array
    {
        $res = $this->request(180)->post($this->url($cid, 'googleAds:mutate'), [
            'mutateOperations' => $mutateOperations,
            'validateOnly'     => $validateOnly,
        ]);

        return [
            'ok'      => $res->successful(),
            'results' => $res->json('mutateOperationResponses') ?? [],
            'error'   => $res->failed() ? self::errorText($res->json() ?? [], $res->body()) : null,
        ];
    }

    /**
     * Junta as mensagens do Google em português claro, incluindo o tema de
     * política quando o anúncio é barrado (ex.: "Saúde em anúncios").
     */
    public static function errorText(array $json, string $raw = ''): string
    {
        $errors = $json['error']['details'][0]['errors'] ?? [];
        if (!$errors) {
            return mb_substr((string) ($json['error']['message'] ?? $raw), 0, 300);
        }

        return collect($errors)->map(function ($e) {
            $msg = $e['message'] ?? 'erro';
            if (!empty($e['trigger']['stringValue'])) {
                $msg .= ' ("' . $e['trigger']['stringValue'] . '")';
            }
            $topics = collect($e['details']['policyFindingDetails']['policyTopicEntries'] ?? [])->pluck('topic')->filter()->implode(', ');

            return $topics ? "{$msg} — política: {$topics}" : $msg;
        })->unique()->take(4)->implode(' · ');
    }

    private function url(string $cid, string $path): string
    {
        return 'https://googleads.googleapis.com/' . GoogleAdsFetcher::API_VERSION . "/customers/{$cid}/{$path}";
    }

    private function request(int $timeout = 30)
    {
        if (!$this->auth) {
            $integration = OrganizationIntegration::where('provider', 'google')->where('status', 'connected')->first()
                ?? throw new \RuntimeException('Integração com o Google Ads não está conectada.');
            $creds = $integration->credentials ?? [];
            $this->auth = [
                'token'   => $this->fetcher->ensureFreshToken($integration),
                'headers' => [
                    'developer-token'   => $creds['developer_token'] ?? '',
                    'login-customer-id' => preg_replace('/\D/', '', $creds['login_customer_id'] ?? ($creds['customer_id'] ?? '')),
                ],
            ];
        }

        return Http::timeout($timeout)->withToken((string) $this->auth['token'])->withHeaders($this->auth['headers']);
    }
}
