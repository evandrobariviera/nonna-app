<?php

namespace App\Http\Controllers;

use App\Models\AiAgent;
use App\Models\Client;
use App\Models\ClientIntegration;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

class ClientIntegrationController extends Controller
{
    public function store(Request $request, Client $client): RedirectResponse
    {
        $data = $this->validateIntegration($request, requireToken: true);
        $data['client_id'] = $client->id;
        $data['settings']  = $this->extractSettings($request);

        $client->integrations()->create($data);

        return redirect()->route('clients.show', [$client, 'tab' => 'atendimento'])
            ->with('success', 'Número de atendimento adicionado.');
    }

    public function update(Request $request, Client $client, ClientIntegration $integration): RedirectResponse
    {
        $this->authorizeIntegration($client, $integration);

        $data = $this->validateIntegration($request, requireToken: false);

        // Token em branco mantém o já salvo - nunca reenviamos o token pro browser
        // pro usuário "confirmar" o que já está lá.
        if (empty($data['external_id'])) {
            unset($data['external_id']);
        }

        $data['settings'] = $this->extractSettings($request);

        $integration->update($data);

        return redirect()->route('clients.show', [$client, 'tab' => 'atendimento'])
            ->with('success', 'Número de atendimento atualizado.');
    }

    public function destroy(Client $client, ClientIntegration $integration): RedirectResponse
    {
        $this->authorizeIntegration($client, $integration);

        $integration->delete();

        return redirect()->route('clients.show', [$client, 'tab' => 'atendimento'])
            ->with('success', 'Número de atendimento removido.');
    }

    private function validateIntegration(Request $request, bool $requireToken): array
    {
        return $request->validate([
            'provider'    => ['required', 'string', 'in:' . implode(',', array_keys(ClientIntegration::$providers))],
            'label'       => ['required', 'string', 'max:120'],
            'external_id' => [$requireToken ? 'required' : 'nullable', 'string', 'max:255'],
            'status'      => ['required', 'string', 'in:' . implode(',', array_keys(ClientIntegration::$statuses))],
        ]);
    }

    private function extractSettings(Request $request): array
    {
        $agentId = $request->input('ai_agent_id');
        if ($agentId && !AiAgent::where('id', $agentId)->where('is_active', true)->exists()) {
            $agentId = null;
        }

        $avgTicket = $request->input('avg_ticket_value');
        $baseUrl   = $request->input('base_url');

        return array_filter([
            'diagnostic_frequency_days' => (int) $request->input('diagnostic_frequency_days', 30) ?: 30,
            'ai_agent_id'               => $agentId,
            'avg_ticket_value'          => $avgTicket !== null && $avgTicket !== '' ? (float) $avgTicket : null,
            'base_url'                  => $baseUrl !== null && $baseUrl !== '' ? rtrim($baseUrl, '/') : null,
        ], fn ($value) => $value !== null);
    }

    private function authorizeIntegration(Client $client, ClientIntegration $integration): void
    {
        abort_unless($integration->client_id === $client->id, 403);
    }
}
