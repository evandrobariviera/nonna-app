<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientAdAccount;
use App\Models\ClientLeadOpportunity;
use App\Models\ClientTracking;
use App\Models\ConversionEvent;
use App\Models\OrganizationIntegration;
use App\Services\AdSync\GoogleAdsApi;
use App\Services\AdSync\MetaAdsFetcher;
use App\Services\Billing\AccountStatement;
use App\Services\Tracking\ConversionSender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * Central de Campanhas → Rastreamento: para onde vão as conversões de cada
 * cliente (Pixel da Meta, Google Ads, GA4). Substitui o GTM.
 */
class TrackingController extends Controller
{
    public function index(Request $request): View
    {
        $clientId = $request->get('client_id');
        $clients = AccountStatement::clientsWithAccounts();
        $trackings = ClientTracking::whereIn('client_id', $clients->pluck('id'))->get()->keyBy('client_id');
        $last = ConversionEvent::selectRaw('client_id, max(created_at) as last_at, count(*) filter (where status = \'erro\') as errors')
            ->where('created_at', '>=', now()->subDays(7))->groupBy('client_id')->get()->keyBy('client_id');

        $client = $clientId && $clientId !== 'meus' ? $clients->firstWhere('id', $clientId) : null;
        $detail = $client ? $this->detail(Client::find($client->id)) : null;

        return view('tracking.index', compact('clients', 'trackings', 'last', 'client', 'detail', 'clientId'));
    }

    public function update(Request $request, Client $client): RedirectResponse
    {
        $v = Validator::make($request->all(), [
            'meta_pixel_id'          => 'nullable|regex:/^\d{6,20}$/',
            'meta_test_event_code'   => 'nullable|string|max:30',
            'ga4_measurement_id'     => 'nullable|regex:/^G-[A-Z0-9]{4,15}$/',
            'ga4_api_secret'         => 'nullable|string|max:120',
            'google_ads_account_id'  => 'nullable|uuid',
            'google_lead_action'     => 'nullable|string|max:120',
            'google_purchase_action' => 'nullable|string|max:120',
            'clarity_id'             => 'nullable|string|max:30',
            'send_enabled'           => 'nullable|boolean',
        ], ['ga4_measurement_id.regex' => 'O ID do GA4 começa com G- (ex.: G-AB12CD34EF).', 'meta_pixel_id.regex' => 'O ID do Pixel tem só números.']);
        if ($v->fails()) {
            return back()->withErrors($v)->withInput();
        }

        $t = ClientTracking::forClient($client);
        $data = $request->only(['meta_pixel_id', 'meta_test_event_code', 'ga4_measurement_id', 'google_ads_account_id', 'google_lead_action', 'google_purchase_action', 'clarity_id']);
        if ($request->filled('ga4_api_secret')) {
            $data['ga4_api_secret'] = $request->input('ga4_api_secret'); // vazio = mantém o que já estava
        }
        $data['send_enabled'] = $request->boolean('send_enabled');
        $t->update($data);

        return redirect()->route('tracking.index', ['client_id' => $client->id])
            ->with('success', $t->send_enabled ? 'Rastreamento salvo. Os leads novos deste cliente já vão para as plataformas.' : 'Rastreamento salvo. Os envios estão desligados até você ligar.');
    }

    /** Cria a conversão "importada" no Google Ads do cliente (lead ou venda). */
    public function createGoogleAction(Request $request, Client $client, GoogleAdsApi $google): RedirectResponse
    {
        $kind = $request->input('kind') === 'venda' ? 'venda' : 'lead';
        $t = ClientTracking::forClient($client);
        $account = ClientAdAccount::where('client_id', $client->id)->where('platform', 'google_ads')->find($request->input('google_ads_account_id') ?: $t->google_ads_account_id);
        if (!$account) {
            return back()->withErrors(['tracking' => 'Escolha a conta do Google Ads do cliente.']);
        }

        $res = $google->createUploadConversionAction(GoogleAdsApi::cid($account), $kind === 'venda' ? 'Venda — Nonna App' : 'Lead — Nonna App', $kind === 'venda' ? 'PURCHASE' : 'SUBMIT_LEAD_FORM');
        if (!$res['ok']) {
            return back()->withErrors(['tracking' => 'O Google não criou a conversão: ' . $res['error']]);
        }

        $t->update(['google_ads_account_id' => $account->id, $kind === 'venda' ? 'google_purchase_action' : 'google_lead_action' => $res['resource']]);
        Cache::forget("tracking_google_actions:{$account->id}");

        return back()->with('success', 'Conversão "' . ($kind === 'venda' ? 'Venda' : 'Lead') . ' — Nonna App" criada no Google Ads e ligada a este cliente.');
    }

    /** Manda o lead mais recente (até 7 dias) no modo teste da Meta. */
    public function testSend(Client $client, ConversionSender $sender): RedirectResponse
    {
        $t = ClientTracking::where('client_id', $client->id)->first();
        if (!$t?->meta_pixel_id || !$t->isTestMode()) {
            return back()->withErrors(['tracking' => 'Preencha o Pixel e o código de teste (aba "Eventos de teste" do Gerenciador de Eventos) e salve antes.']);
        }
        $opp = ClientLeadOpportunity::with('lead', 'channel')->whereHas('lead', fn ($q) => $q->where('client_id', $client->id))
            ->whereHas('channel', fn ($q) => $q->where('kind', 'site'))->where('received_at', '>=', now()->subDays(7))->latest('received_at')->first();
        if (!$opp) {
            return back()->withErrors(['tracking' => 'Nenhum lead do site nos últimos 7 dias para testar.']);
        }

        $ev = $sender->send($opp, 'Lead')['meta'] ?? null;

        return $ev && $ev->status !== 'erro'
            ? back()->with('success', 'Evento de teste enviado. Confira na aba "Eventos de teste" do Gerenciador de Eventos (aparece em poucos segundos).')
            : back()->withErrors(['tracking' => 'A Meta recusou: ' . ($ev?->detail ?? 'sem resposta')]);
    }

    private function detail(Client $client): array
    {
        $t = ClientTracking::forClient($client);
        $meta = ClientAdAccount::where('client_id', $client->id)->where('platform', 'meta_ads')->get();
        $googleAccounts = ClientAdAccount::where('client_id', $client->id)->where('platform', 'google_ads')->get();

        return [
            'tracking'       => $t,
            'pixels'         => $this->pixels($meta),
            'googleAccounts' => $googleAccounts,
            'googleActions'  => $t->google_ads_account_id ? $this->googleActions($googleAccounts->firstWhere('id', $t->google_ads_account_id)) : [],
            'events'         => ConversionEvent::with('opportunity.lead:id,name')->where('client_id', $client->id)->latest()->limit(15)->get(),
            'leads7d'        => ClientLeadOpportunity::whereHas('lead', fn ($q) => $q->where('client_id', $client->id))->where('received_at', '>=', now()->subDays(7))->count(),
        ];
    }

    /** Pixels das contas Meta do cliente que o usuário da Nonna alcança. */
    private function pixels($accounts): array
    {
        $token = OrganizationIntegration::where('provider', 'meta')->where('status', 'connected')->first()?->credentials['access_token'];
        if (!$token) {
            return [];
        }

        return $accounts->flatMap(fn ($a) => Cache::remember("tracking_pixels:{$a->id}", 3600, fn () => collect(
            Http::timeout(20)->get('https://graph.facebook.com/' . MetaAdsFetcher::API_VERSION . "/act_{$a->account_id}/adspixels", ['fields' => 'id,name,last_fired_time', 'access_token' => $token])->json('data') ?? []
        )->map(fn ($p) => ['id' => $p['id'], 'name' => $p['name'], 'last' => $p['last_fired_time'] ?? null])->all()))->unique('id')->values()->all();
    }

    /** Conversões importadas que já existem na conta Google (para reaproveitar). */
    private function googleActions(?ClientAdAccount $account): array
    {
        if (!$account) {
            return [];
        }

        return Cache::remember("tracking_google_actions:{$account->id}", 3600, function () use ($account) {
            try {
                return collect(app(GoogleAdsApi::class)->search(GoogleAdsApi::cid($account), "SELECT conversion_action.resource_name, conversion_action.name, conversion_action.category FROM conversion_action WHERE conversion_action.type = 'UPLOAD_CLICKS' AND conversion_action.status = 'ENABLED'"))
                    ->map(fn ($r) => ['resource' => $r['conversionAction']['resourceName'], 'name' => $r['conversionAction']['name'], 'category' => $r['conversionAction']['category'] ?? null])->all();
            } catch (\Throwable) {
                return [];
            }
        });
    }
}
