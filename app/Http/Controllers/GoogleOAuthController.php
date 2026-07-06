<?php

namespace App\Http\Controllers;

use App\Models\OrganizationIntegration;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

class GoogleOAuthController extends Controller
{
    private const SCOPE = 'https://www.googleapis.com/auth/adwords';

    public function connect(OrganizationIntegration $integration): RedirectResponse
    {
        $org = app('currentOrganization');
        abort_unless($integration->organization_id === $org->id, 403);

        $clientId = $integration->credentials['client_id'] ?? null;
        if (!$clientId) {
            return back()->with('error', 'Preencha o OAuth Client ID e Client Secret antes de conectar.')->with('tab', 'integracoes');
        }

        $params = [
            'client_id'     => $clientId,
            'redirect_uri'  => route('settings.integrations.google.callback'),
            'response_type' => 'code',
            'scope'         => self::SCOPE,
            'access_type'   => 'offline',
            'prompt'        => 'consent',
            'state'         => Crypt::encryptString($integration->id),
        ];

        return redirect('https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params));
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($request->filled('error')) {
            return redirect()->route('settings.index')
                ->with('error', 'Conexão com o Google cancelada ou falhou: ' . $request->input('error'))
                ->with('tab', 'integracoes');
        }

        try {
            $integrationId = Crypt::decryptString($request->input('state', ''));
        } catch (\Throwable $e) {
            abort(403, 'State inválido ou expirado.');
        }

        $integration = OrganizationIntegration::findOrFail($integrationId);
        $creds = $integration->credentials ?? [];

        $response = Http::timeout(15)->asForm()->post('https://oauth2.googleapis.com/token', [
            'code'          => $request->input('code'),
            'client_id'     => $creds['client_id'] ?? '',
            'client_secret' => $creds['client_secret'] ?? '',
            'redirect_uri'  => route('settings.integrations.google.callback'),
            'grant_type'    => 'authorization_code',
        ]);

        if ($response->failed()) {
            return redirect()->route('settings.index')
                ->with('error', 'Falha ao trocar o código pelo token do Google: ' . $response->body())
                ->with('tab', 'integracoes');
        }

        $creds['access_token'] = $response->json('access_token');
        $creds['token_expires_at'] = now()->addSeconds((int) $response->json('expires_in', 3600))->toIso8601String();
        if ($response->json('refresh_token')) {
            $creds['refresh_token'] = $response->json('refresh_token');
        }

        $integration->update(['credentials' => $creds, 'status' => 'connected']);

        return redirect()->route('settings.index')
            ->with('success', 'Google Ads conectado com sucesso.')
            ->with('tab', 'integracoes');
    }
}
