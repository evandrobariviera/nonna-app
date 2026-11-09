<?php

namespace App\Http\Controllers;

use App\Jobs\CheckAdAccountConnectionsJob;
use App\Models\AdAccountConnection;
use App\Models\ClientAdAccount;
use App\Models\OrganizationIntegration;
use App\Services\AdSync\AdAccountConnectionChecker;
use App\Services\AdSync\MetaAdsFetcher;
use App\Services\Billing\AccountStatement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

/**
 * Contas e conexões (Central de Campanhas): o que falta, conta por conta,
 * para o App subir anúncio e para a medição funcionar.
 */
class AdAccountConnectionController extends Controller
{
    public function index(Request $request): View
    {
        $clientId = $request->get('client_id');
        $mine = $clientId === 'meus';

        $accounts = ClientAdAccount::with('client')
            ->whereIn('platform', ['meta_ads', 'google_ads'])
            ->whereHas('client', fn ($q) => $q->where('status', '!=', 'inactive'))
            ->when($clientId && !$mine, fn ($q) => $q->where('client_id', $clientId))
            ->when($mine, fn ($q) => $q->where('responsible_user_id', Auth::id()))
            ->get();

        $connections = AdAccountConnection::whereIn('client_ad_account_id', $accounts->pluck('id'))->get()->keyBy('client_ad_account_id');

        $order = ['sem_acesso' => 0, 'atencao' => 1, 'erro' => 2, 'ok' => 3];
        $groups = $accounts
            ->sortBy(fn ($a) => [$order[$connections->get($a->id)?->status] ?? 2, $a->client?->displayName()])
            ->groupBy('client_id');

        return view('ad-connections.index', [
            'groups'      => $groups,
            'connections' => $connections,
            'counts'      => $connections->countBy('status'),
            'lastCheck'   => $connections->max('checked_at'),
            'integrations' => $this->integrations(),
            'clients'     => AccountStatement::clientsWithAccounts(),
            'clientId'    => $mine ? null : $clientId,
            'mine'        => $mine,
            'metaUtm'     => AdAccountConnectionChecker::META_UTM,
            'googleUtm'   => AdAccountConnectionChecker::GOOGLE_UTM,
        ]);
    }

    public function refresh(): RedirectResponse
    {
        CheckAdAccountConnectionsJob::dispatch();

        return back()->with('success', 'Verificação iniciada. Leva cerca de 1 minuto: atualize a página em seguida.');
    }

    /** Aplica a UTM padrão no sufixo da conta Google inteira e confere de novo. */
    public function applyGoogleUtm(ClientAdAccount $adAccount, AdAccountConnectionChecker $checker): RedirectResponse
    {
        abort_unless($adAccount->platform === 'google_ads', 404);

        $result = $checker->applyGoogleUtm($adAccount);
        if (!$result['ok']) {
            return back()->withErrors(['utm' => 'O Google recusou a alteração: ' . $result['error']]);
        }

        $checker->check($adAccount);

        return back()->with('success', 'UTM padrão aplicada na conta ' . $adAccount->account_id . ' (vale para todas as campanhas, inclusive as que já estão no ar).');
    }

    /** Situação das conexões da Nonna (cache de 1 hora: consulta a Meta). */
    private function integrations(): array
    {
        return Cache::remember('ad-connections:integrations', 3600, function () {
            $meta = OrganizationIntegration::where('provider', 'meta')->first();
            $google = OrganizationIntegration::where('provider', 'google')->first();

            $metaPermissions = null;
            if ($meta?->status === 'connected' && ($token = $meta->credentials['access_token'] ?? null)) {
                $res = Http::timeout(10)->get('https://graph.facebook.com/' . MetaAdsFetcher::API_VERSION . '/me/permissions', ['access_token' => $token]);
                $metaPermissions = $res->successful()
                    ? collect($res->json('data', []))->where('status', 'granted')->pluck('permission')->all()
                    : null;
            }

            return [
                'meta' => [
                    'connected'   => $meta?->status === 'connected',
                    'can_create'  => $metaPermissions !== null && in_array('ads_management', $metaPermissions, true),
                    'permissions' => $metaPermissions,
                ],
                'google' => [
                    'connected' => $google?->status === 'connected',
                ],
            ];
        });
    }
}
