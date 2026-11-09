<?php

namespace App\Services\AdSync;

use App\Models\AdAccountConnection;
use App\Models\ClientAdAccount;
use App\Models\OrganizationIntegration;
use Illuminate\Support\Facades\Http;

/**
 * Contas e conexões: confere, conta por conta, o que o App precisa para
 * criar anúncio e o que a medição precisa para funcionar.
 *
 * Meta: Página e Instagram que a conta pode usar em anúncio (promote_pages),
 * acesso do BM da Nonna e anúncios ativos com UTM fora do padrão.
 * Google: acesso pela MCC, sufixo de URL da conta (UTM) e marcação automática.
 *
 * Os padrões de UTM são os que o time JÁ usa (levantados nas contas em
 * 09/11/2026), não um padrão novo.
 */
class AdAccountConnectionChecker
{
    public const META_UTM = 'utm_source={{site_source_name}}&utm_medium=paid_social&utm_campaign={{campaign.id}}&utm_content={{adset.id}}_{{ad.id}}&utm_term={{placement}}&utm_id={{campaign.id}}';

    public const GOOGLE_UTM = 'utm_source=google-ads&utm_medium=cpc&utm_campaign={campaignid}&utm_content={adgroupid}_{creative}&utm_term={keyword}&utm_id={campaignid}';

    private ?string $metaToken = null;
    private ?string $googleToken = null;
    private array $googleCreds = [];

    public function __construct(private GoogleAdsFetcher $google) {}

    /** Confere uma organização inteira (as contas de clientes ativos). */
    public function checkAll(): array
    {
        $counts = ['ok' => 0, 'atencao' => 0, 'sem_acesso' => 0, 'erro' => 0];

        ClientAdAccount::with('client')
            ->whereIn('platform', ['meta_ads', 'google_ads'])
            ->whereHas('client', fn ($q) => $q->where('status', '!=', 'inactive'))
            ->get()
            ->each(function (ClientAdAccount $account) use (&$counts) {
                $counts[$this->check($account)->status]++;
            });

        return $counts;
    }

    public function check(ClientAdAccount $account): AdAccountConnection
    {
        try {
            $data = $account->platform === 'meta_ads' ? $this->checkMeta($account) : $this->checkGoogle($account);
        } catch (\Throwable $e) {
            report($e);
            $data = ['status' => 'erro', 'issues' => ['A verificação falhou: ' . mb_substr($e->getMessage(), 0, 150)]];
        }

        return AdAccountConnection::updateOrCreate(
            ['client_ad_account_id' => $account->id],
            $data + ['checked_at' => now()]
        );
    }

    /**
     * Aplica o padrão de UTM no sufixo de URL da conta Google inteira — vale
     * para todas as campanhas, inclusive as que já estão no ar, sem mexer
     * nelas. $validateOnly confere sem gravar.
     */
    public function applyGoogleUtm(ClientAdAccount $account, bool $validateOnly = false): array
    {
        $cid = preg_replace('/\D/', '', $account->account_id);
        $response = $this->googleRequest()->post(
            'https://googleads.googleapis.com/' . GoogleAdsFetcher::API_VERSION . "/customers/{$cid}:mutate",
            [
                'operation'    => [
                    'update'     => ['resourceName' => "customers/{$cid}", 'finalUrlSuffix' => self::GOOGLE_UTM],
                    'updateMask' => 'final_url_suffix',
                ],
                'validateOnly' => $validateOnly,
            ]
        );

        return ['ok' => $response->successful(), 'status' => $response->status(), 'error' => $response->failed() ? $this->googleError($response) : null];
    }

    private function checkMeta(ClientAdAccount $account): array
    {
        $act = 'act_' . $account->account_id;
        $issues = [];

        $pages = $this->meta("{$act}/promote_pages", ['fields' => 'id,name,instagram_business_account{id,username}', 'limit' => 25]);
        if (isset($pages['error'])) {
            return ['status' => 'sem_acesso', 'issues' => ['O BM da Nonna não tem acesso a esta conta na Meta (a conta não foi compartilhada, ou o acesso foi removido). Sem isso a sincronização também não traz dados.']];
        }

        $page = $pages['data'][0] ?? null;
        $instagram = $page['instagram_business_account'] ?? null;

        if (!$page) {
            $issues[] = 'Nenhuma Página liberada para o App nesta conta. Os anúncios atuais seguem normais, mas o App não consegue criar anúncio: compartilhe a Página do cliente com o BM da Nonna e dê acesso ao usuário do sistema "Nonna - n8n".';
        }
        if (!$instagram) {
            $igAccounts = $this->meta("{$act}/instagram_accounts", ['fields' => 'id,username', 'limit' => 5]);
            $instagram = $igAccounts['data'][0] ?? null;
        }
        if (!$instagram) {
            $issues[] = 'Nenhum Instagram liberado para o App: anúncio criado por aqui sairia só pela Página. Ligue o Instagram do cliente à Página e à conta de anúncio no BM.';
        }

        // UTM dos anúncios ativos (amostra de até 100).
        $ads = $this->meta("{$act}/ads", ['fields' => 'creative{url_tags}', 'effective_status' => json_encode(['ACTIVE']), 'limit' => 100]);
        $checked = count($ads['data'] ?? []);
        $off = collect($ads['data'] ?? [])->filter(fn ($ad) => trim($ad['creative']['url_tags'] ?? '') !== self::META_UTM)->count();
        if ($off > 0) {
            $issues[] = "{$off} de {$checked} anúncios ativos estão sem a UTM padrão (ou com erro de digitação). Leads desses anúncios chegam sem origem completa.";
        }

        return [
            'status'             => $page ? ($issues ? 'atencao' : 'ok') : 'atencao',
            'issues'             => $issues,
            'page_id'            => $page['id'] ?? null,
            'page_name'          => $page['name'] ?? null,
            'instagram_id'       => $instagram['id'] ?? null,
            'instagram_username' => $instagram['username'] ?? null,
            'utm_status'         => $checked === 0 ? null : ($off === 0 ? 'ok' : 'diferente'),
            'ads_checked'        => $checked,
            'ads_off_standard'   => $off,
        ];
    }

    private function checkGoogle(ClientAdAccount $account): array
    {
        $cid = preg_replace('/\D/', '', $account->account_id);
        $response = $this->googleRequest()->post(
            'https://googleads.googleapis.com/' . GoogleAdsFetcher::API_VERSION . "/customers/{$cid}/googleAds:search",
            ['query' => 'SELECT customer.final_url_suffix, customer.auto_tagging_enabled FROM customer']
        );

        if ($response->status() === 403 || $response->status() === 401) {
            return ['status' => 'sem_acesso', 'issues' => ['A conta gerente (MCC) da Nonna não tem acesso a esta conta no Google Ads.']];
        }
        if ($response->failed()) {
            return ['status' => 'erro', 'issues' => ['O Google não respondeu: ' . $this->googleError($response)]];
        }

        $customer = $response->json('results.0.customer') ?? [];
        $suffix = trim($customer['finalUrlSuffix'] ?? '');
        $autoTag = (bool) ($customer['autoTaggingEnabled'] ?? false);
        $issues = [];

        $utm = $suffix === '' ? 'faltando' : ($suffix === self::GOOGLE_UTM ? 'ok' : 'diferente');
        if ($utm === 'faltando') {
            $issues[] = 'A conta não tem UTM automática: os cliques chegam no site sem origem.';
        } elseif ($utm === 'diferente') {
            $issues[] = 'A UTM da conta está diferente do padrão da Nonna.';
        }
        if (!$autoTag) {
            $issues[] = 'A marcação automática (gclid) está desligada: o Google não consegue ligar a conversão ao clique.';
        }

        return [
            'status'       => $issues ? 'atencao' : 'ok',
            'issues'       => $issues,
            'utm_status'   => $utm,
            'utm_current'  => $suffix ?: null,
            'auto_tagging' => $autoTag,
        ];
    }

    private function meta(string $path, array $query = []): array
    {
        $this->metaToken ??= OrganizationIntegration::where('provider', 'meta')->where('status', 'connected')->first()?->credentials['access_token'];
        if (!$this->metaToken) {
            return ['error' => ['message' => 'Integração com a Meta não está conectada.']];
        }

        return Http::timeout(20)
            ->get('https://graph.facebook.com/' . MetaAdsFetcher::API_VERSION . '/' . $path, $query + ['access_token' => $this->metaToken])
            ->json() ?? ['error' => ['message' => 'Resposta vazia da Meta.']];
    }

    private function googleRequest()
    {
        if (!$this->googleToken) {
            $integration = OrganizationIntegration::where('provider', 'google')->where('status', 'connected')->first();
            if (!$integration) {
                throw new \RuntimeException('Integração com o Google Ads não está conectada.');
            }
            $this->googleToken = $this->google->ensureFreshToken($integration);
            $this->googleCreds = $integration->credentials ?? [];
        }

        return Http::timeout(20)->withToken($this->googleToken)->withHeaders([
            'developer-token'   => $this->googleCreds['developer_token'] ?? '',
            'login-customer-id' => preg_replace('/\D/', '', $this->googleCreds['login_customer_id'] ?? ($this->googleCreds['customer_id'] ?? '')),
        ]);
    }

    private function googleError($response): string
    {
        return mb_substr((string) ($response->json('error.details.0.errors.0.message') ?? $response->json('error.message') ?? $response->body()), 0, 200);
    }
}
