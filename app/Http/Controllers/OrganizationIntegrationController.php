<?php

namespace App\Http\Controllers;

use App\Models\OrganizationIntegration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OrganizationIntegrationController extends Controller
{
    private const CREDENTIAL_FIELDS = [
        'access_token', 'refresh_token', 'developer_token',
        'customer_id', 'login_customer_id', 'client_id', 'client_secret',
        'webhook_url', // usado só pelo provider "n8n" — ver NotificationDispatchService
    ];

    public function store(Request $request): RedirectResponse
    {
        $org = app('currentOrganization');

        $data = $this->validateIntegration($request);
        $data['credentials'] = $this->extractCredentials($request);

        $org->integrations()->create($data);

        return back()->with('success', 'Integração adicionada.')->with('tab', 'integracoes');
    }

    public function update(Request $request, OrganizationIntegration $integration): RedirectResponse
    {
        $this->authorizeIntegration($integration);

        $data = $this->validateIntegration($request);

        // Só sobrescreve as credenciais informadas no formulário - campos em
        // branco mantêm o valor já salvo (nunca reenviamos o secreto pro
        // browser para o usuário "confirmar" o que já está lá).
        $newCredentials = $this->extractCredentials($request);
        if (!empty($newCredentials)) {
            $data['credentials'] = array_merge($integration->credentials ?? [], $newCredentials);
        }

        $integration->update($data);

        return back()->with('success', 'Integração atualizada.')->with('tab', 'integracoes');
    }

    private function validateIntegration(Request $request): array
    {
        return $request->validate([
            'provider'    => ['required', 'string', 'in:' . implode(',', array_keys(OrganizationIntegration::$providers))],
            'label'       => ['required', 'string', 'max:120'],
            'external_id' => ['nullable', 'string', 'max:255'],
            'status'      => ['required', 'string', 'in:pending,connected,disconnected,error'],
        ]);
    }

    private function extractCredentials(Request $request): array
    {
        $creds = collect(self::CREDENTIAL_FIELDS)
            ->mapWithKeys(fn ($field) => [$field => is_string($v = $request->input("credentials.{$field}")) ? trim($v) : $v])
            ->filter(fn ($value) => filled($value))
            ->all();

        // Chave do Google Maps tem formato fixo (AIza + 35). Barra senha salva que o navegador
        // preencheu sozinho no campo (aconteceu em 25/11/2026: ficou uma senha de 13 caracteres).
        if ($request->input('provider') === 'google_maps' && isset($creds['access_token'])
            && !preg_match('/^AIza[0-9A-Za-z_\-]{35}$/', $creds['access_token'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'credentials.access_token' => 'Isso não parece uma chave do Google: ela começa com "AIza" e tem 39 caracteres. Copie de novo em Google Cloud → Credenciais → Mostrar chave.',
            ]);
        }

        return $creds;
    }

    public function destroy(OrganizationIntegration $integration): RedirectResponse
    {
        $this->authorizeIntegration($integration);
        $integration->delete();

        return back()->with('success', 'Integração removida.')->with('tab', 'integracoes');
    }

    private function authorizeIntegration(OrganizationIntegration $integration): void
    {
        $org = app('currentOrganization');
        abort_unless($integration->organization_id === $org->id, 403);
    }
}
